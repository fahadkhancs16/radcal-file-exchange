# Radcal File Exchange

Custom Laravel rebuild of the Radcal File Exchange (see the functional spec,
"Revision 2 — Preliminary"). Secure, **temporary** file transfer between Radcal
and its customers. One deployable, one admin role, ephemeral customer sessions.

## Stack

- Laravel 11, PHP 8.2 (XAMPP), MariaDB 10.4 (`radcal_share`)
- Livewire 3 for the customer workspace; Filament 3 for `/admin`
- Pest 3 + Larastan (level 6) + Pint
- Mail: `log` locally, Brevo in staging/production
- Requires the **`intl`** PHP extension (Filament's number/date formatting needs it) —
  in XAMPP's `php.ini`, uncomment `extension=intl` and restart whatever's serving PHP.

## Frontend

Customer UI is a hand-written stylesheet at `public/css/radcal.css` (no build
step) plus Inter from Google Fonts. Brand: iba green `#69BE28`, charcoal
`#323031` — sampled from `public/images/logo-iba-radcal.png`. The workspace
(`livewire/exchange-workspace.blade.php`) is a sidebar dashboard: the
`$section` property switches Overview / Files from Radcal / Your uploaded
files / Exchange details in place (no page reload). Public/auth pages use
`components/layouts/app.blade.php`; the dashboard uses
`components/layouts/exchange.blade.php`.

## Milestones

| M | Phases | State |
|---|--------|-------|
| **M1 — Foundation & customer-initiated transfer** | setup, data layer, domain services, Send Files to Radcal | **done** |
| **M2 — Exchange workspace, admin & email** | Access Exchange (full), Filament admin panel | **admin panel done**; Brevo domain auth + ZIP download still open |
| M3 — Hardening & launch | purge job, resumable uploads, security, tests, deploy | not started |

## Run it

```bash
cp .env.example .env && php artisan key:generate
# start MariaDB in XAMPP, then:
php artisan migrate:fresh --seed        # admin@radcal.com / password ; demo exchanges
php artisan serve                       # http://127.0.0.1:8000
composer test                           # Pest
composer check                          # Pint + Larastan + Pest
```

`APP_URL` must match how you reach the app (`http://127.0.0.1:8000` for
`artisan serve`) — Livewire builds its JS/upload endpoint URLs from it, so a
mismatch silently breaks every `wire:click`, including file uploads. Serving
under a XAMPP subdirectory means setting `APP_URL` to that full URL and using it.

Local mail is written to `storage/logs/laravel.log` — read verification codes there.

Seeded exchange for manual testing: code `ABC123`, password `demo-pass`.
Admin login: `admin@radcal.com` / `password` at `/admin`. Create more admins with
`php artisan app:make-admin`.

## Admin panel

Filament 3 at `/admin`, restricted to `User::is_admin` via `canAccessPanel()`
(`app/Models/User.php`). There is deliberately **no generic Edit page** — the
`ExchangeResource` view page (`app/Filament/Resources/ExchangeResource/Pages/ViewExchange.php`)
is the whole "cockpit": every field an admin can change is its own explicit,
confirmed header action, and every action calls the same `App\Services\*`
classes the customer UI uses (never raw Eloquent), so activity logging, disk
writes and the expiration clock all stay correct. File management for both
sides of an exchange is two relation managers sharing
`BaseExchangeFilesRelationManager`; uploads go through `FileService::store()`
via `FileUpload::make('files')->storeFiles(false)` (so we get the raw
`TemporaryUploadedFile`, not a Filament-managed stored path); downloads go
through a signed-in-only controller registered inside the panel's own route
group (`AdminPanelProvider::routes()`), not a public URL.

Two Filament gotchas worth knowing before touching this code:
- **Never name a header-action-builder method `{actionName}Action`** (e.g. a
  method called `enableAction()` for an action named `enable`) — Filament's own
  action auto-discovery collides with it and calls it through Livewire's public
  method dispatch, which throws on private methods. The builder methods here
  are prefixed `buildXAction()` to avoid this.
- **`TextColumn::make('someJsonColumn')` auto-implodes an array-cast attribute
  into a comma-joined string** before `formatStateUsing()` ever sees it. To read
  the real array (e.g. `activity_logs.meta`), use `->getStateUsing(fn ($record) => ...)`
  instead, as `ActivityRelationManager` does.

## Architecture

Domain logic lives in `app/Services/` so the customer UI (Livewire) and the
admin panel (Filament, M2) share one code path for every rule.

- `ExchangeService` — create / disable / delete / purge, settings changes
- `FileService` — store, replace-by-filename, delete, download logging; enforces the per-exchange size limit
- `ExpirationService` — the 14-day clock; **only** file add/replace resets it (see `tests/Unit/ExpirationServiceTest.php` — that test is the spec contract)
- `VerificationService` — 6-digit email codes for "Send Files to Radcal"
- `ActivityRecorder` — writes `activity_logs`; resolves the actor (admin / customer / system)

### Key rules baked in

- Storage: one folder per exchange on the `exchanges` disk (`config/exchange.php` → `EXCHANGE_STORAGE_DISK`). Never web-served. Expiry = delete the folder.
- Exchange codes are **upper-case** (`[A-Z0-9-]`), which is why `/{code}` routes never collide with `/send`, `/access`, `/admin`.
- Customers are never `users`. Access is an ephemeral session holding one `exchange.id` (`App\Support\ExchangeSession`), enforced by the `exchange.session` middleware, which 404s (never 403) on any mismatch.
- A wrong code and a wrong password fail identically.
- "Replace by same filename" is case-insensitive and scoped to `(exchange, owner)` — a Radcal file and a customer file may share a name.

## Data model

`exchanges`, `exchange_files`, `email_verifications`, `activity_logs`, plus
`users.is_admin`. `ExchangeStatus` (Active/Disabled/Expired/Purged) is derived,
never stored.

## Conventions

- Enums in `app/Enums/`, backed by strings that match the DB values.
- Services are constructor-injected; call them from controllers/Livewire, not from models.
- New behaviour needs a Pest test. Feature tests use the real MySQL test DB (`radcal_share_test`).
- Livewire actions must not be named `upload`/`uploadMultiple` (or other `WithFileUploads` names) — `wire:click` on them is swallowed. The upload action is `saveUploads()`.
- Run `composer check` before considering work done.

## Not yet built (later milestones)

ZIP "Download Selected" for Radcal files (M2), Brevo domain authentication so
mail doesn't land in spam (M2 — see the DNS notes above), `exchanges:purge`
scheduled command (M3), chunked/resumable uploads for 100–500 MB files (M3),
full CSP/HSTS (M3).

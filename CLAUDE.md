# Radcal File Exchange

Custom Laravel rebuild of the Radcal File Exchange (see the functional spec,
"Revision 2 — Preliminary"). Secure, **temporary** file transfer between Radcal
and its customers. One deployable, one admin role, ephemeral customer sessions.

## Stack

- Laravel 11, PHP 8.2 (XAMPP), MariaDB 10.4 (`radcal_share`)
- Livewire 3 for the customer workspace; Filament 3 for `/admin` (Milestone 2)
- Pest 3 + Larastan (level 6) + Pint
- Mail: `log` locally, Brevo in staging/production

## Milestones

| M | Phases | State |
|---|--------|-------|
| **M1 — Foundation & customer-initiated transfer** | setup, data layer, domain services, Send Files to Radcal | **done** |
| M2 — Exchange workspace, admin & email | Access Exchange (full), Filament admin, Brevo | not started |
| M3 — Hardening & launch | purge job, resumable uploads, security, tests, deploy | not started |

## Run it

```bash
cp .env.example .env && php artisan key:generate
# start MariaDB in XAMPP, then:
php artisan migrate:fresh --seed        # admin@radcal.com / password ; demo exchanges
php artisan serve                       # or serve via XAMPP at /radcal-file-exchange/public
composer test                           # Pest
composer check                          # Pint + Larastan + Pest
```

Local mail is written to `storage/logs/laravel.log` — read verification codes there.

Seeded exchange for manual testing: code `ABC123`, password `demo-pass`.

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
- Run `composer check` before considering work done.

## Not yet built (later milestones)

ZIP "Download Selected" for Radcal files (M2), Filament `/admin` (M2), Brevo
transport wiring (M2), `exchanges:purge` scheduled command (M3), chunked/resumable
uploads for 100–500 MB files (M3), full CSP/HSTS (M3).

<?php

namespace App\Models;

use App\Enums\ExchangeOrigin;
use App\Enums\ExchangeStatus;
use App\Enums\FileOwner;
use App\Support\ExchangeCode;
use Database\Factories\ExchangeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * A single temporary two-way workspace between Radcal and one customer.
 *
 * @property int $id
 * @property string $code
 * @property string $customer_name
 * @property string|null $company
 * @property string $email
 * @property string|null $description
 * @property ExchangeOrigin $origin
 * @property int $max_file_size
 * @property Carbon $expires_at
 * @property Carbon|null $disabled_at
 * @property Carbon|null $purged_at
 * @property Carbon $created_at
 */
class Exchange extends Model
{
    /** @use HasFactory<ExchangeFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'password_hash',
        'customer_name',
        'company',
        'email',
        'description',
        'origin',
        'max_file_size',
        'created_by',
        'expires_at',
        'disabled_at',
        'purged_at',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            'origin' => ExchangeOrigin::class,
            'max_file_size' => 'integer',
            'expires_at' => 'datetime',
            'disabled_at' => 'datetime',
            'purged_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Exchange $exchange): void {
            if (blank($exchange->code)) {
                $exchange->code = ExchangeCode::generate();
            }
            if (blank($exchange->max_file_size)) {
                $exchange->max_file_size = (int) config('exchange.default_max_bytes');
            }
            if (blank($exchange->expires_at)) {
                $exchange->expires_at = now()->addDays((int) config('exchange.lifetime_days'));
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    // ---------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------

    /** @return HasMany<ExchangeFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(ExchangeFile::class);
    }

    /** @return HasMany<ExchangeFile, $this> */
    public function radcalFiles(): HasMany
    {
        return $this->files()->where('owner', FileOwner::Radcal->value);
    }

    /** @return HasMany<ExchangeFile, $this> */
    public function customerFiles(): HasMany
    {
        return $this->files()->where('owner', FileOwner::Customer->value);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<ActivityLog, $this> */
    public function activity(): HasMany
    {
        return $this->hasMany(ActivityLog::class)->latest('created_at');
    }

    // ---------------------------------------------------------------------
    // State
    // ---------------------------------------------------------------------

    public function status(): ExchangeStatus
    {
        return match (true) {
            $this->purged_at !== null => ExchangeStatus::Purged,
            $this->disabled_at !== null => ExchangeStatus::Disabled,
            $this->expires_at->isPast() => ExchangeStatus::Expired,
            default => ExchangeStatus::Active,
        };
    }

    public function isReachable(): bool
    {
        return $this->status()->isReachable();
    }

    public function isExpiringSoon(): bool
    {
        return $this->isReachable()
            && $this->expires_at->lte(now()->addDays((int) config('exchange.expiring_soon_days')));
    }

    // ---------------------------------------------------------------------
    // Password
    // ---------------------------------------------------------------------

    public function setPassword(string $plain): void
    {
        $this->password_hash = Hash::make($plain);
    }

    public function checkPassword(string $plain): bool
    {
        if (! Hash::check($plain, $this->password_hash)) {
            return false;
        }

        if (Hash::needsRehash($this->password_hash)) {
            $this->forceFill(['password_hash' => Hash::make($plain)])->saveQuietly();
        }

        return true;
    }

    // ---------------------------------------------------------------------
    // Storage
    // ---------------------------------------------------------------------

    /** Path, relative to the exchanges disk root, of this exchange's folder. */
    public function storageDirectory(): string
    {
        return $this->code;
    }

    public function storagePathFor(string $storedName): string
    {
        return $this->storageDirectory().'/'.$storedName;
    }

    // ---------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------

    /** @param Builder<Exchange> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('disabled_at')
            ->whereNull('purged_at')
            ->where('expires_at', '>', now());
    }

    /** @param Builder<Exchange> $query */
    public function scopeDueForPurge(Builder $query): void
    {
        $query->whereNull('purged_at')
            ->where('expires_at', '<=', now());
    }

    /** @param Builder<Exchange> $query */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        $query->where(function (Builder $q) use ($like, $term): void {
            $q->where('code', 'like', $like)
                ->orWhere('customer_name', 'like', $like)
                ->orWhere('company', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhereHas('files', fn (Builder $f) => $f->where('original_filename', 'like', $like));

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $term)) {
                $q->orWhereDate('created_at', $term)->orWhereDate('expires_at', $term);
            }
        });
    }
}

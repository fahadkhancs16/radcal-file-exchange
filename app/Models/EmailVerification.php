<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * A pending "Send Files to Radcal" verification. Holds the customer's
 * details until the emailed 6-digit code is confirmed.
 *
 * @property int $id
 * @property string $email
 * @property array<string, mixed> $payload
 * @property Carbon $expires_at
 * @property int $attempts
 * @property Carbon|null $consumed_at
 */
class EmailVerification extends Model
{
    protected $fillable = [
        'email',
        'code_hash',
        'payload',
        'expires_at',
        'attempts',
        'consumed_at',
    ];

    protected $hidden = [
        'code_hash',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function isLocked(): bool
    {
        return $this->attempts >= (int) config('exchange.verification.max_attempts');
    }

    public function isUsable(): bool
    {
        return ! $this->isExpired() && ! $this->isConsumed() && ! $this->isLocked();
    }

    public function codeMatches(string $code): bool
    {
        return Hash::check($code, $this->code_hash);
    }
}

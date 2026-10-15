<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * A colleague a customer invited into their exchange. Has their own password
 * and the same rights as the customer inside that one exchange.
 *
 * @property int $id
 * @property int $exchange_id
 * @property string $email
 * @property string|null $name
 * @property string $password_hash
 * @property string $invited_by
 * @property Carbon $created_at
 */
class ExchangeMember extends Model
{
    protected $fillable = [
        'exchange_id',
        'email',
        'name',
        'password_hash',
        'invited_by',
    ];

    protected $hidden = [
        'password_hash',
    ];

    /** @return BelongsTo<Exchange, $this> */
    public function exchange(): BelongsTo
    {
        return $this->belongsTo(Exchange::class);
    }

    public function setPassword(string $plain): void
    {
        $this->password_hash = Hash::make($plain);
    }

    public function checkPassword(string $plain): bool
    {
        return Hash::check($plain, $this->password_hash);
    }
}

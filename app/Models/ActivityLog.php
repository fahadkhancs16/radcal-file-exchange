<?php

namespace App\Models;

use App\Enums\ActivityAction;
use App\Enums\ActorType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An append-only record of everything done to an exchange. Survives the
 * exchange being purged (exchange_id goes null, exchange_code is kept).
 *
 * @property int $id
 * @property int|null $exchange_id
 * @property string|null $exchange_code
 * @property ActorType $actor_type
 * @property int|null $actor_id
 * @property string|null $actor_label
 * @property ActivityAction $action
 * @property array<string, mixed>|null $meta
 * @property string|null $ip_address
 * @property Carbon $created_at
 */
class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'exchange_id',
        'exchange_code',
        'actor_type',
        'actor_id',
        'actor_label',
        'action',
        'meta',
        'ip_address',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'actor_type' => ActorType::class,
            'action' => ActivityAction::class,
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Exchange, $this> */
    public function exchange(): BelongsTo
    {
        return $this->belongsTo(Exchange::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

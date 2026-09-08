<?php

namespace App\Models;

use App\Enums\FileOwner;
use Database\Factories\ExchangeFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One file inside an exchange, on one side of it.
 *
 * @property int $id
 * @property int $exchange_id
 * @property FileOwner $owner
 * @property string $original_filename
 * @property string $stored_name
 * @property int $size
 * @property string|null $mime
 * @property int|null $uploaded_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ExchangeFile extends Model
{
    /** @use HasFactory<ExchangeFileFactory> */
    use HasFactory;

    protected $fillable = [
        'exchange_id',
        'owner',
        'original_filename',
        'stored_name',
        'size',
        'mime',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'owner' => FileOwner::class,
            'size' => 'integer',
        ];
    }

    /** @return BelongsTo<Exchange, $this> */
    public function exchange(): BelongsTo
    {
        return $this->belongsTo(Exchange::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isCustomerOwned(): bool
    {
        return $this->owner === FileOwner::Customer;
    }

    public function humanSize(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);

        return round($bytes / (1024 ** $power), $power > 0 ? 1 : 0).' '.$units[$power];
    }
}

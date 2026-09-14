<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Models\Exchange;
use Illuminate\Support\Carbon;

/**
 * Owns the expiration clock. The rules (spec §5, §8, §9):
 *
 *   - Expiration belongs to the exchange, never to individual files.
 *   - Adding OR replacing a file (by either side) resets it to now + 14 days.
 *   - Downloading, viewing and logging in never change it.
 *   - Deleting a file does not change it (spec lists only add/replace).
 *   - An administrator may set it to any explicit date.
 *   - The stored value IS the deletion date — no separate grace period.
 */
class ExpirationService
{
    public function __construct(private readonly ActivityRecorder $activity) {}

    public function lifetimeDays(): int
    {
        return (int) config('exchange.lifetime_days');
    }

    /** The datetime a fresh reset would produce. */
    public function nextDeadline(?Carbon $from = null): Carbon
    {
        return ($from ?? now())->copy()->addDays($this->lifetimeDays());
    }

    /**
     * Reset the clock because a file was added or replaced. Never shortens an
     * exchange whose expiry an admin pushed further out than the standard
     * window.
     */
    public function bumpForFileActivity(Exchange $exchange): void
    {
        $deadline = $this->nextDeadline();

        if ($exchange->expires_at->greaterThan($deadline)) {
            return;
        }

        $exchange->forceFill(['expires_at' => $deadline])->save();
    }

    /** An administrator sets an explicit expiration date. */
    public function setExplicit(Exchange $exchange, Carbon $when): void
    {
        $previous = $exchange->expires_at;
        $exchange->forceFill(['expires_at' => $when])->save();

        $this->activity->record($exchange, ActivityAction::ExpirationChanged, [
            'from' => $previous->toDateTimeString(),
            'to' => $when->toDateTimeString(),
        ]);
    }

    /** Extend by a number of days from the later of now / current expiry. */
    public function extend(Exchange $exchange, int $days): void
    {
        $previous = $exchange->expires_at;
        $base = $previous->isFuture() ? $previous : now();
        $when = $base->copy()->addDays($days);

        $exchange->forceFill(['expires_at' => $when])->save();

        $this->activity->record($exchange, ActivityAction::ExpirationChanged, [
            'from' => $previous->toDateTimeString(),
            'to' => $when->toDateTimeString(),
            'extended_days' => $days,
        ]);
    }
}

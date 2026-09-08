<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Enums\ActorType;
use App\Models\ActivityLog;
use App\Models\Exchange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Writes the activity_logs trail. The actor is resolved from the current
 * context unless one is forced with actingAsSystem() (used by console
 * commands such as the purge job).
 */
class ActivityRecorder
{
    private bool $forceSystem = false;

    private string $systemLabel = 'System';

    public function __construct(private readonly Request $request) {}

    public function actingAsSystem(string $label = 'System'): self
    {
        $clone = clone $this;
        $clone->forceSystem = true;
        $clone->systemLabel = $label;

        return $clone;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function record(Exchange $exchange, ActivityAction $action, array $meta = []): ActivityLog
    {
        [$type, $id, $label] = $this->resolveActor($exchange);

        return ActivityLog::create([
            'exchange_id' => $exchange->exists ? $exchange->getKey() : null,
            'exchange_code' => $exchange->code,
            'actor_type' => $type,
            'actor_id' => $id,
            'actor_label' => $label,
            'action' => $action,
            'meta' => $meta ?: null,
            'ip_address' => $this->forceSystem ? null : $this->request->ip(),
            'created_at' => now(),
        ]);
    }

    /**
     * @return array{0: ActorType, 1: int|null, 2: string|null}
     */
    private function resolveActor(Exchange $exchange): array
    {
        if ($this->forceSystem) {
            return [ActorType::System, null, $this->systemLabel];
        }

        $user = Auth::user();
        if ($user !== null) {
            return [ActorType::Admin, $user->getKey(), $user->name];
        }

        // A customer acting inside an exchange session.
        $sessionEmail = $this->request->hasSession()
            ? $this->request->session()->get('exchange.customer_email')
            : null;

        return [ActorType::Customer, null, $sessionEmail ?? $exchange->email];
    }
}

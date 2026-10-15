<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Exceptions\ColleagueException;
use App\Models\Exchange;
use App\Models\ExchangeMember;
use App\Support\PasswordGenerator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Colleagues invited into an exchange by a customer. Each gets their own
 * generated password (mailed to them) and thereafter has the same rights
 * as the customer within that one exchange.
 */
class ColleagueService
{
    public function __construct(private readonly ActivityRecorder $activity) {}

    /**
     * @return array{member: ExchangeMember, password: string}
     *
     * @throws ColleagueException
     */
    public function invite(Exchange $exchange, string $email, ?string $name, string $invitedBy): array
    {
        $email = Str::lower(trim($email));

        if ($email === Str::lower($exchange->email)) {
            throw ColleagueException::isOwner();
        }

        if ($exchange->members()->where('email', $email)->exists()) {
            throw ColleagueException::alreadyInvited($email);
        }

        $limit = (int) config('exchange.max_colleagues');

        if ($exchange->members()->count() >= $limit) {
            throw ColleagueException::limitReached($limit);
        }

        $password = PasswordGenerator::generate();

        $member = new ExchangeMember([
            'exchange_id' => $exchange->getKey(),
            'email' => $email,
            'name' => filled($name) ? trim((string) $name) : null,
            'invited_by' => $invitedBy,
        ]);
        $member->setPassword($password);
        $member->save();

        $this->activity->record($exchange, ActivityAction::ColleagueInvited, [
            'email' => $email,
            'invited_by' => $invitedBy,
        ]);

        return ['member' => $member, 'password' => $password];
    }

    /**
     * Everyone the signed-in customer could send files to: the contact and
     * invited colleagues of this exchange, then the contact of every other
     * active exchange (i.e. every other customer). Includes the caller —
     * the workspace filters themselves out. Emails are lower-cased; `key`
     * ("<exchange id>:<email>") uniquely identifies a person because the
     * same address could appear in two exchanges.
     *
     * @return Collection<int, array{key: string, email: string, name: string|null, company: string|null, code: string, contact: bool, member_id: int|null, exchange_id: int, own: bool}>
     */
    public function team(Exchange $exchange): Collection
    {
        $person = fn (Exchange $e, string $email, ?string $name, bool $contact, ?int $memberId, bool $own) => [
            'key' => $e->getKey().':'.Str::lower($email),
            'email' => Str::lower($email),
            'name' => $name,
            'company' => $e->company,
            'code' => $e->code,
            'contact' => $contact,
            'member_id' => $memberId,
            'exchange_id' => $e->getKey(),
            'own' => $own,
        ];

        $own = $exchange->members()->orderBy('created_at')->get()
            ->map(fn (ExchangeMember $m) => $person($exchange, $m->email, $m->name, false, $m->id, true))
            ->prepend($person($exchange, $exchange->email, $exchange->customer_name, true, null, true));

        $others = Exchange::query()->active()
            ->whereKeyNot($exchange->getKey())
            ->orderBy('customer_name')
            ->get()
            ->map(fn (Exchange $e) => $person($e, $e->email, $e->customer_name, true, null, false));

        return $own->concat($others)->values();
    }

    public function remove(Exchange $exchange, ExchangeMember $member): void
    {
        $email = $member->email;
        $member->delete();

        $this->activity->record($exchange, ActivityAction::ColleagueRemoved, ['email' => $email]);
    }
}

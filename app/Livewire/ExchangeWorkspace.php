<?php

namespace App\Livewire;

use App\Enums\FileOwner;
use App\Exceptions\ColleagueException;
use App\Exceptions\FileUploadException;
use App\Mail\ColleagueInvitedMail;
use App\Models\Exchange;
use App\Models\ExchangeFile;
use App\Services\ColleagueService;
use App\Services\FileService;
use App\Services\UploadNotificationService;
use App\Support\ExchangeSession;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Response;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The customer's dashboard view of one exchange (spec §4.2–4.3). Radcal's
 * files are read-only; the customer may add, replace (same filename) and
 * delete their own. ZIP "Download Selected" for Radcal files arrives in
 * Milestone 2 — for now each Radcal file downloads on its own.
 */
#[Layout('components.layouts.exchange')]
class ExchangeWorkspace extends Component
{
    use WithFileUploads;

    public Exchange $exchange;

    public bool $firstVisit = false;

    /** Which sidebar section is shown: overview | radcal | uploads | team | colleague_files | details. */
    public string $section = 'overview';

    /** @var array<int, TemporaryUploadedFile> */
    public array $uploads = [];

    public string $explanation = '';

    /** @var array<int, int|string> */
    public array $selected = [];

    /** Team transfer form: the chosen recipient's email, or NEW_RECIPIENT to invite someone. */
    public string $recipient = '';

    public string $newEmail = '';

    public string $newName = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $teamUploads = [];

    public string $teamExplanation = '';

    private const NEW_RECIPIENT = '__new__';

    public function mount(string $code): void
    {
        // EnsureExchangeSession has already authorised and loaded the exchange.
        $this->exchange = request()->attributes->get('exchange')
            ?? Exchange::query()->where('code', $code)->firstOrFail();

        $this->firstVisit = (bool) session('exchange.first_visit', false);

        if ($this->firstVisit) {
            $this->section = 'uploads';
        }
    }

    public function showSection(string $section): void
    {
        if (in_array($section, ['overview', 'radcal', 'uploads', 'team', 'colleague_files', 'details'], true)) {
            $this->section = $section;
            $this->reset('selected', 'teamUploads', 'teamExplanation', 'recipient', 'newEmail', 'newName');
            $this->resetErrorBag();
        }
    }

    /** The signed-in customer-side person (exchange contact or an invited colleague). */
    private function currentEmail(): string
    {
        return app(ExchangeSession::class)->customerEmail() ?? $this->exchange->email;
    }

    public function isContact(): bool
    {
        return mb_strtolower($this->currentEmail()) === mb_strtolower($this->exchange->email);
    }

    /** Pick who the next transfer goes to (an existing person's email, or the invite-new option). */
    public function chooseRecipient(string $recipient): void
    {
        $this->recipient = $recipient;
        $this->resetErrorBag();
    }

    /**
     * Send the pending files to one colleague, inviting them first when the
     * recipient is a new address. Mirrors saveUploads() for the Radcal side.
     */
    public function sendToColleague(
        FileService $files,
        ColleagueService $colleagues,
        UploadNotificationService $notifications,
    ): void {
        $inviting = $this->recipient === self::NEW_RECIPIENT;

        $this->validate([
            'recipient' => ['required', 'string'],
            'newEmail' => [$inviting ? 'required' : 'nullable', 'email:rfc', 'max:190'],
            'newName' => ['nullable', 'string', 'max:120'],
            'teamUploads' => ['required', 'array', 'min:1'],
            'teamUploads.*' => ['file'],
            'teamExplanation' => ['required', 'string', 'max:2000'],
        ], [
            'recipient.required' => 'Choose who to send the files to.',
            'teamUploads.required' => 'Add at least one file.',
            'teamUploads.min' => 'Add at least one file.',
        ]);

        $sender = mb_strtolower($this->currentEmail());
        $myId = $this->exchange->getKey();
        $isMe = fn (array $p) => $p['exchange_id'] === $myId && $p['email'] === $sender;
        $team = $colleagues->team($this->exchange)->reject($isMe);

        $target = mb_strtolower($inviting ? $this->newEmail : '');

        if ($inviting && $target === $sender) {
            $this->addError('newEmail', 'You cannot send files to yourself.');

            return;
        }

        // Typing the address of someone who is already a customer just sends to them.
        $person = $inviting
            ? $team->first(fn (array $p) => $p['email'] === $target)
            : $team->firstWhere('key', $this->recipient);

        if (! $inviting && $person === null) {
            $this->addError('recipient', 'That person is not available to receive files.');

            return;
        }

        if ($person === null) {
            try {
                ['member' => $member, 'password' => $password] = $colleagues->invite(
                    $this->exchange, $target, trim($this->newName) ?: null, $sender,
                );
            } catch (ColleagueException $e) {
                $this->addError('newEmail', $e->getMessage());

                return;
            }

            try {
                Mail::to($member->email)->send(new ColleagueInvitedMail(
                    $this->exchange, $password, $sender, trim($this->teamExplanation) ?: null,
                ));
            } catch (\Throwable $e) {
                // Without the email the colleague can never learn their password.
                $colleagues->remove($this->exchange, $member);
                Log::error('Colleague invite email failed', ['exchange' => $this->exchange->code, 'error' => $e->getMessage()]);
                $this->addError('newEmail', 'We could not send the invitation email. Please try again.');

                return;
            }

            $person = ['key' => $myId.':'.$target, 'email' => $target, 'exchange_id' => $myId];
        }

        $target = $person['email'];
        $recipientExchange = $person['exchange_id'] === $myId
            ? $this->exchange
            : Exchange::query()->find($person['exchange_id']);

        $explanation = trim($this->teamExplanation) ?: null;
        $stored = collect();
        $rejected = [];

        foreach ($this->teamUploads as $upload) {
            try {
                $stored->push($files->store(
                    $this->exchange, FileOwner::Colleague, $upload,
                    note: $explanation, uploadedByEmail: $sender, recipientEmail: $target,
                    recipientExchangeId: $person['exchange_id'],
                ));
            } catch (FileUploadException $e) {
                $rejected[] = $e->getMessage();
            }
        }

        $this->reset('teamUploads');
        $this->exchange->refresh();

        if ($stored->isNotEmpty()) {
            $count = $stored->count();
            $notifications->notifyColleagueOfFiles($this->exchange, $stored, $sender, $target, $explanation, $recipientExchange);
            $this->reset('teamExplanation', 'newEmail', 'newName');
            $this->recipient = $person['key'];
            $this->dispatch('notify', message: $count.' file'.($count === 1 ? '' : 's').' sent to '.$target.'.');
        }

        foreach ($rejected as $message) {
            $this->addError('teamUploads', $message);
        }
    }

    public function deleteSentColleagueFiles(FileService $files): void
    {
        if ($this->selected === []) {
            return;
        }

        $count = $files->deleteSelected(
            $this->exchange,
            FileOwner::Colleague,
            array_map('intval', $this->selected),
            onlyUploadedBy: $this->currentEmail(),
        );

        $this->reset('selected');
        $this->dispatch('notify', message: $count.' file'.($count === 1 ? '' : 's').' removed.');
    }

    public function downloadColleagueFile(int $fileId, FileService $files): StreamedResponse
    {
        // Only the sender and the recipient may fetch a transfer. The bytes live
        // in the sender's exchange, which may not be the one this person is in.
        $file = ExchangeFile::query()->with('exchange')
            ->involving($this->exchange->getKey(), $this->currentEmail())
            ->findOrFail($fileId);

        $files->recordDownload($file->exchange, collect([$file]));

        return $this->streamFile($files, $file);
    }

    public function removeColleague(int $memberId, ColleagueService $colleagues): void
    {
        // Only the exchange contact may revoke someone's access.
        if (! $this->isContact()) {
            return;
        }

        $member = $this->exchange->members()->find($memberId);

        if ($member !== null) {
            $colleagues->remove($this->exchange, $member);
            $this->dispatch('notify', message: $member->email.' no longer has access.');
        }
    }

    public function leave(ExchangeSession $session): void
    {
        $session->revoke();
        $this->redirectRoute('home');
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        // The per-file byte ceiling is enforced (with a friendly message) by
        // FileService; here we only assert each item really is an upload.
        return [
            'uploads' => ['array'],
            'uploads.*' => ['file'],
            'explanation' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * Persist the pending uploads onto the exchange.
     *
     * NB: must not be named `upload()` / `uploadMultiple()` — Livewire's
     * WithFileUploads trait reserves those for the temp-upload machinery, so
     * a wire:click on them is silently swallowed.
     */
    public function saveUploads(FileService $files, UploadNotificationService $notifications): void
    {
        $this->validate();

        $stored = collect();
        $rejected = [];

        $explanation = trim($this->explanation) ?: null;

        foreach ($this->uploads as $upload) {
            try {
                // TemporaryUploadedFile extends UploadedFile — FileService takes it as-is.
                $stored->push($files->store(
                    $this->exchange, FileOwner::Customer, $upload,
                    note: $explanation, uploadedByEmail: $this->currentEmail(),
                ));
            } catch (FileUploadException $e) {
                $rejected[] = $e->getMessage();
            }
        }

        $this->reset('uploads');
        $this->exchange->refresh();

        if ($stored->isNotEmpty()) {
            $count = $stored->count();
            $this->dispatch('notify', message: $count.' file'.($count === 1 ? '' : 's').' uploaded.');
            $notifications->notifyStaffOfCustomerUpload($this->exchange, $stored, $explanation);
            $this->reset('explanation');
        }

        foreach ($rejected as $message) {
            $this->addError('uploads', $message);
        }
    }

    public function deleteSelected(FileService $files): void
    {
        if ($this->selected === []) {
            return;
        }

        $count = $files->deleteSelected(
            $this->exchange,
            FileOwner::Customer,
            array_map('intval', $this->selected),
        );

        $this->reset('selected');
        $this->exchange->refresh();
        $this->dispatch('notify', message: $count.' file'.($count === 1 ? '' : 's').' removed.');
    }

    public function downloadRadcalFile(int $fileId, FileService $files): StreamedResponse
    {
        $file = $this->exchange->radcalFiles()->findOrFail($fileId);

        $files->recordDownload($this->exchange, collect([$file]));

        return Response::streamDownload(
            fn () => print $files->disk()->get($this->exchange->storagePathFor($file->stored_name)),
            $file->original_filename,
        );
    }

    public function downloadCustomerFile(int $fileId, FileService $files): StreamedResponse
    {
        $file = $this->exchange->customerFiles()->findOrFail($fileId);

        $files->recordDownload($this->exchange, collect([$file]));

        return $this->streamFile($files, $file);
    }

    private function streamFile(FileService $files, ExchangeFile $file): StreamedResponse
    {
        return Response::streamDownload(function () use ($files, $file) {
            $stream = $files->disk()->readStream($file->exchange->storagePathFor($file->stored_name));

            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, $file->original_filename);
    }

    public function render(): View
    {
        return view('livewire.exchange-workspace', [
            'radcalFiles' => $this->exchange->radcalFiles()->latest()->get(),
            'customerFiles' => $this->exchange->customerFiles()->latest()->get(),
            'others' => $others = app(ColleagueService::class)->team($this->exchange)
                ->reject(fn (array $p) => $p['exchange_id'] === $this->exchange->getKey() && $p['email'] === mb_strtolower($this->currentEmail()))
                ->values(),
            'recipientPerson' => $others->firstWhere('key', $this->recipient),
            'colleagueFiles' => ExchangeFile::query()
                ->involving($this->exchange->getKey(), $this->currentEmail())
                ->latest()->get(),
            'me' => mb_strtolower($this->currentEmail()),
        ]);
    }
}

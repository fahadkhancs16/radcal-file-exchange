<?php

namespace App\Livewire;

use App\Enums\FileOwner;
use App\Exceptions\FileUploadException;
use App\Mail\ExchangeReadyMail;
use App\Models\Exchange;
use App\Services\FileService;
use App\Support\ExchangeSession;
use Illuminate\Contracts\View\View;
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

    /** Session key holding the not-yet-sent reference/password email (set by SendFilesController). */
    public const READY_MAIL_SESSION_KEY = 'exchange.ready_mail';

    public Exchange $exchange;

    public bool $firstVisit = false;

    /** Which sidebar section is shown: overview | radcal | uploads | details. */
    public string $section = 'overview';

    /** @var array<int, TemporaryUploadedFile> */
    public array $uploads = [];

    /**
     * Landing slot for the file input. Livewire replaces the bound array on
     * every selection, so each new batch lands here and is appended to
     * $uploads in updatedIncoming() — earlier picks are never lost.
     *
     * @var array<int, TemporaryUploadedFile>
     */
    public array $incoming = [];

    public string $explanation = '';

    /** @var array<int, int|string> */
    public array $selected = [];

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

    public function updatedIncoming(): void
    {
        foreach ($this->incoming as $file) {
            $this->uploads[] = $file;
        }

        $this->reset('incoming');
    }

    public function showSection(string $section): void
    {
        if (in_array($section, ['overview', 'radcal', 'uploads', 'details'], true)) {
            $this->section = $section;
            $this->reset('selected');
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
    public function saveUploads(FileService $files): void
    {
        $this->validate();

        $stored = collect();
        $rejected = [];

        $explanation = trim($this->explanation) ?: null;

        foreach ($this->uploads as $upload) {
            try {
                // TemporaryUploadedFile extends UploadedFile — FileService takes it as-is.
                $stored->push($files->store($this->exchange, FileOwner::Customer, $upload, note: $explanation));
            } catch (FileUploadException $e) {
                $rejected[] = $e->getMessage();
            }
        }

        $this->reset('uploads');
        $this->exchange->refresh();

        if ($stored->isNotEmpty()) {
            $count = $stored->count();
            $this->dispatch('notify', message: $count.' file'.($count === 1 ? '' : 's').' uploaded.');
            $this->reset('explanation');
            $this->sendReadyMailOnce();
        }

        foreach ($rejected as $message) {
            $this->addError('uploads', $message);
        }
    }

    /**
     * Customer-initiated exchanges get their reference number + password email
     * after the first successful upload, not at verification. pull() makes it
     * fire exactly once.
     */
    private function sendReadyMailOnce(): void
    {
        $pending = session(self::READY_MAIL_SESSION_KEY);

        if (! is_array($pending) || ($pending['exchange_id'] ?? null) !== $this->exchange->id) {
            return;
        }

        session()->forget(self::READY_MAIL_SESSION_KEY);

        Mail::to($this->exchange->email)->send(new ExchangeReadyMail(
            $this->exchange,
            (string) $pending['password'],
            route('exchange.landing', $this->exchange),
        ));
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

    public function downloadCustomerFile(int $fileId, FileService $files): StreamedResponse
    {
        $file = $this->exchange->customerFiles()->findOrFail($fileId);

        $files->recordDownload($this->exchange, collect([$file]));

        return $files->downloadResponse($this->exchange, $file);
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

    public function render(): View
    {
        return view('livewire.exchange-workspace', [
            'radcalFiles' => $this->exchange->radcalFiles()->latest()->get(),
            'customerFiles' => $this->exchange->customerFiles()->latest()->get(),
        ]);
    }
}

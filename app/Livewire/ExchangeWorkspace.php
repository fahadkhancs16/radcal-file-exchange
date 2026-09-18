<?php

namespace App\Livewire;

use App\Enums\FileOwner;
use App\Exceptions\FileUploadException;
use App\Models\Exchange;
use App\Services\FileService;
use App\Services\UploadNotificationService;
use App\Support\ExchangeSession;
use Illuminate\Contracts\View\View;
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

    /** Which sidebar section is shown: overview | radcal | uploads | details. */
    public string $section = 'overview';

    /** @var array<int, TemporaryUploadedFile> */
    public array $uploads = [];

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

        foreach ($this->uploads as $upload) {
            try {
                // TemporaryUploadedFile extends UploadedFile — FileService takes it as-is.
                $stored->push($files->store($this->exchange, FileOwner::Customer, $upload));
            } catch (FileUploadException $e) {
                $rejected[] = $e->getMessage();
            }
        }

        $this->reset('uploads');
        $this->exchange->refresh();

        if ($stored->isNotEmpty()) {
            $count = $stored->count();
            $this->dispatch('notify', message: $count.' file'.($count === 1 ? '' : 's').' uploaded.');
            $notifications->notifyStaffOfCustomerUpload($this->exchange, $stored);
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

    public function render(): View
    {
        return view('livewire.exchange-workspace', [
            'radcalFiles' => $this->exchange->radcalFiles()->latest()->get(),
            'customerFiles' => $this->exchange->customerFiles()->latest()->get(),
        ]);
    }
}

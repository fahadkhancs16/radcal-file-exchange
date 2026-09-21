<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Enums\FileOwner;
use App\Exceptions\FileUploadException;
use App\Models\Exchange;
use App\Models\ExchangeFile;
use App\Models\User;
use App\Support\Filename;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Add / replace / delete files within an exchange, enforcing the per-exchange
 * size limit and keeping disk, database, activity log and expiration clock in
 * step. Both the customer UI and the admin panel call through here.
 */
class FileService
{
    public function __construct(
        private readonly ActivityRecorder $activity,
        private readonly ExpirationService $expiration,
    ) {}

    public function disk(): Filesystem
    {
        return Storage::disk((string) config('exchange.disk'));
    }

    /**
     * Store an uploaded file on one side of the exchange. If a file with the
     * same name (case-insensitive) already exists on that side it is replaced
     * in place, keeping the same row.
     */
    public function store(
        Exchange $exchange,
        FileOwner $owner,
        UploadedFile $upload,
        ?User $uploadedBy = null,
        ?string $note = null,
    ): ExchangeFile {
        $name = Filename::clean($upload->getClientOriginalName());
        $size = (int) $upload->getSize();

        if ($size <= 0) {
            throw FileUploadException::empty($name);
        }

        if ($size > $exchange->max_file_size) {
            throw FileUploadException::tooLarge($name, $exchange->max_file_size);
        }

        $existing = $this->findByName($exchange, $owner, $name);

        return DB::transaction(function () use ($exchange, $owner, $upload, $uploadedBy, $note, $name, $size, $existing) {
            $storedName = $existing !== null ? $existing->stored_name : Filename::storedName($name);

            $this->disk()->putFileAs($exchange->storageDirectory(), $upload, $storedName);

            $attributes = [
                'original_filename' => $name,
                'stored_name' => $storedName,
                'size' => $size,
                'mime' => $upload->getMimeType() ?: null,
                'uploaded_by' => $uploadedBy?->getKey(),
            ];

            if ($existing !== null) {
                $existing->update($attributes);
                $file = $existing;
                $action = ActivityAction::FileReplaced;
            } else {
                $file = $exchange->files()->create([
                    'owner' => $owner,
                    ...$attributes,
                ]);
                $action = ActivityAction::FileAdded;
            }

            $this->activity->record($exchange, $action, array_filter([
                'filename' => $name,
                'owner' => $owner->value,
                'size' => $size,
                'note' => filled($note) ? $note : null,
            ]));

            $this->expiration->bumpForFileActivity($exchange);

            return $file->refresh();
        });
    }

    /**
     * Delete selected files from one side of the exchange. Deleting a file does
     * NOT reset the expiration clock (spec §8).
     *
     * @param  array<int>  $fileIds
     * @return int number of files deleted
     */
    public function deleteSelected(Exchange $exchange, FileOwner $owner, array $fileIds): int
    {
        $files = $exchange->files()
            ->where('owner', $owner->value)
            ->whereIn('id', $fileIds)
            ->get();

        foreach ($files as $file) {
            DB::transaction(function () use ($exchange, $file) {
                $this->disk()->delete($exchange->storagePathFor($file->stored_name));
                $file->delete();

                $this->activity->record($exchange, ActivityAction::FileDeleted, [
                    'filename' => $file->original_filename,
                    'owner' => $file->owner->value,
                ]);
            });
        }

        return $files->count();
    }

    /**
     * Record that files were downloaded. Downloads never extend expiration.
     *
     * @param  Collection<int, ExchangeFile>  $files
     */
    public function recordDownload(Exchange $exchange, Collection $files): void
    {
        foreach ($files as $file) {
            $this->activity->record($exchange, ActivityAction::FileDownloaded, [
                'filename' => $file->original_filename,
                'owner' => $file->owner->value,
            ]);
        }
    }

    private function findByName(Exchange $exchange, FileOwner $owner, string $name): ?ExchangeFile
    {
        $key = Filename::comparisonKey($name);

        return $exchange->files()
            ->where('owner', $owner->value)
            ->whereRaw('LOWER(original_filename) = ?', [$key])
            ->first();
    }
}

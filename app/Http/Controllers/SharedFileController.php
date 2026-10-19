<?php

namespace App\Http\Controllers;

use App\Enums\FileOwner;
use App\Models\ExchangeFile;
use App\Services\FileService;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The public page behind a "download link" a customer emailed to someone
 * (or copied from their workspace). Anyone holding the unguessable token can
 * view and download that one file — no password. The link stops working when
 * the customer deletes the file or the exchange is disabled, expires or is
 * purged. Anything else 404s, so a token never confirms what it used to be.
 */
class SharedFileController extends Controller
{
    public function __construct(private readonly FileService $files) {}

    public function show(string $token): View
    {
        $file = $this->resolve($token);

        return view('pages.shared-file', [
            'file' => $file,
            'exchange' => $file->exchange,
            'previewable' => $file->isPreviewable(),
        ]);
    }

    /** Inline preview — only for types that are safe to render from our own origin. */
    public function view(string $token): StreamedResponse
    {
        $file = $this->resolve($token);

        abort_unless($file->isPreviewable(), 404);

        $this->files->recordLinkDownload($file->exchange, $file, inline: true);

        return $this->files->inlineResponse($file->exchange, $file);
    }

    public function download(string $token): StreamedResponse
    {
        $file = $this->resolve($token);

        $this->files->recordLinkDownload($file->exchange, $file);

        return $this->files->downloadResponse($file->exchange, $file);
    }

    private function resolve(string $token): ExchangeFile
    {
        $file = ExchangeFile::query()
            ->with('exchange')
            ->where('share_token', $token)
            ->where('owner', FileOwner::Customer->value)
            ->first();

        abort_unless(
            $file !== null
                && $file->exchange->isReachable()
                && $this->files->disk()->exists($file->exchange->storagePathFor($file->stored_name)),
            404,
        );

        return $file;
    }
}

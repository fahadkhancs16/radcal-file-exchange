<?php

namespace App\Http\Controllers;

use App\Models\Exchange;
use App\Models\ExchangeFile;
use App\Services\FileService;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The public page behind an exchange's share link. Anyone holding the
 * unguessable token (exchanges.share_token) sees every file the customer has
 * uploaded to that exchange and can view/download them — no password. Radcal's
 * own files are never listed. The link stops working when the exchange is
 * disabled, expires or is purged; a file disappears from it when the customer
 * deletes it. Anything else 404s, so a token never confirms what it used to be.
 */
class SharedExchangeController extends Controller
{
    public function __construct(private readonly FileService $files) {}

    public function show(string $token): View
    {
        $exchange = $this->resolveExchange($token);

        return view('pages.shared-exchange', [
            'exchange' => $exchange,
            'files' => $exchange->customerFiles()->orderBy('original_filename')->get(),
        ]);
    }

    /** Inline preview — only for types that are safe to render from our own origin. */
    public function view(string $token, int $file): StreamedResponse
    {
        $exchange = $this->resolveExchange($token);
        $file = $this->resolveFile($exchange, $file);

        abort_unless($file->isPreviewable(), 404);

        $this->files->recordLinkDownload($exchange, $file, inline: true);

        return $this->files->inlineResponse($exchange, $file);
    }

    public function download(string $token, int $file): StreamedResponse
    {
        $exchange = $this->resolveExchange($token);
        $file = $this->resolveFile($exchange, $file);

        $this->files->recordLinkDownload($exchange, $file);

        return $this->files->downloadResponse($exchange, $file);
    }

    private function resolveExchange(string $token): Exchange
    {
        $exchange = Exchange::query()->where('share_token', $token)->first();

        abort_unless($exchange !== null && $exchange->isReachable(), 404);

        return $exchange;
    }

    /** A customer-uploaded file of this exchange that is still on disk. */
    private function resolveFile(Exchange $exchange, int $id): ExchangeFile
    {
        $file = $exchange->customerFiles()->find($id);

        abort_unless(
            $file !== null && $this->files->disk()->exists($exchange->storagePathFor($file->stored_name)),
            404,
        );

        return $file;
    }
}

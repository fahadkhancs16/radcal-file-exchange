<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExchangeFile;
use App\Services\FileService;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a single file to a signed-in administrator. Registered inside the
 * Filament admin panel's own route group (see AdminPanelProvider), so it
 * inherits the panel's auth middleware — reaching it at all already implies
 * User::canAccessPanel() passed.
 */
class FileDownloadController extends Controller
{
    public function __invoke(ExchangeFile $file, FileService $files): StreamedResponse
    {
        $exchange = $file->exchange;

        $files->recordDownload($exchange, collect([$file]));

        return Response::streamDownload(
            fn () => print $files->disk()->get($exchange->storagePathFor($file->stored_name)),
            $file->original_filename,
        );
    }
}

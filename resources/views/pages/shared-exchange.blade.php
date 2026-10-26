<x-layouts.app :title="'Files from '.$exchange->customer_name">
    <h1>Files shared with you</h1>
    <p class="sub">
        {{ $exchange->customer_name }}@if ($exchange->company) ({{ $exchange->company }})@endif
        shared {{ $files->count() === 1 ? 'this file' : 'these files' }} with you.
    </p>

    <div class="panel">
        @if ($files->isEmpty())
            <p>No files are available right now.</p>
        @else
            <div class="table-wrap">
                <table class="files">
                    <thead><tr><th>Name</th><th>Size</th><th class="right"></th></tr></thead>
                    <tbody>
                    @foreach ($files as $file)
                        <tr>
                            <td style="word-break:break-word">{{ $file->original_filename }}</td>
                            <td class="size">{{ $file->humanSize() }}</td>
                            <td class="right">
                                <span style="display:inline-flex;gap:.4rem">
                                    <a class="btn sm" href="{{ route('share.download', [$exchange->share_token, $file->id]) }}">Download</a>
                                    @if ($file->isPreviewable())
                                        <a class="btn secondary sm" href="{{ route('share.view', [$exchange->share_token, $file->id]) }}" target="_blank" rel="noopener">View</a>
                                    @endif
                                </span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <p class="dz-hint" style="margin-top:1rem">
            This link works until {{ $exchange->expires_at->format('F j, Y') }}, when the files are permanently deleted.
        </p>
    </div>
</x-layouts.app>

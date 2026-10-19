<x-layouts.app :title="$file->original_filename">
    <h1>A file was shared with you</h1>
    <p class="sub">
        {{ $exchange->customer_name }}@if ($exchange->company) ({{ $exchange->company }})@endif
        shared this file with you.
    </p>

    <div class="panel">
        <div style="display:flex;align-items:center;gap:.8rem;margin-bottom:1rem;word-break:break-word">
            <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex:none"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            <div>
                <strong>{{ $file->original_filename }}</strong><br>
                <span class="dz-hint">{{ $file->humanSize() }} · shared {{ $file->updated_at->format('F j, Y') }}</span>
            </div>
        </div>

        @if ($previewable && str_starts_with((string) $file->mime, 'image/'))
            <img src="{{ route('share.view', $file->share_token) }}" alt="{{ $file->original_filename }}"
                 style="max-width:100%;height:auto;border-radius:6px;margin-bottom:1rem">
        @endif

        <div class="actions">
            <a class="btn" href="{{ route('share.download', $file->share_token) }}">Download</a>
            @if ($previewable)
                <a class="btn secondary" href="{{ route('share.view', $file->share_token) }}" target="_blank" rel="noopener">View</a>
            @endif
        </div>

        <p class="dz-hint" style="margin-top:1rem">
            This link works until {{ $exchange->expires_at->format('F j, Y') }}, when the file is permanently deleted.
        </p>
    </div>
</x-layouts.app>

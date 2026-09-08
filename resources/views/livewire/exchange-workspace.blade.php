<div x-data="{ toast: null }"
     x-on:notify.window="toast = $event.detail.message; setTimeout(() => toast = null, 3500)">

    @if ($firstVisit)
        <div class="alert success">
            Your files reached Radcal. Your reference number is
            <strong class="ref-number">{{ $exchange->code }}</strong>. We emailed a link and password so you can return to this exchange.
        </div>
    @endif

    <h1>Your file exchange</h1>
    <p class="lede">Reference <strong class="ref-number">{{ $exchange->code }}</strong> &middot; {{ $exchange->customer_name }}@if ($exchange->company), {{ $exchange->company }}@endif</p>

    <div class="meta-row">
        <div><div class="k">Expires</div><div class="v">{{ $exchange->expires_at->format('F j, Y') }}</div></div>
        <div><div class="k">Per-file limit</div><div class="v">{{ number_format($exchange->max_file_size / 1048576) }} MB</div></div>
        <div><div class="k">Your files</div><div class="v">{{ $customerFiles->count() }}</div></div>
    </div>

    <div class="notice strong">
        Temporary storage — this exchange and all files will be permanently deleted on
        {{ $exchange->expires_at->format('F j, Y') }}. Save anything you need to keep before this date.
    </div>

    {{-- Files from Radcal --------------------------------------------------- --}}
    <div class="file-group">
        <h2>Files from Radcal <span class="count">({{ $radcalFiles->count() }})</span></h2>
        <table class="files">
            <thead><tr><th>Name</th><th>Size</th><th></th></tr></thead>
            <tbody>
            @forelse ($radcalFiles as $file)
                <tr>
                    <td>{{ $file->original_filename }}</td>
                    <td class="size">{{ $file->humanSize() }}</td>
                    <td style="text-align:right">
                        <button type="button" class="btn secondary"
                                wire:click="downloadRadcalFile({{ $file->id }})">Download</button>
                    </td>
                </tr>
            @empty
                <tr class="empty"><td colspan="3">Radcal has not added any files yet.</td></tr>
            @endforelse
            </tbody>
        </table>
        @if ($radcalFiles->isNotEmpty())
            <p class="lede" style="font-size:.82rem;margin-top:.5rem">
                These files are read-only. Downloading does not extend the expiration date.
            </p>
        @endif
    </div>

    {{-- Files you uploaded ------------------------------------------------- --}}
    <div class="file-group">
        <h2>Files you uploaded <span class="count">({{ $customerFiles->count() }})</span></h2>

        <table class="files">
            <thead><tr><th style="width:1.5rem"></th><th>Name</th><th>Size</th><th>Uploaded</th></tr></thead>
            <tbody>
            @forelse ($customerFiles as $file)
                <tr>
                    <td><input type="checkbox" value="{{ $file->id }}" wire:model="selected"></td>
                    <td>{{ $file->original_filename }}</td>
                    <td class="size">{{ $file->humanSize() }}</td>
                    <td class="size">{{ $file->created_at->diffForHumans() }}</td>
                </tr>
            @empty
                <tr class="empty"><td colspan="4">You have not uploaded any files yet.</td></tr>
            @endforelse
            </tbody>
        </table>

        @if ($customerFiles->isNotEmpty())
            <div class="actions">
                <button type="button" class="btn danger"
                        wire:click="deleteSelected"
                        wire:confirm="Delete the selected files? This cannot be undone."
                        @disabled(count($selected) === 0)>
                    Delete selected
                </button>
                <span class="lede" style="font-size:.82rem;margin:0">
                    Uploading a file with the same name replaces the existing one.
                </span>
            </div>
        @endif

        <div class="dropzone">
            <label for="uploads"><strong>Add files</strong> — up to {{ number_format($exchange->max_file_size / 1048576) }} MB each</label>
            <input type="file" id="uploads" wire:model="uploads" multiple>
            <div wire:loading wire:target="uploads" class="lede" style="font-size:.82rem;margin:.4rem 0 0">Uploading…</div>
            @error('uploads') <p class="error-text">{{ $message }}</p> @enderror
            @error('uploads.*') <p class="error-text">{{ $message }}</p> @enderror

            @if (count($uploads) > 0)
                <ul style="text-align:left;margin:.8rem 0 0;font-size:.88rem">
                    @foreach ($uploads as $u)
                        <li>{{ $u->getClientOriginalName() }}</li>
                    @endforeach
                </ul>
                <div class="actions" style="justify-content:center">
                    <button type="button" class="btn" wire:click="upload"
                            wire:loading.attr="disabled" wire:target="upload">Upload {{ count($uploads) }} file{{ count($uploads) === 1 ? '' : 's' }}</button>
                    <button type="button" class="btn link" wire:click="$set('uploads', [])">Clear</button>
                </div>
            @endif
        </div>
    </div>

    <p class="lede" style="margin-top:2rem;font-size:.86rem">
        After uploading, contact your Radcal representative and give them your reference number
        <strong class="ref-number">{{ $exchange->code }}</strong>. Uploading files does not notify Radcal on its own.
    </p>

    <template x-if="toast">
        <div class="toast" x-text="toast"></div>
    </template>
</div>

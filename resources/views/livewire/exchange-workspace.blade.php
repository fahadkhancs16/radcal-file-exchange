@php
    $titles = [
        'overview' => 'Overview',
        'radcal'   => 'Files from Radcal',
        'uploads'  => 'Your uploaded files',
        'details'  => 'Exchange details',
    ];
    $expiresOn = $exchange->expires_at->format('F j, Y');
    $daysLeft  = $exchange->expires_at->isFuture() ? (int) ceil(now()->diffInDays($exchange->expires_at)) : 0;
    $soon      = $exchange->isExpiringSoon();
@endphp

<div class="shell"
     x-data="{ toast: null, t: null }"
     x-on:notify.window="toast = $event.detail.message; clearTimeout(t); t = setTimeout(() => toast = null, 3500)">

    {{-- ============================ Sidebar ============================ --}}
    <aside class="sidebar">
        <div class="sidebar-brand">
            <img src="{{ asset('images/logo-iba-radcal.png') }}" alt="iba Radcal">
        </div>

        <nav class="sidebar-nav">
            <span class="nav-heading">Exchange {{ $exchange->code }}</span>

            <button type="button" class="nav-item @if($section==='overview') is-active @endif"
                    wire:click="showSection('overview')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>
                Overview
            </button>

            <button type="button" class="nav-item @if($section==='radcal') is-active @endif"
                    wire:click="showSection('radcal')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Files from Radcal
                <span class="tag">{{ $radcalFiles->count() }}</span>
            </button>

            <button type="button" class="nav-item @if($section==='uploads') is-active @endif"
                    wire:click="showSection('uploads')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                Your uploaded files
                <span class="tag">{{ $customerFiles->count() }}</span>
            </button>

            <button type="button" class="nav-item @if($section==='details') is-active @endif"
                    wire:click="showSection('details')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
                Exchange details
            </button>
        </nav>

        <div class="sidebar-foot">
            Expires
            <span class="exp">{{ $expiresOn }}</span>
            <form wire:submit="leave">
                <button type="submit" class="btn link" style="padding-left:0">Leave exchange</button>
            </form>
        </div>
    </aside>

    {{-- ============================= Main ============================= --}}
    <div class="main">
        <header class="topbar">
            <h1>{{ $titles[$section] }}</h1>
            <span class="spacer"></span>
            <span class="ref-chip">Reference <b>{{ $exchange->code }}</b></span>
            <span class="status-pill {{ $soon ? 'warn' : 'active' }}">
                {{ $soon ? 'Expires '.$exchange->expires_at->diffForHumans() : 'Active' }}
            </span>
        </header>

        <div class="content">
            @if ($firstVisit)
                <div class="alert success">
                    Your files reached Radcal. Your reference number is <strong>{{ $exchange->code }}</strong> —
                    we emailed a link and password so you can return to this exchange later.
                </div>
            @endif

            {{-- ------------------------ Overview ------------------------ --}}
            @if ($section === 'overview')
                <div class="stats">
                    <div class="stat"><div class="k">Your files</div><div class="v">{{ $customerFiles->count() }}</div></div>
                    <div class="stat"><div class="k">Files from Radcal</div><div class="v">{{ $radcalFiles->count() }}</div></div>
                    <div class="stat"><div class="k">Expires in</div><div class="v">{{ $daysLeft }} <small>day{{ $daysLeft === 1 ? '' : 's' }}</small></div></div>
                    <div class="stat"><div class="k">Per-file limit</div><div class="v">{{ number_format($exchange->max_file_size / 1048576) }} <small>MB</small></div></div>
                </div>

                <section class="notice">
                    <strong>Temporary storage.</strong> This exchange and every file in it are permanently
                    deleted on {{ $expiresOn }}. Save anything you need to keep before that date — Radcal keeps
                    nothing here afterwards.
                </section>

                <section class="card">
                    <div class="card-head"><h2>What to do next</h2></div>
                    <div class="card-body">
                        <ol style="margin:0;padding-left:1.1rem;color:var(--ink-2);display:flex;flex-direction:column;gap:.4rem">
                            <li>Upload the files you need to send from <strong>Your uploaded files</strong>.</li>
                            <li>Contact your Radcal representative directly and give them reference
                                <strong>{{ $exchange->code }}</strong> — uploading does not notify Radcal on its own.</li>
                            <li>Check <strong>Files from Radcal</strong> for anything sent back to you.</li>
                        </ol>
                    </div>
                </section>
            @endif

            {{-- --------------------- Files from Radcal ------------------ --}}
            @if ($section === 'radcal')
                <section class="card">
                    <div class="card-head">
                        <h2>Files from Radcal</h2>
                        <span class="count">{{ $radcalFiles->count() }} file{{ $radcalFiles->count() === 1 ? '' : 's' }}</span>
                    </div>
                    <div class="table-wrap">
                        <table class="files">
                            <thead><tr><th>Name</th><th>Size</th><th class="right">Added</th><th class="right"></th></tr></thead>
                            <tbody>
                            @forelse ($radcalFiles as $file)
                                <tr wire:key="r-{{ $file->id }}">
                                    <td><span class="fname">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                        {{ $file->original_filename }}
                                    </span></td>
                                    <td class="size">{{ $file->humanSize() }}</td>
                                    <td class="size right">{{ $file->created_at->diffForHumans() }}</td>
                                    <td class="right">
                                        <button type="button" class="btn secondary sm" wire:click="downloadRadcalFile({{ $file->id }})">
                                            Download
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr class="empty-row"><td colspan="4">Radcal has not added any files yet.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($radcalFiles->isNotEmpty())
                        <div class="card-note">Read-only. Downloading a file does not extend the expiration date.</div>
                    @endif
                </section>
            @endif

            {{-- --------------------- Your uploaded files ---------------- --}}
            @if ($section === 'uploads')
                <section class="card" wire:key="upload-card">
                    <div class="card-head"><h2>Add files</h2></div>
                    <div class="card-body">
                        <div class="dropzone-outer"
                             x-data="{ over: false }"
                             x-on:dragover.prevent="over = true"
                             x-on:dragleave.prevent="over = false"
                             x-on:drop.prevent="over = false">
                            <div class="dropzone" :class="{ 'is-over': over }">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                <p class="dz-title">Drop files here or click to browse</p>
                                <p class="dz-hint">Up to {{ number_format($exchange->max_file_size / 1048576) }} MB per file. Same filename replaces an existing file.</p>
                            </div>
                            <input type="file" wire:model="uploads" multiple>
                        </div>

                        <div wire:loading wire:target="uploads" class="dz-hint" style="margin-top:.7rem">
                            <svg class="spin" style="width:13px;height:13px;vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                            Transferring…
                        </div>

                        @error('uploads') <p class="error-text">{{ $message }}</p> @enderror
                        @error('uploads.*') <p class="error-text">{{ $message }}</p> @enderror

                        @if (count($uploads) > 0)
                            <ul class="pending-list">
                                @foreach ($uploads as $u)
                                    <li>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                        {{ $u->getClientOriginalName() }}
                                    </li>
                                @endforeach
                            </ul>
                            <div class="actions">
                                <button type="button" class="btn" wire:click="saveUploads"
                                        wire:loading.attr="disabled" wire:target="saveUploads">
                                    Upload {{ count($uploads) }} file{{ count($uploads) === 1 ? '' : 's' }}
                                </button>
                                <button type="button" class="btn link" wire:click="$set('uploads', [])">Clear</button>
                            </div>
                        @endif
                    </div>
                </section>

                <section class="card">
                    <div class="card-head">
                        <h2>Your uploaded files</h2>
                        <span class="count">{{ $customerFiles->count() }} file{{ $customerFiles->count() === 1 ? '' : 's' }}</span>
                        <span class="spacer"></span>
                        @if ($customerFiles->isNotEmpty())
                            <button type="button" class="btn danger sm"
                                    wire:click="deleteSelected"
                                    wire:confirm="Delete the selected files? This cannot be undone."
                                    @disabled(count($selected) === 0)>
                                Delete selected @if(count($selected)) ({{ count($selected) }}) @endif
                            </button>
                        @endif
                    </div>
                    <div class="table-wrap">
                        <table class="files">
                            <thead><tr><th class="check"></th><th>Name</th><th>Size</th><th class="right">Uploaded</th></tr></thead>
                            <tbody>
                            @forelse ($customerFiles as $file)
                                <tr wire:key="c-{{ $file->id }}">
                                    <td class="check"><input type="checkbox" value="{{ $file->id }}" wire:model.live="selected"></td>
                                    <td><span class="fname">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                        {{ $file->original_filename }}
                                    </span></td>
                                    <td class="size">{{ $file->humanSize() }}</td>
                                    <td class="size right">{{ $file->created_at->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr class="empty-row"><td colspan="4">You have not uploaded any files yet.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            {{-- --------------------- Exchange details ------------------- --}}
            @if ($section === 'details')
                <section class="card">
                    <div class="card-head"><h2>Exchange details</h2></div>
                    <div class="card-body">
                        <dl class="dl">
                            <div><dt>Reference / code</dt><dd><span class="ref-number">{{ $exchange->code }}</span></dd></div>
                            <div><dt>Contact name</dt><dd>{{ $exchange->customer_name }}</dd></div>
                            @if ($exchange->company)
                                <div><dt>Company</dt><dd>{{ $exchange->company }}</dd></div>
                            @endif
                            <div><dt>Email</dt><dd>{{ $exchange->email }}</dd></div>
                            @if ($exchange->description)
                                <div><dt>Description</dt><dd style="max-width:22rem">{{ $exchange->description }}</dd></div>
                            @endif
                            <div><dt>Created</dt><dd>{{ $exchange->created_at->format('F j, Y') }}</dd></div>
                            <div><dt>Expires</dt><dd>{{ $expiresOn }}</dd></div>
                            <div><dt>Per-file limit</dt><dd>{{ number_format($exchange->max_file_size / 1048576) }} MB</dd></div>
                            <div><dt>Status</dt><dd>{{ $exchange->status()->label() }}</dd></div>
                        </dl>
                    </div>
                    <div class="card-note">
                        Need the code, password, size limit or expiry date changed? Contact your Radcal representative —
                        these are managed by Radcal.
                    </div>
                </section>
            @endif
        </div>
    </div>

    <template x-if="toast"><div class="toast" x-text="toast"></div></template>
</div>

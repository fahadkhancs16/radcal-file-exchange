@php
    $titles = [
        'overview' => 'Overview',
        'radcal'   => 'Files from Radcal',
        'uploads'  => 'Your uploaded files',
        'team' => 'Team',
        'colleague_files' => 'Files from Colleague',
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

            <button type="button" class="nav-item @if($section==='team') is-active @endif"
                    wire:click="showSection('team')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Team
                <span class="tag">{{ $others->count() }}</span>
            </button>

            <button type="button" class="nav-item @if($section==='colleague_files') is-active @endif"
                    wire:click="showSection('colleague_files')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v13H5.2L4 18.2z"/><polyline points="8 9 12 13 16 9"/></svg>
                Files from Colleague
                <span class="tag">{{ $colleagueFiles->count() }}</span>
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
                             x-data="{
                                 over: false,
                                 handleDrop(e) {
                                     this.over = false;
                                     if (! e.dataTransfer || e.dataTransfer.files.length === 0) return;
                                     // Forward the dropped files to the real file input ourselves —
                                     // preventDefault() on `drop` (needed to stop the browser from
                                     // navigating to the file) also cancels the input's own native
                                     // drop-to-select behaviour, so nothing would reach Livewire otherwise.
                                     this.$refs.uploadsInput.files = e.dataTransfer.files;
                                     this.$refs.uploadsInput.dispatchEvent(new Event('change', { bubbles: true }));
                                 },
                             }"
                             x-on:dragover.prevent="over = true"
                             x-on:dragleave.prevent="over = false"
                             x-on:drop.prevent="handleDrop($event)">
                            <div class="dropzone" :class="{ 'is-over': over }">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                <p class="dz-title">Drop files here or click to browse</p>
                                <p class="dz-hint">Up to {{ number_format($exchange->max_file_size / 1048576) }} MB per file. Same filename replaces an existing file.</p>
                            </div>
                            <input type="file" wire:model="uploads" multiple x-ref="uploadsInput">
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

                            <div class="field" style="margin-top:1rem">
                                <label for="explanation">Explanation <span class="hint">(required, included in the email to Radcal)</span></label>
                                <textarea id="explanation" wire:model="explanation" rows="3" placeholder="What are these files for?" required></textarea>
                                @error('explanation') <p class="error-text">{{ $message }}</p> @enderror
                            </div>

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
                            <thead><tr><th class="check"></th><th>Name</th><th>Size</th><th>Uploaded by</th><th class="right">Uploaded</th><th class="right"></th></tr></thead>
                            <tbody>
                            @forelse ($customerFiles as $file)
                                <tr wire:key="c-{{ $file->id }}">
                                    <td class="check"><input type="checkbox" value="{{ $file->id }}" wire:model.live="selected"></td>
                                    <td><span class="fname">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                        {{ $file->original_filename }}
                                    </span></td>
                                    <td class="size">{{ $file->humanSize() }}</td>
                                    <td class="size">{{ $file->uploaded_by_email ?? $exchange->email }}</td>
                                    <td class="size right">{{ $file->created_at->diffForHumans() }}</td>
                                    <td class="right">
                                        <button type="button" class="btn secondary sm" wire:click="downloadCustomerFile({{ $file->id }})">
                                            Download
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr class="empty-row"><td colspan="6">You have not uploaded any files yet.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            {{-- --------------------------- Team ------------------------- --}}
            @if ($section === 'team')
                <section class="card">
                    <div class="card-head">
                        <h2>Your team</h2>
                        <span class="count">{{ $others->count() }} colleague{{ $others->count() === 1 ? '' : 's' }}</span>
                        <span class="spacer"></span>
                        <button type="button" class="btn sm" wire:click="chooseRecipient('__new__')">Invite a new colleague</button>
                    </div>
                    <div class="table-wrap">
                        <table class="files">
                            <thead><tr><th>Name</th><th>Company</th><th>Email</th><th>Reference</th><th class="right"></th></tr></thead>
                            <tbody>
                            @forelse ($others as $person)
                                <tr wire:key="p-{{ $person['key'] }}" @if($recipient === $person['key']) style="background:var(--surface-2, #f4f8ef)" @endif>
                                    <td>{{ $person['name'] ?? '—' }} @if($person['own']) <span class="tag">Your exchange</span> @endif</td>
                                    <td class="size">{{ $person['company'] ?? '—' }}</td>
                                    <td class="size">{{ $person['email'] }}</td>
                                    <td class="size">{{ $person['code'] }}</td>
                                    <td class="right">
                                        <button type="button" class="btn secondary sm" wire:click="chooseRecipient('{{ $person['key'] }}')">Send files</button>
                                        @if ($this->isContact() && $person['member_id'])
                                            <button type="button" class="btn danger sm"
                                                    wire:click="removeColleague({{ $person['member_id'] }})"
                                                    wire:confirm="Remove this person's access to the exchange?">Remove</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr class="empty-row"><td colspan="5">No other customers yet — invite a colleague to send them files.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="card-note">
                        Choose a colleague to send files to them directly. Only you and the recipient can see
                        what you send; Radcal staff can see everything in an exchange.
                    </div>
                </section>

                @if ($recipient !== '')
                    <section class="card" wire:key="team-send-card">
                        <div class="card-head">
                            <h2>
                                @if ($recipient === '__new__')
                                    Invite a new colleague and send files
                                @else
                                    Send files to {{ $recipientPerson['name'] ?? $recipientPerson['email'] ?? '' }} @if($recipientPerson) ({{ $recipientPerson['email'] }}, {{ $recipientPerson['code'] }}) @endif
                                @endif
                            </h2>
                        </div>
                        <div class="card-body">
                            @if ($recipient === '__new__')
                                <div class="field">
                                    <label for="newEmail">Colleague's email</label>
                                    <input id="newEmail" type="email" wire:model="newEmail" placeholder="name@company.com">
                                    @error('newEmail') <p class="error-text">{{ $message }}</p> @enderror
                                </div>
                                <div class="field">
                                    <label for="newName">Name <span class="hint">(optional)</span></label>
                                    <input id="newName" type="text" wire:model="newName">
                                    @error('newName') <p class="error-text">{{ $message }}</p> @enderror
                                </div>
                                <p class="dz-hint" style="margin-bottom:1rem">
                                    They receive an email with the exchange link and their own password.
                                </p>
                            @endif
                            @error('recipient') <p class="error-text">{{ $message }}</p> @enderror

                            <div class="dropzone-outer"
                                 x-data="{
                                     over: false,
                                     handleDrop(e) {
                                         this.over = false;
                                         if (! e.dataTransfer || e.dataTransfer.files.length === 0) return;
                                         // See the Radcal upload dropzone: drop must be forwarded to the input by hand.
                                         this.$refs.teamInput.files = e.dataTransfer.files;
                                         this.$refs.teamInput.dispatchEvent(new Event('change', { bubbles: true }));
                                     },
                                 }"
                                 x-on:dragover.prevent="over = true"
                                 x-on:dragleave.prevent="over = false"
                                 x-on:drop.prevent="handleDrop($event)">
                                <div class="dropzone" :class="{ 'is-over': over }">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                    <p class="dz-title">Drop files here or click to browse</p>
                                    <p class="dz-hint">Up to {{ number_format($exchange->max_file_size / 1048576) }} MB per file. Same filename to the same person replaces the earlier file.</p>
                                </div>
                                <input type="file" wire:model="teamUploads" multiple x-ref="teamInput">
                            </div>

                            <div wire:loading wire:target="teamUploads" class="dz-hint" style="margin-top:.7rem">
                                <svg class="spin" style="width:13px;height:13px;vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                                Transferring…
                            </div>

                            @error('teamUploads') <p class="error-text">{{ $message }}</p> @enderror
                            @error('teamUploads.*') <p class="error-text">{{ $message }}</p> @enderror

                            @if (count($teamUploads) > 0)
                                <ul class="pending-list">
                                    @foreach ($teamUploads as $u)
                                        <li>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                            {{ $u->getClientOriginalName() }}
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            <div class="field" style="margin-top:1rem">
                                <label for="teamExplanation">Explanation <span class="hint">(required, included in the email to your colleague)</span></label>
                                <textarea id="teamExplanation" wire:model="teamExplanation" rows="3" placeholder="What are these files for?"></textarea>
                                @error('teamExplanation') <p class="error-text">{{ $message }}</p> @enderror
                            </div>

                            <div class="actions">
                                <button type="button" class="btn" wire:click="sendToColleague"
                                        wire:loading.attr="disabled" wire:target="sendToColleague,teamUploads">
                                    @if ($recipient === '__new__') Invite and send @else Send @endif
                                    {{ count($teamUploads) }} file{{ count($teamUploads) === 1 ? '' : 's' }}
                                </button>
                                <button type="button" class="btn link" wire:click="chooseRecipient('')">Cancel</button>
                            </div>
                        </div>
                    </section>
                @endif
            @endif

            {{-- ------------------- Files from Colleague ----------------- --}}
            @if ($section === 'colleague_files')
                <section class="card">
                    <div class="card-head">
                        <h2>Files from Colleague</h2>
                        <span class="count">{{ $colleagueFiles->count() }} file{{ $colleagueFiles->count() === 1 ? '' : 's' }}</span>
                        <span class="spacer"></span>
                        @if ($colleagueFiles->where('uploaded_by_email', $me)->isNotEmpty())
                            <button type="button" class="btn danger sm"
                                    wire:click="deleteSentColleagueFiles"
                                    wire:confirm="Delete the selected files you sent? The recipient will no longer be able to download them."
                                    @disabled(count($selected) === 0)>
                                Delete selected @if(count($selected)) ({{ count($selected) }}) @endif
                            </button>
                        @endif
                    </div>
                    <div class="table-wrap">
                        <table class="files">
                            <thead><tr><th class="check"></th><th>Name</th><th>Size</th><th>Direction</th><th>With</th><th class="right">Date</th><th class="right"></th></tr></thead>
                            <tbody>
                            @forelse ($colleagueFiles as $file)
                                @php $sent = $file->exchange_id === $exchange->id && $file->uploaded_by_email === $me; @endphp
                                <tr wire:key="cf-{{ $file->id }}">
                                    <td class="check">
                                        @if ($sent)<input type="checkbox" value="{{ $file->id }}" wire:model.live="selected">@endif
                                    </td>
                                    <td><span class="fname">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                        {{ $file->original_filename }}
                                    </span></td>
                                    <td class="size">{{ $file->humanSize() }}</td>
                                    <td><span class="tag">{{ $sent ? 'Sent' : 'Received' }}</span></td>
                                    <td class="size">{{ $sent ? $file->recipient_email : $file->uploaded_by_email }}</td>
                                    <td class="size right">{{ $file->created_at->diffForHumans() }}</td>
                                    <td class="right">
                                        <button type="button" class="btn secondary sm" wire:click="downloadColleagueFile({{ $file->id }})">Download</button>
                                    </td>
                                </tr>
                            @empty
                                <tr class="empty-row"><td colspan="7">No files sent or received yet. Use <strong>Team</strong> to send files to a colleague.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($colleagueFiles->isNotEmpty())
                        <div class="card-note">Downloading a file does not extend the expiration date.</div>
                    @endif
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

<x-layouts.app>
    <h1>File Exchange</h1>
    <p class="sub">A secure way to move files that are too large for email between you and Radcal. Files are held temporarily and removed automatically.</p>

    <div class="choice-grid">
        <a class="choice" href="{{ route('send.start') }}">
            <span class="ic">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            </span>
            <div>
                <h2>Send files to Radcal</h2>
                <p>You need to send us files and have not been given an exchange link.</p>
            </div>
        </a>
        <a class="choice" href="{{ route('access') }}">
            <span class="ic">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </span>
            <div>
                <h2>Access a file exchange</h2>
                <p>Radcal gave you an exchange link or code and a password.</p>
            </div>
        </a>
    </div>
</x-layouts.app>

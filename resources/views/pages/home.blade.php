<x-layouts.app>
    <h1>Radcal File Exchange</h1>
    <p class="lede">A secure way to move files that are too large for email between you and Radcal. Files are held temporarily and removed automatically.</p>

    <div class="choice-grid">
        <a class="choice" href="{{ route('send.start') }}">
            <h2>Send files to Radcal</h2>
            <p>You need to send us files and have not been given an exchange link.</p>
        </a>
        <a class="choice" href="{{ route('access') }}">
            <h2>Access a file exchange</h2>
            <p>Radcal gave you an exchange link or code and a password.</p>
        </a>
    </div>
</x-layouts.app>

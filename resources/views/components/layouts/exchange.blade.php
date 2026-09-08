{{-- Full-page layout for the Livewire ExchangeWorkspace component. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? 'Your File Exchange' }} — Radcal File Exchange</title>
    <link rel="stylesheet" href="{{ asset('css/radcal.css') }}">
    @livewireStyles
</head>
<body>
<div class="site">
    <header class="site-header">
        <div class="inner">
            <a href="{{ route('home') }}" class="wordmark">Radcal <span>File Exchange</span></a>
            <span class="tag">Secure temporary transfer</span>
        </div>
    </header>

    <main class="shell wide">
        {{ $slot }}
    </main>

    <footer class="site-footer">
        <div class="inner">
            Temporary transfer only. This exchange and its files are deleted on their expiration date.
        </div>
    </footer>
</div>
@livewireScripts
</body>
</html>

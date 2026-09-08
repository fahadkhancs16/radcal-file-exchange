@props(['wide' => false, 'title' => null])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' — ' : '' }}Radcal File Exchange</title>
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

    <main class="shell {{ $wide ? 'wide' : '' }}">
        @if (session('status'))
            <div class="alert success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert error">{{ session('error') }}</div>
        @endif

        {{ $slot }}
    </main>

    <footer class="site-footer">
        <div class="inner">
            Files sent through this system are held temporarily and deleted automatically. Save anything you need to keep before its exchange expires.
        </div>
    </footer>
</div>
@livewireScripts
</body>
</html>

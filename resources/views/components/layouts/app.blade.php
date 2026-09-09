@props(['title' => null, 'wide' => false])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' — ' : '' }}Radcal File Exchange</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/radcal.css') }}">
    @livewireStyles
</head>
<body>
<div class="auth">
    <a href="{{ route('home') }}" class="auth-brand">
        <img src="{{ asset('images/logo-iba-radcal.png') }}" alt="iba Radcal">
    </a>

    <div class="auth-inner {{ $wide ? 'lg' : '' }}">
        @if (session('status'))
            <div class="alert success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert error">{{ session('error') }}</div>
        @endif

        {{ $slot }}
    </div>

    <p class="auth-foot">
        Files sent through this system are held temporarily and removed automatically once their exchange expires.
    </p>
</div>
@livewireScripts
</body>
</html>

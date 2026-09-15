@props(['title' => null])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' — ' : '' }}Staff Access</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/radcal.css') }}">
</head>
<body>
<div class="site">
    <header class="site-header">
        
    </header>

    <main class="" style="padding: 30px">
        <div class="inner">
            <span class="wordmark">Radcal <span>Staff Access</span></span>
            <form method="POST" action="{{ route('admin-manager.logout') }}" style="margin-left:auto">
                @csrf
                <button type="submit" class="btn link">Lock</button>
            </form>
        </div>
        @if (session('status'))
            <div class="alert success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert error">{{ session('error') }}</div>
        @endif

        {{ $slot }}
        <div class="inner">Private URL, not linked from anywhere in the app. Do not share it.</div>
    </main>

    <footer class="site-footer">
        
    </footer>
</div>
</body>
</html>

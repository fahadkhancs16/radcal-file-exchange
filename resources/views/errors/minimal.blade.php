<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') — Radcal File Exchange</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/radcal.css') }}">
</head>
<body>
<div class="auth">
    <a href="{{ url('/') }}" class="auth-brand">
        <img src="{{ asset('images/logo-iba-radcal.png') }}" alt="iba Radcal">
    </a>
    <div class="auth-inner">
        <h1>@yield('title')</h1>
        <p class="sub">@yield('message')</p>
        <a href="{{ url('/') }}" class="btn">Back to File Exchange</a>
    </div>
</div>
</body>
</html>

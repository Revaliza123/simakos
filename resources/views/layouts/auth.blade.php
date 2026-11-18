<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>@yield('title') · SIMAKOS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-page">
    <main class="auth-card">
        <a class="brand auth-brand" href="{{ route('login') }}">
            <span class="brand-mark"><span class="material-symbols-outlined">apartment</span></span>
            <span><strong>SIMAKOS</strong><small>SISTEM MANAJEMEN KOS</small></span>
        </a>
        @if (session('status'))
            <div class="flash-message">{{ session('status') }}</div>
        @endif
        @yield('content')
    </main>
</body>
</html>

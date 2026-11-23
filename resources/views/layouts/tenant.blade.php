<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>@yield('title', 'Beranda Penghuni') · SIMAKOS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="app-shell tenant-shell">
        @include('components.tenant-sidebar')
        <div class="content-shell">
            <header class="topbar tenant-topbar">
                <button class="mobile-menu" type="button" aria-label="Buka menu" data-menu-toggle><span class="material-symbols-outlined">menu</span></button>
                <div class="tenant-room-chip"><span class="material-symbols-outlined">meeting_room</span>Kamar {{ $tenant['room'] }} · {{ $tenant['floor'] }}</div>
                <div class="topbar-actions"><span class="tenant-active-chip"><i></i>Penghuni</span><div class="profile"><span class="profile-copy"><strong>{{ $tenant['name'] }}</strong><small>{{ $tenant['email'] }}</small></span><span class="avatar">{{ strtoupper(substr($tenant['name'], 0, 1)) }}</span><form method="POST" action="{{ route('logout') }}">@csrf<button class="icon-button" type="submit" title="Keluar" aria-label="Keluar"><span class="material-symbols-outlined">logout</span></button></form></div></div>
            </header>
            <main class="page-content"><div class="page-container">
                @if (session('status'))<div class="flash-message">{{ session('status') }}</div>@endif
                @include('components.form-errors')
                @yield('content')
            </div></main>
        </div>
    </div>
</body>
</html>

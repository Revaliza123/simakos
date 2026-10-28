<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beranda Penghuni · SIMAKOS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="app-shell tenant-shell">
        @include('components.tenant-sidebar')
        <div class="content-shell">
            <header class="topbar tenant-topbar">
                <div class="tenant-room-chip"><span class="material-symbols-outlined">meeting_room</span>Kamar {{ $tenant['room'] }} · {{ $tenant['floor'] }}</div>
                <div class="topbar-actions"><span class="tenant-active-chip"><i></i>Penghuni Aktif</span><button class="icon-button" type="button" aria-label="Notifikasi"><span class="material-symbols-outlined">notifications</span></button><div class="profile"><span class="profile-copy"><strong>{{ $tenant['name'] }}</strong><small>{{ $tenant['email'] }}</small></span><span class="avatar">DA</span><a class="icon-button" href="{{ route('logout') }}" title="Keluar"><span class="material-symbols-outlined">logout</span></a></div></div>
            </header>
            <main class="page-content"><div class="page-container">@if (session('status'))<div class="flash-message">{{ session('status') }}</div>@endif @yield('content')</div></main>
        </div>
    </div>
</body>
</html>

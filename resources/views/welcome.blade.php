<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="SIMAKOS — katalog kamar kos kosong di kawasan Bintaro beserta tipe dan harga sewa.">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>Kamar Kos Tersedia · SIMAKOS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="landing-page">
    <header class="landing-nav">
        <a class="brand" href="{{ route('home') }}"><span class="brand-mark"><span class="material-symbols-outlined">apartment</span></span><span><strong>SIMAKOS</strong><small>SISTEM MANAJEMEN KOS</small></span></a>
        <nav>
            @auth
                <a class="button button-primary" href="{{ route('dashboard') }}">Buka Dashboard</a>
            @else
                <a class="button button-primary" href="{{ route('login') }}"><span class="material-symbols-outlined">login</span>Masuk</a>
            @endauth
        </nav>
    </header>

    <main class="landing-main">
        <section class="landing-hero">
            <span class="eyebrow">KOS BINTARO · MAHASISWA & PEKERJA</span>
            <h1>Temukan kamar kos yang siap dihuni</h1>
            <p>Lihat ketersediaan kamar secara langsung, bandingkan tipe dan harga sewa, lalu hubungi pengelola untuk proses check-in.</p>
            <div class="landing-stats">
                <div><strong>{{ $rooms->count() }}</strong><small>Kamar tersedia</small></div>
                <div><strong>{{ $totalRooms }}</strong><small>Total kamar</small></div>
                <div><strong>{{ $lowestPrice !== null ? rupiah_short($lowestPrice) : '-' }}</strong><small>Mulai per bulan</small></div>
            </div>
        </section>

        @forelse ($roomTypes as $typeName => $typeRooms)
            <section class="landing-section">
                <div class="panel-heading">
                    <div><h2>{{ $typeName }}</h2><p>{{ $typeRooms->first()->roomType?->description ?: 'Kamar siap huni.' }}</p></div>
                    <span class="status-badge available"><i></i>{{ $typeRooms->count() }} tersedia</span>
                </div>
                <div class="room-grid">
                    @foreach ($typeRooms as $room)
                        <article class="room-card">
                            @php($cover = $room->photos->first())
                            <div class="room-visual available {{ $cover ? 'has-photo' : '' }}">
                                @if ($cover)<img src="{{ $cover->url() }}" alt="Foto Kamar {{ $room->room_number }}" loading="lazy">@endif
                                <span class="room-number">Kamar {{ $room->room_number }}</span><span class="status-badge available"><i></i>Tersedia</span><span class="floor-label">{{ $room->floorLabel() }}@if ($room->sizeLabel()) · {{ $room->sizeLabel() }}@endif</span>
                            </div>
                            <div class="room-body">
                                <div><h3>{{ $typeName }}</h3><strong class="room-price">{{ rupiah($room->price) }} <small>/bulan</small></strong></div>
                                @if ($room->facilityList())
                                    <ul class="facility-chips">
                                        @foreach ($room->facilityList() as $facility)
                                            <li><span class="material-symbols-outlined">{{ $facility['icon'] }}</span>{{ $facility['label'] }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                                <div class="room-tenant"><span class="tenant-icon"><span class="material-symbols-outlined">verified</span></span><span><b>Siap dihuni</b><small>Hubungi pengelola untuk survei & check-in</small></span></div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @empty
            <section class="panel settings-form landing-section">
                <h2>Semua kamar sedang terisi</h2>
                <p class="empty-note">Saat ini belum ada kamar kosong. Silakan cek kembali halaman ini secara berkala.</p>
            </section>
        @endforelse
    </main>

    <footer class="landing-footer">© {{ now()->year }} SIMAKOS · Sistem Informasi Manajemen Kos</footer>
</body>
</html>

<div class="page-heading">
    <div><span class="eyebrow">SIMAKOS / {{ strtoupper($pageTitles[$activeMenu][0]) }}</span><h1>{{ $pageTitles[$activeMenu][0] }}</h1><p>{{ $pageTitles[$activeMenu][1] }}</p></div>
    @if ($activeMenu === 'overview')
        <a class="button button-primary" href="{{ route('tenants.create') }}"><span class="material-symbols-outlined">add</span>Tambah Penghuni</a>
    @elseif ($activeMenu === 'rooms')
        <a class="button button-primary" href="{{ route('rooms.create') }}"><span class="material-symbols-outlined">add</span>Tambah Kamar</a>
    @elseif ($activeMenu === 'billing')
        <a class="button button-primary" href="{{ route('billing.create') }}"><span class="material-symbols-outlined">add</span>Catat Pembayaran</a>
    @elseif ($activeMenu === 'complaints')
        <a class="button button-primary" href="{{ route('complaints.create') }}"><span class="material-symbols-outlined">add</span>Buat Tiket</a>
    @elseif ($activeMenu === 'announcements')
        <a class="button button-primary" href="{{ route('announcements.create') }}"><span class="material-symbols-outlined">add</span>Buat Pengumuman</a>
    @endif
</div>

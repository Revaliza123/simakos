<div class="page-heading">
    <div><span class="eyebrow">SIMAKOS / {{ strtoupper($pageTitles[$activeMenu][0]) }}</span><h1>{{ $pageTitles[$activeMenu][0] }}</h1><p>{{ $pageTitles[$activeMenu][1] }}</p></div>
    @if ($activeMenu === 'rooms')
        <div class="heading-actions">
            <a class="button button-secondary" href="{{ route('room-types.index') }}"><span class="material-symbols-outlined">category</span>Tipe Kamar</a>
            <a class="button button-primary" href="{{ route('rooms.create') }}"><span class="material-symbols-outlined">add</span>Tambah Kamar</a>
        </div>
    @elseif ($activeMenu === 'room-types')
        <div class="heading-actions">
            <a class="button button-secondary" href="{{ route('rooms') }}"><span class="material-symbols-outlined">arrow_back</span>Unit Kamar</a>
            <a class="button button-primary" href="{{ route('room-types.create') }}"><span class="material-symbols-outlined">add</span>Tambah Tipe</a>
        </div>
    @elseif ($activeMenu === 'rentals')
        <div class="heading-actions">
            <a class="button button-secondary" href="{{ route('rentals.create') }}"><span class="material-symbols-outlined">key</span>Check-in Penghuni Terdaftar</a>
            <a class="button button-primary" href="{{ route('tenants.create') }}"><span class="material-symbols-outlined">person_add</span>Penghuni Baru</a>
        </div>
    @elseif ($activeMenu === 'billing')
        <div class="heading-actions">
            @if (request()->routeIs('billing.charges*'))
                <a class="button button-secondary" href="{{ route('billing') }}"><span class="material-symbols-outlined">arrow_back</span>Tagihan Sewa</a>
                <a class="button button-primary" href="{{ route('billing.charges.create') }}"><span class="material-symbols-outlined">add</span>Buat Tagihan Lain</a>
            @else
                <a class="button button-secondary" href="{{ route('billing.charges') }}"><span class="material-symbols-outlined">request_quote</span>Tagihan Lain</a>
                <form method="POST" action="{{ route('billing.generate') }}">@csrf<input type="hidden" name="month" value="{{ ($month ?? now())->format('Y-m') }}"><button class="button button-secondary" type="submit" title="Terbitkan tagihan sewa & tagihan lain untuk {{ ($month ?? now())->translatedFormat('F Y') }}"><span class="material-symbols-outlined">autorenew</span>Generate Tagihan {{ ($month ?? now())->translatedFormat('M Y') }}</button></form>
                <a class="button button-primary" href="{{ route('billing.create') }}"><span class="material-symbols-outlined">add</span>Catat Pembayaran</a>
            @endif
        </div>
    @elseif ($activeMenu === 'expenses')
        <a class="button button-primary" href="{{ route('expenses.create') }}"><span class="material-symbols-outlined">add</span>Catat Pengeluaran</a>
    @elseif ($activeMenu === 'complaints')
        <a class="button button-primary" href="{{ route('complaints.create') }}"><span class="material-symbols-outlined">add</span>Buat Tiket</a>
    @elseif ($activeMenu === 'announcements')
        <a class="button button-primary" href="{{ route('announcements.create') }}"><span class="material-symbols-outlined">add</span>Buat Pengumuman</a>
    @elseif ($activeMenu === 'administrator-users')
        <a class="button button-primary" href="{{ route('administrator.users.create') }}"><span class="material-symbols-outlined">add</span>Tambah User</a>
    @endif
</div>

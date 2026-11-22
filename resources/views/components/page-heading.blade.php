<div class="page-heading">
    <div><span class="eyebrow">SIMAKOS / {{ strtoupper($pageTitles[$activeMenu][0]) }}</span><h1>{{ $pageTitles[$activeMenu][0] }}</h1><p>{{ $pageTitles[$activeMenu][1] }}</p></div>
    @if ($activeMenu === 'rooms' && request()->routeIs('rooms'))
        <a class="button button-primary" href="{{ route('rooms.create') }}"><span class="material-symbols-outlined">add</span>Tambah Kamar</a>
    @elseif ($activeMenu === 'billing')
        <div class="heading-actions">
            <form method="POST" action="{{ route('billing.generate') }}">@csrf<input type="hidden" name="month" value="{{ ($month ?? now())->format('Y-m') }}"><button class="button button-secondary" type="submit"><span class="material-symbols-outlined">autorenew</span>Generate Tagihan</button></form>
            <a class="button button-primary" href="{{ route('billing.create') }}"><span class="material-symbols-outlined">add</span>Catat Pembayaran</a>
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

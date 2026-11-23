<aside class="sidebar tenant-sidebar" id="sidebar">
    <div>
        <a class="brand tenant-brand" href="{{ route('tenant.overview') }}"><span class="brand-mark"><span class="material-symbols-outlined">apartment</span></span><span><strong>SIMAKOS</strong><small>PENGHUNI PORTAL</small></span></a>
        <div class="tenant-room-card"><span><i></i>Kamar {{ $tenant['room'] }}</span><small>{{ $tenant['floor'] }}</small></div>
        <nav class="sidebar-nav" aria-label="Navigasi penghuni">
            <span class="nav-label">Portal Penghuni</span>
            @foreach ([
                ['key' => 'tenant-overview', 'label' => 'Beranda', 'icon' => 'dashboard', 'route' => 'tenant.overview'],
                ['key' => 'tenant-bills', 'label' => 'Tagihan & Pembayaran', 'icon' => 'receipt_long', 'route' => 'tenant.bills'],
                ['key' => 'tenant-complaints', 'label' => 'Aduan & Tiket', 'icon' => 'support_agent', 'route' => 'tenant.complaints'],
                ['key' => 'tenant-announcements', 'label' => 'Pengumuman Kos', 'icon' => 'campaign', 'route' => 'tenant.announcements'],
            ] as $item)
                <a class="nav-item {{ $activeMenu === $item['key'] ? 'active' : '' }}" href="{{ route($item['route']) }}"><span class="material-symbols-outlined">{{ $item['icon'] }}</span><span>{{ $item['label'] }}</span></a>
            @endforeach
        </nav>
    </div>
    <div class="sidebar-nav tenant-sidebar-footer">
        <div class="tenant-lease"><span class="material-symbols-outlined">verified_user</span><span><b>{{ $rental ? 'Sewa Aktif' : 'Akun Terdaftar' }}</b><small>{{ $rental ? 'Sampai: '.$tenant['lease_end'] : 'Belum ada kontrak sewa' }}</small></span></div>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="nav-item nav-logout" type="submit"><span class="material-symbols-outlined">logout</span><span>Keluar</span></button></form>
    </div>
</aside>

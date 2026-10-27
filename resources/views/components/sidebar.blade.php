<aside class="sidebar" id="sidebar">
    <div>
        <a class="brand" href="{{ route('overview') }}">
            <span class="brand-mark"><span class="material-symbols-outlined">apartment</span></span>
            <span><strong>SIMAKOS</strong><small>KOST MANAGER</small></span>
        </a>
        <nav class="sidebar-nav" aria-label="Navigasi utama">
            <span class="nav-label">Main menu</span>
            @foreach ([
                ['key' => 'overview', 'label' => 'Overview', 'icon' => 'grid_view', 'route' => 'overview'],
                ['key' => 'rooms', 'label' => 'Room Management', 'icon' => 'bed', 'route' => 'rooms'],
                ['key' => 'billing', 'label' => 'Payment & Billing', 'icon' => 'receipt_long', 'route' => 'billing'],
                ['key' => 'complaints', 'label' => 'Complaints & Tickets', 'icon' => 'report_problem', 'route' => 'complaints'],
                ['key' => 'announcements', 'label' => 'Announcements', 'icon' => 'campaign', 'route' => 'announcements'],
            ] as $item)
                <a class="nav-item {{ $activeMenu === $item['key'] ? 'active' : '' }}" href="{{ route($item['route']) }}">
                    <span class="material-symbols-outlined">{{ $item['icon'] }}</span>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>
    </div>
    <nav class="sidebar-nav sidebar-footer" aria-label="Navigasi sistem">
        <span class="nav-label">System</span>
        <a class="nav-item {{ $activeMenu === 'settings' ? 'active' : '' }}" href="{{ route('settings') }}"><span class="material-symbols-outlined">settings</span><span>Settings</span></a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="nav-item nav-logout" type="submit"><span class="material-symbols-outlined">logout</span><span>Logout</span></button>
        </form>
    </nav>
</aside>

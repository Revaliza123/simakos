<header class="topbar">
    <button class="mobile-menu" type="button" aria-label="Buka menu" data-menu-toggle><span class="material-symbols-outlined">menu</span></button>
    <div class="search-box"><span class="material-symbols-outlined">search</span><input type="search" placeholder="Cari penghuni, kamar, transaksi..." data-global-search></div>
    <div class="topbar-actions">
        <div class="date-pill"><span class="material-symbols-outlined">calendar_today</span><span>Senin, 28 Okt 2026</span></div>
        <button class="icon-button notification-button" type="button" aria-label="Notifikasi"><span class="material-symbols-outlined">notifications</span><i></i></button>
        <div class="profile"><span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span><span class="profile-copy"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->email }}</small></span></div>
    </div>
</header>

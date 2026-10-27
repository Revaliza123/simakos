<div class="page-heading">
    <div><span class="eyebrow">SIMAKOS / {{ strtoupper($pageTitles[$activeMenu][0]) }}</span><h1>{{ $pageTitles[$activeMenu][0] }}</h1><p>{{ $pageTitles[$activeMenu][1] }}</p></div>
    @if ($activeMenu === 'overview')
        <a class="button button-primary" href="{{ route('rooms') }}"><span class="material-symbols-outlined">add</span>Tambah Penghuni</a>
    @elseif ($activeMenu === 'announcements')
        <button class="button button-primary" type="button"><span class="material-symbols-outlined">add</span>Buat Pengumuman</button>
    @endif
</div>

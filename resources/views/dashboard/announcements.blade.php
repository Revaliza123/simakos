@extends('layouts.app')

@section('content')
    @include('components.page-heading')
    @include('components.form-errors')
    <section class="announcement-layout">
        <div class="announcement-list">
            <div class="section-label">Semua pengumuman <span>{{ $announcements->count() }}</span></div>
            @forelse ($announcements as $announcement)
                <article class="announcement-card {{ $loop->first ? 'active' : '' }}" data-search="{{ strtolower($announcement->title) }}">
                    <div class="announcement-icon blue"><span class="material-symbols-outlined">campaign</span></div>
                    <div>
                        <div class="card-meta">
                            @if ($announcement->isPublished())
                                <span class="status-badge available"><i></i>Dipublikasikan</span><time>{{ $announcement->published_at->translatedFormat('d M Y') }}</time>
                            @else
                                <span class="status-badge maintenance"><i></i>Draf</span><time>{{ $announcement->created_at?->translatedFormat('d M Y') }}</time>
                            @endif
                        </div>
                        <h3>{{ $announcement->title }}</h3>
                        <p>{{ $announcement->content }}</p>
                        <small>Oleh {{ $announcement->creator?->name ?? 'Admin' }}</small>
                        <div class="card-actions">
                            <a class="table-action" href="{{ route('announcements.edit', $announcement) }}">Ubah</a>
                            <form method="POST" action="{{ route('announcements.destroy', $announcement) }}" onsubmit="return confirm('Hapus pengumuman ini?')">@csrf @method('DELETE')<button class="table-action danger-text" type="submit">Hapus</button></form>
                        </div>
                    </div>
                </article>
            @empty
                <div class="panel settings-form"><h2>Belum ada pengumuman</h2><p style="margin-top:8px;color:#64748b">Buat pengumuman untuk menampilkan informasi kepada penghuni.</p></div>
            @endforelse
        </div>
        <aside class="panel announcement-side">
            <div class="panel-heading"><div><h2>Ringkasan</h2><p>Aktivitas pengumuman</p></div></div>
            <div class="summary-stat"><span class="material-symbols-outlined blue-text">campaign</span><div><strong>{{ $announcements->filter->isPublished()->count() }}</strong><small>Tampil di portal penghuni</small></div></div>
            <div class="summary-stat"><span class="material-symbols-outlined orange-text">edit_note</span><div><strong>{{ $announcements->reject->isPublished()->count() }}</strong><small>Draf belum dipublikasikan</small></div></div>
        </aside>
    </section>
@endsection

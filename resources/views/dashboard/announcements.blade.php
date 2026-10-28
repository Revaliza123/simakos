@extends('layouts.app')

@section('content')
    @include('components.page-heading')
    @include('components.form-errors')
    <section class="announcement-layout"><div class="announcement-list"><div class="section-label">Pengumuman aktif <span>{{ $announcements->count() }}</span></div>@forelse ($announcements as $announcement)<article class="announcement-card {{ $loop->first ? 'active' : '' }}"><div class="announcement-icon blue"><span class="material-symbols-outlined">campaign</span></div><div><div class="card-meta"><span class="status-badge available"><i></i>Dipublikasikan</span><time>{{ $announcement->created_at?->format('d M Y') }}</time></div><h3>{{ $announcement->title }}</h3><p>{{ $announcement->content }}</p><small>Oleh {{ $announcement->creator?->name ?? 'Admin' }}</small></div></article>@empty<div class="panel settings-form"><h2>Belum ada pengumuman</h2><p style="margin-top:8px;color:#64748b">Buat pengumuman untuk menampilkan informasi kepada penghuni.</p></div>@endforelse</div><aside class="panel announcement-side"><div class="panel-heading"><div><h2>Ringkasan</h2><p>Aktivitas pengumuman</p></div></div><div class="summary-stat"><span class="material-symbols-outlined blue-text">campaign</span><div><strong>{{ $announcements->count() }}</strong><small>Total pengumuman</small></div></div></aside></section>
@endsection

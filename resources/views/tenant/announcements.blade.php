@extends('layouts.tenant')

@section('title', 'Pengumuman Kos')

@section('content')
    <div class="tenant-welcome"><div><span class="eyebrow">PORTAL PENGHUNI / PENGUMUMAN</span><h1>Pengumuman Kos</h1><p>Informasi resmi dari pengelola kos.</p></div></div>
    <section class="panel tenant-announcement-card">
        @forelse ($announcements as $announcement)
            <div class="tenant-notice">
                <span class="announcement-icon blue"><span class="material-symbols-outlined">campaign</span></span>
                <div><span class="status-badge blue"><i></i>{{ $announcement->published_at->translatedFormat('d M Y') }}</span><h3>{{ $announcement->title }}</h3><p style="white-space:pre-line">{{ $announcement->content }}</p>@include('components.announcement-media')<small class="empty-note">Oleh {{ $announcement->creator?->name ?? 'Pengelola' }}</small></div>
            </div>
        @empty
            <p class="empty-note">Belum ada pengumuman.</p>
        @endforelse
    </section>
@endsection

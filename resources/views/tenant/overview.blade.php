@extends('layouts.tenant')

@section('title', 'Beranda Penghuni')

@section('content')
    @php($totalDue = $outstanding->sum(fn ($invoice) => $invoice->totalDue()))
    <div class="tenant-welcome">
        <div><span class="eyebrow">PORTAL PENGHUNI / BERANDA</span><h1>Selamat datang, {{ $tenant['name'] }}</h1><p>Berikut ringkasan informasi kamar dan aktivitas sewa Anda.</p></div>
        <span class="tenant-status"><i></i>{{ $rental ? 'Status Sewa: Aktif' : 'Belum ada kontrak sewa' }}</span>
    </div>

    @if ($rental)
        <section class="tenant-hero panel">
            <div>
                <span class="eyebrow">KAMAR SAAT INI</span>
                <h2>Kamar {{ $rental->room->room_number }} <span>· {{ $rental->room->roomType?->name ?? 'Properti SIMAKOS' }}</span></h2>
                <p>{{ rupiah($rental->room->price) }}/bulan · Mulai {{ $rental->start_date->translatedFormat('d M Y') }} · Selesai: {{ $rental->endLabel() }}@if ($rental->remainingLabel()) · {{ $rental->remainingLabel() }}@endif</p>
                @if ($rental->room->roomType?->description)
                    <div class="tenant-hero-meta"><span><span class="material-symbols-outlined">check_circle</span> {{ $rental->room->roomType->description }}</span></div>
                @endif
                @if ($rental->room->facilityList())
                    <ul class="facility-chips" style="margin-top:12px">
                        @foreach ($rental->room->facilityList() as $facility)
                            <li><span class="material-symbols-outlined">{{ $facility['icon'] }}</span>{{ $facility['label'] }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
            @php($cover = $rental->room->photos->first())
            @if ($cover)
                <div class="tenant-room-illustration has-photo"><img class="public-room-photo" src="{{ $cover->url() }}" alt="Foto Kamar {{ $rental->room->room_number }}"></div>
            @else
                <div class="tenant-room-illustration"><span class="material-symbols-outlined">bed</span><small>Unit {{ $rental->room->room_number }}</small></div>
            @endif
        </section>
    @else
        <section class="panel settings-form" style="margin-bottom:20px">
            <h2>Anda belum memiliki kontrak sewa aktif</h2>
            <p class="empty-note">Hubungi pengelola kos untuk proses check-in. Setelah kontrak dibuat, tagihan dan menu keluhan akan aktif di portal ini.</p>
        </section>
    @endif

    <section class="tenant-grid">
        <article class="panel tenant-bill-card">
            <div class="panel-heading">
                <div><h2>Tagihan Belum Lunas</h2><p>{{ $outstanding->count() }} tagihan · diperbarui otomatis setiap bulan</p></div>
                @if ($outstanding->isEmpty())
                    <span class="status-badge available"><i></i>Lunas</span>
                @else
                    <span class="status-badge {{ $outstanding->contains('status', 'overdue') ? 'occupied' : 'maintenance' }}"><i></i>{{ $outstanding->contains('status', 'overdue') ? 'Terlambat' : 'Menunggu Pembayaran' }}</span>
                @endif
            </div>
            <div class="tenant-amount">{{ rupiah($totalDue) }} <small>Total tagihan</small></div>
            <div class="tenant-bill-lines">
                @forelse ($outstanding->take(3) as $invoice)
                    <span>{{ $invoice->label() }} · {{ $invoice->periodLabel() }} · {{ $invoice->statusLabel() }} <b>{{ rupiah($invoice->totalDue()) }}</b></span>
                    <span>Jatuh tempo <b>{{ $invoice->due_date->translatedFormat('d M Y') }}</b></span>
                @empty
                    <span>Tidak ada tagihan tertunggak. Terima kasih!</span>
                @endforelse
                @if ($latestPayment?->status === 'rejected')
                    <span class="danger-text">Pembayaran terakhir ditolak: {{ $latestPayment->admin_notes }}</span>
                @endif
            </div>
            <a class="button button-primary button-full" href="{{ route('tenant.bills') }}" style="margin-top:16px"><span class="material-symbols-outlined">upload</span>Bayar / Unggah Bukti</a>
        </article>

        <article class="panel tenant-ticket-card">
            <div class="panel-heading"><div><h2>Riwayat Tiket & Aduan</h2><p>Pantau penanganan laporan fasilitas</p></div><a class="text-link" href="{{ route('tenant.complaints') }}">Semua</a></div>
            @forelse ($complaints as $complaint)
                <div class="tenant-ticket-row">
                    <span class="activity-avatar {{ $complaint->status === 'resolved' ? 'green' : 'orange' }}"><span class="material-symbols-outlined">{{ $complaint->status === 'resolved' ? 'check' : 'build' }}</span></span>
                    <div><strong>{{ $complaint->title }}</strong><p>{{ $complaint->ticketNumber() }} · {{ $complaint->created_at->diffForHumans() }}</p></div>
                    <span class="status-badge {{ $complaint->statusTone() }}"><i></i>{{ $complaint->statusLabel() }}</span>
                </div>
            @empty
                <p class="empty-note">Belum ada keluhan. Laporkan kerusakan fasilitas melalui menu Aduan & Tiket.</p>
            @endforelse
            @if ($rental)
                <a class="button button-secondary button-full" href="{{ route('tenant.complaints.create') }}" style="margin-top:12px"><span class="material-symbols-outlined">add</span>Ajukan Keluhan</a>
            @endif
        </article>
    </section>

    <section class="tenant-grid tenant-grid-bottom">
        <article class="panel tenant-announcement-card" style="grid-column:1/-1">
            <div class="panel-heading"><div><h2>Papan Pengumuman</h2><p>Informasi terbaru dari pengelola</p></div><a class="text-link" href="{{ route('tenant.announcements') }}">Semua</a></div>
            @forelse ($announcements as $announcement)
                <div class="tenant-notice">
                    <span class="announcement-icon blue"><span class="material-symbols-outlined">campaign</span></span>
                    <div><span class="status-badge blue"><i></i>{{ $announcement->published_at->translatedFormat('d M Y') }}</span><h3>{{ $announcement->title }}</h3><p>{{ \Illuminate\Support\Str::limit($announcement->content, 220) }}</p>@include('components.announcement-media', ['compact' => true])</div>
                </div>
            @empty
                <p class="empty-note">Belum ada pengumuman.</p>
            @endforelse
        </article>
    </section>
@endsection

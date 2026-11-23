@extends('layouts.tenant')

@section('title', 'Aduan & Tiket')

@section('content')
    <div class="tenant-welcome">
        <div><span class="eyebrow">PORTAL PENGHUNI / ADUAN</span><h1>Aduan & Tiket</h1><p>Laporkan kerusakan fasilitas dan pantau status penanganannya.</p></div>
        @if ($rental)
            <a class="button button-primary" href="{{ route('tenant.complaints.create') }}"><span class="material-symbols-outlined">add</span>Ajukan Keluhan</a>
        @endif
    </div>
    <section class="ticket-grid">
        @forelse ($complaints as $complaint)
            <article class="ticket-card">
                <div class="ticket-top"><span class="status-badge {{ $complaint->statusTone() }}"><i></i>{{ $complaint->statusLabel() }}</span><span class="ticket-id">{{ $complaint->ticketNumber() }}</span></div>
                <h3>{{ $complaint->title }}</h3>
                <p>{{ $complaint->description }}</p>
                @if ($complaint->photo)
                    <a class="ticket-photo" href="{{ asset('storage/'.$complaint->photo) }}" target="_blank" rel="noopener"><img src="{{ asset('storage/'.$complaint->photo) }}" alt="Foto bukti {{ $complaint->title }}" loading="lazy"></a>
                @endif
                <div class="ticket-info"><span class="material-symbols-outlined">meeting_room</span>Kamar {{ $complaint->rental?->room?->room_number ?? '-' }}<time>{{ $complaint->created_at->diffForHumans() }}</time></div>
                <div class="ticket-response">
                    <b>Tanggapan pengelola</b>
                    {{ $complaint->response ?: 'Belum ada tanggapan. Laporan Anda sudah masuk antrean.' }}
                    @if ($complaint->responded_at)<small>{{ $complaint->responded_at->translatedFormat('d M Y H:i') }}</small>@endif
                </div>
            </article>
        @empty
            <div class="panel settings-form"><h2>Belum ada keluhan</h2><p class="empty-note">Gunakan tombol Ajukan Keluhan untuk melaporkan kerusakan fasilitas.</p></div>
        @endforelse
    </section>
@endsection

@extends('layouts.app')

@section('content')
    @include('components.page-heading')
    @include('components.form-errors')
    <div class="toolbar"><div class="tabs"><span class="tab active">Semua <b>{{ $complaints->count() }}</b></span></div><a class="button button-primary" href="{{ route('complaints.create') }}"><span class="material-symbols-outlined">add</span>Buat Tiket</a></div>
    <section class="ticket-grid">@forelse ($complaints as $complaint)<article class="ticket-card"><div class="ticket-top"><span class="status-badge {{ $complaint->status === 'resolved' ? 'available' : ($complaint->status === 'open' ? 'occupied' : 'maintenance') }}"><i></i>{{ str_replace('_', ' ', ucfirst($complaint->status)) }}</span><span class="ticket-id">#TKT-{{ str_pad($complaint->id, 4, '0', STR_PAD_LEFT) }}</span></div><h3>{{ $complaint->title }}</h3><p>{{ $complaint->description }}</p><div class="ticket-info"><span class="material-symbols-outlined">person</span>{{ $complaint->rental?->user?->name ?? '-' }} · Kamar {{ $complaint->rental?->room?->room_number ?? '-' }}<time>{{ $complaint->created_at?->diffForHumans() }}</time></div><div class="ticket-footer"><span><span class="material-symbols-outlined">support_agent</span> Tiket tercatat</span></div></article>@empty<div class="panel settings-form"><h2>Belum ada tiket keluhan</h2><p style="margin-top:8px;color:#64748b">Gunakan tombol Buat Tiket untuk menambahkan laporan pertama.</p></div>@endforelse</section>
@endsection

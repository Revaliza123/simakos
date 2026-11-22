@extends('layouts.app')

@section('content')
@include('components.page-heading')
@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@include('components.form-errors')
<div class="toolbar"><div class="tabs"><button class="tab active" type="button" data-filter="all">Semua <b>{{ $roomCounts['all'] }}</b></button><button class="tab" type="button" data-filter="available">Tersedia <b>{{ $roomCounts['available'] }}</b></button><button class="tab" type="button" data-filter="occupied">Terisi <b>{{ $roomCounts['occupied'] }}</b></button><button class="tab" type="button" data-filter="maintenance">Perbaikan <b>{{ $roomCounts['maintenance'] }}</b></button></div><a class="button button-secondary" href="{{ route('rooms.create') }}"><span class="material-symbols-outlined">add</span>Tambah Kamar</a></div>
<section class="room-grid" data-room-list>
@forelse($roomsData as $room)
<article class="room-card" data-status="{{ $room['tone'] }}" data-search="{{ strtolower($room['number'].' '.$room['type'].' '.$room['tenant']) }}">
    <div class="room-visual {{ $room['tone'] }}"><span class="room-number">Kamar {{ $room['number'] }}</span><span class="status-badge {{ $room['tone'] }}"><i></i>{{ $room['status'] }}</span><span class="floor-label">{{ $room['floor'] }}</span></div>
    <div class="room-body"><div><h3>{{ $room['type'] }}</h3><strong class="room-price">{{ $room['price'] }} <small>/bulan</small></strong></div>
    <div class="room-tenant"><span class="tenant-icon"><span class="material-symbols-outlined">{{ $room['tone']==='available'?'verified':($room['tone']==='maintenance'?'build':'person') }}</span></span><span><b>{{ $room['tenant'] }}</b><small>{{ $room['tone']==='available'?'Siap dihuni sekarang':'Informasi penghuni / status' }}</small></span></div>
    <div class="room-meta"><span>ID: {{ $room['id'] }}</span><span>{{ $room['status'] }}</span></div>
    <div class="form-actions"><a class="button button-secondary" href="{{ route('rooms.edit', $room['id']) }}">Edit</a><form method="POST" action="{{ route('rooms.destroy', $room['id']) }}" onsubmit="return confirm('Hapus kamar ini?')">@csrf @method('DELETE')<button class="button button-danger" type="submit">Hapus</button></form>@if($room['tone']==='available')<a class="button button-primary" href="{{ route('tenants.create') }}">Tugaskan Penghuni</a>@endif</div>
    </div>
</article>
@empty<div class="panel"><p>Belum ada data kamar. Silakan tambahkan kamar terlebih dahulu.</p></div>@endforelse
</section>
@endsection

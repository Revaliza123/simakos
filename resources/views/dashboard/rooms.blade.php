@extends('layouts.app')

@section('content')
    @include('components.page-heading')
    <div class="toolbar"><div class="tabs"><button class="tab active" type="button" data-filter="all">Semua <b>56</b></button><button class="tab" type="button" data-filter="available">Tersedia <b>8</b></button><button class="tab" type="button" data-filter="occupied">Terisi <b>42</b></button><button class="tab" type="button" data-filter="maintenance">Perbaikan <b>6</b></button></div><button class="button button-secondary" type="button"><span class="material-symbols-outlined">tune</span>Filter</button></div>
    <section class="room-grid" data-room-list>
        @foreach ($roomsData as $room)
            <article class="room-card" data-status="{{ $room['tone'] }}" data-search="{{ strtolower($room['number'].' '.$room['type'].' '.$room['tenant']) }}"><div class="room-visual {{ $room['tone'] }}"><span class="room-number">Kamar {{ $room['number'] }}</span><span class="status-badge {{ $room['tone'] }}"><i></i>{{ $room['status'] }}</span><span class="floor-label">{{ $room['floor'] }}</span></div><div class="room-body"><div><h3>{{ $room['type'] }}</h3><strong class="room-price">{{ $room['price'] }} <small>/bulan</small></strong></div><div class="room-tenant"><span class="tenant-icon"><span class="material-symbols-outlined">{{ $room['tone'] === 'available' ? 'verified' : ($room['tone'] === 'maintenance' ? 'build' : 'person') }}</span></span><span><b>{{ $room['tenant'] }}</b><small>{{ $room['tone'] === 'available' ? 'Siap dihuni sekarang' : 'Informasi penghuni / status' }}</small></span></div><div class="room-meta"><span><span class="material-symbols-outlined">ac_unit</span> AC</span><span><span class="material-symbols-outlined">shower</span> KM dalam</span><span><span class="material-symbols-outlined">wifi</span> WiFi</span></div><button class="button button-full {{ $room['tone'] === 'available' ? 'button-primary' : 'button-secondary' }}" type="button">{{ $room['tone'] === 'available' ? 'Tugaskan Penghuni' : 'Detail Kamar' }}</button></div></article>
        @endforeach
    </section>
@endsection

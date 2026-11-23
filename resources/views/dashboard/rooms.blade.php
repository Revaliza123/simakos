@extends('layouts.app')

@section('content')
    @include('components.page-heading')
    @include('components.form-errors')
    <div class="toolbar">
        <div class="tabs">
            <button class="tab active" type="button" data-filter="all">Semua <b>{{ $counts['all'] }}</b></button>
            <button class="tab" type="button" data-filter="available">Tersedia <b>{{ $counts['available'] }}</b></button>
            <button class="tab" type="button" data-filter="occupied">Terisi <b>{{ $counts['occupied'] }}</b></button>
            <button class="tab" type="button" data-filter="maintenance">Perbaikan <b>{{ $counts['maintenance'] }}</b></button>
        </div>
        <a class="button button-secondary" href="{{ route('rentals.index') }}"><span class="material-symbols-outlined">history</span>Riwayat Sewa</a>
    </div>
    <section class="room-grid" data-room-list>
        @forelse ($rooms as $room)
            @php($rental = $room->activeRental)
            @php($cover = $room->photos->first())
            @php($facilities = $room->facilityList())
            <article class="room-card" data-status="{{ $room->status }}" data-search="{{ strtolower($room->room_number.' '.($room->roomType?->name ?? '').' '.($rental?->user?->name ?? '')) }}">
                <a class="room-visual {{ $room->status }} {{ $cover ? 'has-photo' : '' }}" href="{{ route('rooms.show', $room) }}" aria-label="Lihat detail Kamar {{ $room->room_number }}">
                    @if ($cover)
                        <img src="{{ $cover->url() }}" alt="Foto Kamar {{ $room->room_number }}" loading="lazy">
                    @else
                        <span class="room-visual-empty material-symbols-outlined">bed</span>
                    @endif
                    <span class="room-number">Kamar {{ $room->room_number }}</span>
                    <span class="status-badge {{ $room->status }}"><i></i>{{ $room->statusLabel() }}</span>
                    <span class="floor-label">{{ $room->floorLabel() }}@if ($room->sizeLabel()) · {{ $room->sizeLabel() }}@endif</span>
                    @if ($room->photos->count() > 1)
                        <span class="photo-count"><span class="material-symbols-outlined">photo_library</span>{{ $room->photos->count() }}</span>
                    @endif
                </a>
                <div class="room-body">
                    <div><h3>{{ $room->roomType?->name ?? 'Tanpa tipe' }}</h3><strong class="room-price">{{ rupiah($room->price) }} <small>/bulan</small></strong></div>
                    <div class="room-tenant">
                        <span class="tenant-icon"><span class="material-symbols-outlined">{{ $room->status === 'available' ? 'verified' : ($room->status === 'maintenance' ? 'build' : 'person') }}</span></span>
                        <span>
                            <b>{{ $rental?->user?->name ?? ($room->status === 'available' ? 'Siap huni' : ($room->status === 'maintenance' ? 'Sedang perbaikan' : 'Belum ada data penghuni')) }}</b>
                            @if ($rental)
                                <small>{{ $rental->periodLabel() }}</small>
                                @if ($rental->remainingLabel())
                                    <small class="remaining {{ ($rental->remainingDays() ?? 99) <= 30 ? 'soon' : '' }}">{{ $rental->remainingLabel() }}</small>
                                @endif
                            @else
                                <small>{{ $room->status === 'available' ? 'Siap dihuni sekarang' : 'Tidak dapat disewa sementara' }}</small>
                            @endif
                        </span>
                    </div>
                    @if ($facilities)
                        <ul class="facility-chips">
                            @foreach (array_slice($facilities, 0, 4) as $facility)
                                <li title="{{ $facility['label'] }}"><span class="material-symbols-outlined">{{ $facility['icon'] }}</span>{{ $facility['label'] }}</li>
                            @endforeach
                            @if (count($facilities) > 4)
                                <li class="more">+{{ count($facilities) - 4 }}</li>
                            @endif
                        </ul>
                    @endif
                    <div class="room-actions">
                        <a class="button button-secondary" href="{{ route('rooms.show', $room) }}"><span class="material-symbols-outlined">info</span>Detail</a>
                        @if ($room->status === 'available')
                            <a class="button button-primary" href="{{ route('tenants.create', ['room' => $room->id]) }}"><span class="material-symbols-outlined">person_add</span>Tugaskan</a>
                        @endif
                        <a class="icon-action" href="{{ route('rooms.edit', $room) }}" title="Ubah kamar" aria-label="Ubah Kamar {{ $room->room_number }}"><span class="material-symbols-outlined">edit</span></a>
                        @if ($room->rentals_count === 0)
                            <form method="POST" action="{{ route('rooms.destroy', $room) }}" onsubmit="return confirm('Hapus Kamar {{ $room->room_number }}?')">@csrf @method('DELETE')<button class="icon-action danger" type="submit" title="Hapus kamar" aria-label="Hapus Kamar {{ $room->room_number }}"><span class="material-symbols-outlined">delete</span></button></form>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <div class="panel settings-form"><h2>Belum ada kamar</h2><p class="empty-note">Tambahkan tipe kamar terlebih dahulu, lalu daftarkan unit kamar.</p></div>
        @endforelse
    </section>

@endsection

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
            @php($rental = $room->rentals->first())
            <article class="room-card" data-status="{{ $room->status }}" data-search="{{ strtolower($room->room_number.' '.($room->roomType?->name ?? '').' '.($rental?->user?->name ?? '')) }}">
                <div class="room-visual {{ $room->status }}">
                    <span class="room-number">Kamar {{ $room->room_number }}</span>
                    <span class="status-badge {{ $room->status }}"><i></i>{{ $room->statusLabel() }}</span>
                    <span class="floor-label">{{ $room->roomType?->name ?? 'Tanpa tipe' }}</span>
                </div>
                <div class="room-body">
                    <div><h3>{{ $room->roomType?->name ?? 'Tanpa tipe' }}</h3><strong class="room-price">{{ rupiah($room->price) }} <small>/bulan</small></strong></div>
                    <div class="room-tenant">
                        <span class="tenant-icon"><span class="material-symbols-outlined">{{ $room->status === 'available' ? 'verified' : ($room->status === 'maintenance' ? 'build' : 'person') }}</span></span>
                        <span>
                            <b>{{ $rental?->user?->name ?? ($room->status === 'available' ? 'Siap huni' : ($room->status === 'maintenance' ? 'Sedang perbaikan' : 'Belum ada data penghuni')) }}</b>
                            <small>{{ $rental ? 'Sejak '.$rental->start_date->translatedFormat('d M Y').($rental->end_date ? ' · s.d. '.$rental->end_date->translatedFormat('d M Y') : '') : ($room->status === 'available' ? 'Siap dihuni sekarang' : 'Tidak dapat disewa sementara') }}</small>
                        </span>
                    </div>
                    @if ($room->roomType?->description)
                        <p class="room-description">{{ \Illuminate\Support\Str::limit($room->roomType->description, 90) }}</p>
                    @endif
                    @if ($room->status === 'available')
                        <a class="button button-full button-primary" href="{{ route('tenants.create', ['room' => $room->id]) }}">Tugaskan Penghuni</a>
                    @elseif ($rental)
                        <form method="POST" action="{{ route('rentals.checkout', $rental) }}" onsubmit="return confirm('Proses check-out {{ addslashes($rental->user?->name ?? '') }} dari Kamar {{ $room->room_number }}?')">
                            @csrf @method('PATCH')
                            <button class="button button-full button-secondary" type="submit"><span class="material-symbols-outlined">logout</span>Check-out Penghuni</button>
                        </form>
                    @endif
                    <div class="card-actions">
                        <a class="table-action" href="{{ route('rooms.edit', $room) }}">Ubah</a>
                        @if ($room->rentals_count === 0)
                            <form method="POST" action="{{ route('rooms.destroy', $room) }}" onsubmit="return confirm('Hapus Kamar {{ $room->room_number }}?')">@csrf @method('DELETE')<button class="table-action danger-text" type="submit">Hapus</button></form>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <div class="panel settings-form"><h2>Belum ada kamar</h2><p class="empty-note">Tambahkan tipe kamar terlebih dahulu, lalu daftarkan unit kamar.</p></div>
        @endforelse
    </section>
@endsection

@extends('layouts.app')

@section('content')
    <div class="page-heading">
        <div>
            <span class="eyebrow"><a href="{{ route('rooms') }}">SIMAKOS / ROOM MANAGEMENT</a> / KAMAR {{ strtoupper($room->room_number) }}</span>
            <h1 class="room-title">Kamar {{ $room->room_number }} <span class="status-badge {{ $room->status }}"><i></i>{{ $room->statusLabel() }}</span></h1>
            <p>{{ $room->roomType?->name ?? 'Tanpa tipe' }} · {{ $room->floor !== null ? $room->floorLabel() : 'Lantai -' }}{{ $room->sizeLabel() ? ' · '.$room->sizeLabel() : '' }}</p>
        </div>
        <div class="heading-actions">
            <a class="button button-secondary" href="{{ route('rooms') }}"><span class="material-symbols-outlined">arrow_back</span>Kembali</a>
            <a class="button button-secondary" href="{{ route('rooms.edit', $room) }}"><span class="material-symbols-outlined">edit</span>Ubah Kamar &amp; Foto</a>
            @if ($room->status === 'available')
                <a class="button button-primary" href="{{ route('tenants.create', ['room' => $room->id]) }}"><span class="material-symbols-outlined">person_add</span>Tugaskan Penghuni</a>
            @endif
        </div>
    </div>
    @include('components.form-errors')

    <section class="panel room-detail-page">
        @include('dashboard.partials.room-detail')
    </section>

    <script>
        // Ganti foto utama saat thumbnail diklik.
        document.querySelectorAll('[data-gallery-thumb]').forEach((thumb) => {
            thumb.addEventListener('click', () => {
                document.querySelector('[data-gallery-main]').src = thumb.dataset.galleryThumb;
                document.querySelectorAll('[data-gallery-thumb]').forEach((t) => t.classList.toggle('active', t === thumb));
            });
        });
    </script>
@endsection

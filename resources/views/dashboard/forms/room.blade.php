@extends('components.form-page')

@section('form-content')
    @php($editing = $room->exists)
    <div class="panel-heading"><div><h2>{{ $editing ? 'Ubah Kamar '.$room->room_number : 'Tambah Kamar' }}</h2><p>{{ $editing ? 'Perbarui nomor, tipe, tarif, atau status kamar.' : 'Masukkan data kamar baru ke dalam properti.' }}</p></div></div>
    <form method="POST" action="{{ $editing ? route('rooms.update', $room) : route('rooms.store') }}">@csrf
        @if ($editing) @method('PUT') @endif
        <div class="form-grid">
            <label>Nomor kamar<input name="room_number" value="{{ old('room_number', $room->room_number) }}" maxlength="10" required></label>
            <label>Tipe kamar
                <select name="room_type_id">
                    <option value="">Pilih tipe kamar</option>
                    @foreach ($roomTypes as $type)
                        <option value="{{ $type->id }}" @selected((int) old('room_type_id', $room->room_type_id) === $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Harga per bulan<input name="price" value="{{ old('price', $room->price !== null ? (float) $room->price : '') }}" type="number" min="0" step="1000" required></label>
            <label>Status
                @if ($hasActiveRental)
                    <input value="Terisi (terkunci sampai check-out)" disabled>
                @else
                    <select name="status" required>
                        <option value="available" @selected(old('status', $room->status) === 'available')>Tersedia</option>
                        <option value="maintenance" @selected(old('status', $room->status) === 'maintenance')>Perbaikan</option>
                    </select>
                @endif
            </label>
        </div>
        @if ($roomTypes->isEmpty())
            <p class="empty-note">Belum ada tipe kamar. <a class="text-link" href="{{ route('room-types.create') }}">Tambah tipe kamar</a></p>
        @endif
        <p class="empty-note">Status <b>Terisi</b> diatur otomatis oleh sistem saat check-in dan dikembalikan ke Tersedia saat check-out.</p>
        <div class="form-actions"><a class="button button-secondary" href="{{ route('rooms') }}">Batal</a><button class="button button-primary" type="submit">{{ $editing ? 'Simpan Perubahan' : 'Simpan Kamar' }}</button></div>
    </form>
@endsection

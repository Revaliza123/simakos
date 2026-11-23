@extends('components.form-page')

@section('form-content')
    @php($editing = $roomType->exists)
    <div class="panel-heading"><div><h2>{{ $editing ? 'Ubah Tipe Kamar' : 'Tambah Tipe Kamar' }}</h2><p>Nama kategori kamar, deskripsi, dan fasilitas bawaan untuk semua unit bertipe ini.</p></div></div>
    <form method="POST" action="{{ $editing ? route('room-types.update', $roomType) : route('room-types.store') }}">@csrf
        @if ($editing) @method('PUT') @endif
        <div class="form-grid">
            <label style="grid-column:1/-1">Nama tipe<input name="name" value="{{ old('name', $roomType->name) }}" maxlength="100" placeholder="mis. Deluxe Balcony" required></label>
            <label style="grid-column:1/-1">Deskripsi<textarea name="description" rows="4" placeholder="mis. Kamar luas dengan balkon pribadi dan pencahayaan alami">{{ old('description', $roomType->description) }}</textarea></label>
            <div style="grid-column:1/-1">
                <span class="field-label">Fasilitas bawaan</span>
                @include('dashboard.forms.facility-picker', ['selected' => old('facilities', $roomType->facilities ?? []), 'inherited' => []])
            </div>
        </div>
        <div class="form-actions"><a class="button button-secondary" href="{{ route('room-types.index') }}">Batal</a><button class="button button-primary" type="submit">{{ $editing ? 'Simpan Perubahan' : 'Simpan Tipe' }}</button></div>
    </form>
@endsection

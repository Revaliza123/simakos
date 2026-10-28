@extends('components.form-page')

@section('form-content')
    <div class="panel-heading"><div><h2>Tambah Kamar</h2><p>Masukkan data kamar baru ke dalam properti.</p></div></div>
    <form method="POST" action="{{ route('rooms.store') }}">@csrf
        <div class="form-grid"><label>Nomor kamar<input name="room_number" value="{{ old('room_number') }}" maxlength="10" required></label><label>Tipe kamar<select name="room_type_id"><option value="">Pilih tipe kamar</option>@foreach ($roomTypes as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach</select></label><label>Harga per bulan<input name="price" value="{{ old('price') }}" type="number" min="0" step="0.01" required></label><label>Status<select name="status" required><option value="available">Tersedia</option><option value="occupied">Terisi</option><option value="maintenance">Perbaikan</option></select></label></div><div class="form-actions"><a class="button button-secondary" href="{{ route('rooms') }}">Batal</a><button class="button button-primary" type="submit">Simpan Kamar</button></div>
    </form>
@endsection

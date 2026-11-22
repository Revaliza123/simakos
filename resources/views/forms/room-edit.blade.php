@extends('components.form-page')
@section('form-content')
<div class="panel-heading"><div><h2>Edit Kamar {{ $room->room_number }}</h2><p>Perbarui informasi kamar.</p></div></div>
<form method="POST" action="{{ route('rooms.update', $room) }}">@csrf @method('PUT')
<div class="form-grid"><label>Nomor kamar<input name="room_number" value="{{ old('room_number', $room->room_number) }}" maxlength="10" required></label><label>Tipe kamar<select name="room_type_id"><option value="">Pilih tipe kamar</option>@foreach($roomTypes as $type)<option value="{{ $type->id }}" @selected(old('room_type_id', $room->room_type_id)==$type->id)>{{ $type->name }}</option>@endforeach</select></label><label>Harga per bulan<input name="price" type="number" min="0" step="0.01" value="{{ old('price', $room->price) }}" required></label><label>Status<select name="status"><option value="available" @selected($room->status==='available')>Tersedia</option><option value="maintenance" @selected($room->status==='maintenance')>Perbaikan</option></select><small>Status Terisi ditentukan otomatis oleh penyewaan aktif.</small></label></div>
<div class="form-actions"><a class="button button-secondary" href="{{ route('rooms') }}">Batal</a><button class="button button-primary">Simpan Perubahan</button></div></form>
@endsection

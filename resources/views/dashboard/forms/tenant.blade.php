@extends('components.form-page')

@section('form-content')
    <div class="panel-heading"><div><h2>Tambah Penghuni</h2><p>Buat akun penghuni sekaligus kontrak sewa aktif.</p></div></div>
    <form method="POST" action="{{ route('tenants.store') }}">@csrf
        <div class="form-grid"><label>Nama lengkap<input name="name" value="{{ old('name') }}" required></label><label>Email<input name="email" value="{{ old('email') }}" type="email" required></label><label>Password<input name="password" type="password" minlength="8" required></label><label>Konfirmasi password<input name="password_confirmation" type="password" minlength="8" required></label><label>Kamar tersedia<select name="room_id" required><option value="">Pilih kamar</option>@foreach ($rooms as $room)<option value="{{ $room->id }}">Kamar {{ $room->room_number }} · Rp {{ number_format($room->price, 0, ',', '.') }}</option>@endforeach</select></label><label>Tanggal mulai<input name="start_date" value="{{ old('start_date', now()->format('Y-m-d')) }}" type="date" required></label><label>Tanggal selesai<input name="end_date" value="{{ old('end_date') }}" type="date"></label></div><div class="form-actions"><a class="button button-secondary" href="{{ route('overview') }}">Batal</a><button class="button button-primary" type="submit">Simpan Penghuni</button></div>
    </form>
@endsection

@extends('components.form-page')

@section('form-content')
    <div class="panel-heading"><div><h2>Penghuni Baru & Check-in</h2><p>Daftarkan akun penghuni sekaligus kontrak sewa. Status kamar otomatis menjadi Terisi.</p></div></div>
    <form method="POST" action="{{ route('tenants.store') }}">@csrf
        <div class="form-grid">
            <label>Nama lengkap<input name="name" value="{{ old('name') }}" required></label>
            <label>Email<input name="email" value="{{ old('email') }}" type="email" required></label>
            <label>No. telepon<input name="phone_number" value="{{ old('phone_number') }}" maxlength="20" placeholder="08xxxxxxxxxx"></label>
            <label>Kontak darurat<input name="emergency_contact" value="{{ old('emergency_contact') }}" maxlength="100" placeholder="Nama & nomor keluarga"></label>
            <label>Password<input name="password" type="password" minlength="8" required></label>
            <label>Konfirmasi password<input name="password_confirmation" type="password" minlength="8" required></label>
            @include('dashboard.forms.rental-fields')
        </div>
        <div class="form-actions"><a class="button button-secondary" href="{{ route('rentals.index') }}">Batal</a><button class="button button-primary" type="submit">Simpan & Check-in</button></div>
    </form>
@endsection

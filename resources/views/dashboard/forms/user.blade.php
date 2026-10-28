@extends('components.form-page')

@section('form-content')
    <div class="panel-heading"><div><h2>Tambah User</h2><p>Buat akun administrator atau penghuni baru.</p></div></div>
    <form method="POST" action="{{ route('administrator.users.store') }}">@csrf
        <div class="form-grid"><label>Nama lengkap<input name="name" value="{{ old('name') }}" required></label><label>Email<input name="email" value="{{ old('email') }}" type="email" required></label><label>Password<input name="password" type="password" minlength="8" required></label><label>Konfirmasi password<input name="password_confirmation" type="password" minlength="8" required></label><label>Role<select name="role" required><option value="tenant">Tenant / Penghuni</option><option value="admin">Administrator</option></select></label></div><div class="form-actions"><a class="button button-secondary" href="{{ route('administrator.users') }}">Batal</a><button class="button button-primary" type="submit">Simpan User</button></div>
    </form>
@endsection

@extends('layouts.auth')

@section('title', 'Masuk')

@section('content')
    <div class="auth-heading">
        <span class="eyebrow">SELAMAT DATANG KEMBALI</span>
        <h1>Masuk ke SIMAKOS</h1>
        <p>Gunakan akun Anda untuk melanjutkan ke portal.</p>
    </div>
    <form class="auth-form" method="POST" action="{{ route('login.store') }}">
        @csrf
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
        @error('email')<small class="auth-error">{{ $message }}</small>@enderror
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        @error('password')<small class="auth-error">{{ $message }}</small>@enderror
        <label class="auth-remember"><input name="remember" type="checkbox" value="1"> Ingat saya</label>
        <button class="button button-primary auth-submit" type="submit">Masuk</button>
    </form>
    <p class="auth-switch">Belum punya akun penghuni? <a href="{{ route('register') }}">Daftar sekarang</a></p>
@endsection

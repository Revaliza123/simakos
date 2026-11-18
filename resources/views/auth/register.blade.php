@extends('layouts.auth')

@section('title', 'Daftar Penghuni')

@section('content')
    <div class="auth-heading">
        <span class="eyebrow">PORTAL PENGHUNI</span>
        <h1>Buat akun penghuni</h1>
        <p>Daftar untuk masuk ke portal penghuni SIMAKOS.</p>
    </div>
    <form class="auth-form" method="POST" action="{{ route('register.store') }}">
        @csrf
        <label for="name">Nama lengkap</label>
        <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" required autofocus>
        @error('name')<small class="auth-error">{{ $message }}</small>@enderror
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
        @error('email')<small class="auth-error">{{ $message }}</small>@enderror
        <label for="password">Password (minimal 8 karakter)</label>
        <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required>
        @error('password')<small class="auth-error">{{ $message }}</small>@enderror
        <label for="password_confirmation">Konfirmasi password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>
        <button class="button button-primary auth-submit" type="submit">Daftar sebagai Penghuni</button>
    </form>
    <p class="auth-switch">Sudah punya akun? <a href="{{ route('login') }}">Masuk</a></p>
@endsection

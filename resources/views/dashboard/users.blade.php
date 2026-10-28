@extends('layouts.app')

@section('content')
    @include('components.page-heading')
    @include('components.form-errors')
    <div class="toolbar"><div class="tabs"><span class="tab active">Semua User <b>{{ $users->count() }}</b></span><a class="tab" href="{{ route('administrator') }}">Profil Administrator</a></div></div>
    <section class="panel table-panel"><div class="panel-heading"><div><h2>Daftar User</h2><p>Administrator dan penghuni yang terdaftar di sistem.</p></div></div><div class="table-scroll"><table><thead><tr><th>Nama</th><th>Email</th><th>Role</th><th>Terdaftar</th></tr></thead><tbody>@forelse ($users as $user)<tr><td><b>{{ $user->name }}</b></td><td>{{ $user->email }}</td><td><span class="status-badge {{ $user->role === 'admin' ? 'blue' : 'available' }}"><i></i>{{ ucfirst($user->role) }}</span></td><td>{{ $user->created_at?->format('d M Y') }}</td></tr>@empty<tr><td colspan="4">Belum ada user terdaftar.</td></tr>@endforelse</tbody></table></div></section>
@endsection

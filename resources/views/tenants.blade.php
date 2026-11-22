@extends('layouts.app')

@section('content')
@include('components.page-heading')
@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@include('components.form-errors')
<div class="panel">
    <div class="panel-heading"><div><h2>Daftar Penghuni</h2><p>Data tenant, kamar aktif, dan riwayat penyewaan.</p></div><a class="button button-primary" href="{{ route('tenants.create') }}">Tambah Penghuni</a></div>
    <div class="table-responsive"><table><thead><tr><th>Nama</th><th>Email</th><th>Kamar aktif</th><th>Riwayat sewa</th><th>Aksi</th></tr></thead><tbody>
    @forelse($tenants as $tenant)
        @php($activeRental = $tenant->rentals->firstWhere('status', 'active'))
        <tr><td>{{ $tenant->name }}</td><td>{{ $tenant->email }}</td><td>{{ $activeRental?->room?->room_number ? 'Kamar '.$activeRental->room->room_number : 'Belum ditempatkan' }}</td><td>{{ $tenant->rentals->count() }}</td><td>
            @if($activeRental)<form method="POST" action="{{ route('rentals.complete', $activeRental) }}" onsubmit="return confirm('Akhiri masa sewa penghuni ini?')">@csrf @method('PATCH')<button class="button button-secondary" type="submit">Akhiri Sewa</button></form>@else
            <form method="POST" action="{{ route('tenants.assign', $tenant) }}">@csrf<select name="room_id" required><option value="">Pilih kamar tersedia</option>@foreach($availableRooms as $room)<option value="{{ $room->id }}">{{ $room->room_number }} — Rp {{ number_format($room->price,0,',','.') }}</option>@endforeach</select><input type="date" name="start_date" value="{{ now()->toDateString() }}" required><button class="button button-primary" type="submit">Tempatkan</button></form>
            @endif
            <form method="POST" action="{{ route('tenants.update', $tenant) }}" class="inline-form">@csrf @method('PUT')<input name="name" value="{{ $tenant->name }}" required><input type="email" name="email" value="{{ $tenant->email }}" required><button class="button button-secondary">Simpan</button></form>
            @if($tenant->rentals->isEmpty())<form method="POST" action="{{ route('tenants.destroy', $tenant) }}" onsubmit="return confirm('Hapus akun ini?')">@csrf @method('DELETE')<button class="button button-danger">Hapus</button></form>@endif
        </td></tr>
    @empty<tr><td colspan="5">Belum ada data penghuni.</td></tr>@endforelse
    </tbody></table></div>
</div>
@endsection

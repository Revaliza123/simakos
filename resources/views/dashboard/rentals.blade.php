@extends('layouts.app')

@section('content')
    @include('components.page-heading')
    @include('components.form-errors')
    <div class="toolbar">
        <div class="tabs">
            <a class="tab {{ $status === null ? 'active' : '' }}" href="{{ route('rentals.index') }}">Semua <b>{{ $counts->sum() }}</b></a>
            <a class="tab {{ $status === 'active' ? 'active' : '' }}" href="{{ route('rentals.index', ['status' => 'active']) }}">Aktif <b>{{ $counts['active'] ?? 0 }}</b></a>
            <a class="tab {{ $status === 'completed' ? 'active' : '' }}" href="{{ route('rentals.index', ['status' => 'completed']) }}">Selesai <b>{{ $counts['completed'] ?? 0 }}</b></a>
        </div>
    </div>
    <section class="panel table-panel">
        <div class="panel-heading"><div><h2>Kontrak Sewa</h2><p>Riwayat penyewaan setiap kamar tersimpan permanen (BR-02).</p></div></div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Penghuni</th><th>Kamar</th><th>Mulai</th><th>Selesai</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                @forelse ($rentals as $rental)
                    <tr data-search="{{ strtolower(($rental->user?->name ?? '').' '.($rental->room?->room_number ?? '')) }}">
                        <td><b>{{ $rental->user?->name ?? '-' }}</b><small>{{ $rental->user?->email }}{{ $rental->user?->phone_number ? ' · '.$rental->user->phone_number : '' }}</small></td>
                        <td><b>Kamar {{ $rental->room?->room_number ?? '-' }}</b><small>{{ $rental->room?->roomType?->name ?? 'Tanpa tipe' }}</small></td>
                        <td>{{ $rental->start_date->translatedFormat('d M Y') }}</td>
                        <td>{{ $rental->end_date?->translatedFormat('d M Y') ?? 'Tidak ditentukan' }}</td>
                        <td><span class="status-badge {{ $rental->status === 'active' ? 'available' : 'maintenance' }}"><i></i>{{ ['active' => 'Aktif', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'][$rental->status] ?? $rental->status }}</span></td>
                        <td>
                            @if ($rental->status === 'active')
                                <form class="inline-form" method="POST" action="{{ route('rentals.checkout', $rental) }}" onsubmit="return confirm('Proses check-out {{ addslashes($rental->user?->name ?? '') }}? Kamar akan kembali berstatus Tersedia.')">
                                    @csrf @method('PATCH')
                                    <input name="checkout_date" type="date" value="{{ now()->format('Y-m-d') }}" min="{{ $rental->start_date->format('Y-m-d') }}" aria-label="Tanggal check-out">
                                    <button class="table-action danger-text" type="submit">Check-out</button>
                                </form>
                            @else
                                <small>-</small>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">Belum ada kontrak sewa.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

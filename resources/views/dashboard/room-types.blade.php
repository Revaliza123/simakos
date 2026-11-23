@extends('layouts.app')

@section('content')
    @include('components.page-heading')
    @include('components.form-errors')
    <section class="panel table-panel">
        <div class="panel-heading"><div><h2>Daftar Tipe Kamar</h2><p>Kategori kamar beserta deskripsi dan fasilitas bawaannya.</p></div></div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Nama tipe</th><th>Deskripsi / fasilitas</th><th>Jumlah unit</th><th>Aksi</th></tr></thead>
                <tbody>
                @forelse ($roomTypes as $type)
                    <tr data-search="{{ strtolower($type->name) }}">
                        <td><b>{{ $type->name }}</b></td>
                        <td>
                            {{ $type->description ?: '-' }}
                            @if ($type->facilityList())
                                <ul class="facility-chips">
                                    @foreach ($type->facilityList() as $facility)
                                        <li><span class="material-symbols-outlined">{{ $facility['icon'] }}</span>{{ $facility['label'] }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </td>
                        <td>{{ $type->rooms_count }} kamar</td>
                        <td>
                            <div class="row-actions">
                                <a class="table-action" href="{{ route('room-types.edit', $type) }}">Ubah</a>
                                @if ($type->rooms_count === 0)
                                    <form method="POST" action="{{ route('room-types.destroy', $type) }}" onsubmit="return confirm('Hapus tipe {{ addslashes($type->name) }}?')">@csrf @method('DELETE')<button class="table-action danger-text" type="submit">Hapus</button></form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4">Belum ada tipe kamar.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

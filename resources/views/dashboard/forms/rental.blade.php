@extends('components.form-page')

@section('form-content')
    <div class="panel-heading"><div><h2>Check-in Penghuni Terdaftar</h2><p>Pilih akun penghuni yang belum memiliki sewa aktif dan unit kamar yang tersedia.</p></div></div>
    <form method="POST" action="{{ route('rentals.store') }}">@csrf
        <div class="form-grid">
            <label style="grid-column:1/-1">Penghuni
                <select name="user_id" required>
                    <option value="">Pilih penghuni</option>
                    @foreach ($tenants as $tenant)
                        <option value="{{ $tenant->id }}" @selected((int) old('user_id') === $tenant->id)>{{ $tenant->name }} · {{ $tenant->email }}</option>
                    @endforeach
                </select>
            </label>
            @include('dashboard.forms.rental-fields')
        </div>
        @if ($tenants->isEmpty())
            <p class="empty-note">Semua akun penghuni sudah memiliki sewa aktif. <a class="text-link" href="{{ route('tenants.create') }}">Daftarkan penghuni baru</a></p>
        @endif
        <div class="form-actions"><a class="button button-secondary" href="{{ route('rentals.index') }}">Batal</a><button class="button button-primary" type="submit">Simpan Check-in</button></div>
    </form>
@endsection

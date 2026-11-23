@extends('layouts.tenant')

@section('title', 'Ajukan Keluhan')

@section('content')
    <div class="tenant-welcome"><div><span class="eyebrow">PORTAL PENGHUNI / ADUAN / BARU</span><h1>Ajukan Keluhan Fasilitas</h1><p>Laporan terhubung otomatis dengan kontrak sewa Kamar {{ $rental->room->room_number }}.</p></div></div>
    <section class="panel settings-form" style="max-width:820px">
        <form method="POST" action="{{ route('tenant.complaints.store') }}" enctype="multipart/form-data">@csrf
            <div class="form-grid" style="padding-top:0">
                <label style="grid-column:1/-1">Judul keluhan<input name="title" value="{{ old('title') }}" maxlength="255" placeholder="mis. AC bocor" required></label>
                <label style="grid-column:1/-1">Deskripsi kerusakan<textarea name="description" rows="5" maxlength="5000" placeholder="Jelaskan lokasi dan kondisi kerusakan" required>{{ old('description') }}</textarea></label>
                <label style="grid-column:1/-1">Foto bukti kerusakan (JPG/PNG, maks. 2 MB)<input name="photo" type="file" accept=".jpg,.jpeg,.png,image/jpeg,image/png"></label>
            </div>
            <div class="form-actions"><a class="button button-secondary" href="{{ route('tenant.complaints') }}">Batal</a><button class="button button-primary" type="submit">Kirim Keluhan</button></div>
        </form>
    </section>
@endsection

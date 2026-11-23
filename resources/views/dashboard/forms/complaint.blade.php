@extends('components.form-page')

@section('form-content')
    <div class="panel-heading"><div><h2>Buat Tiket Keluhan</h2><p>Catat laporan atas nama penghuni (mis. dilaporkan langsung ke pengelola).</p></div></div>
    <form method="POST" action="{{ route('complaints.store') }}" enctype="multipart/form-data">@csrf
        <div class="form-grid">
            <label style="grid-column:1/-1">Penghuni / kamar<select name="rental_id" required><option value="">Pilih kontrak aktif</option>@foreach ($rentals as $rental)<option value="{{ $rental->id }}" @selected((int) old('rental_id') === $rental->id)>{{ $rental->user->name }} · Kamar {{ $rental->room->room_number }}</option>@endforeach</select></label>
            <label style="grid-column:1/-1">Judul keluhan<input name="title" value="{{ old('title') }}" required></label>
            <label style="grid-column:1/-1">Deskripsi<textarea name="description" rows="5" required>{{ old('description') }}</textarea></label>
            <label style="grid-column:1/-1">Foto bukti (JPG/PNG, maks. 2 MB)<input name="photo" type="file" accept=".jpg,.jpeg,.png,image/jpeg,image/png"></label>
        </div>
        <div class="form-actions"><a class="button button-secondary" href="{{ route('complaints') }}">Batal</a><button class="button button-primary" type="submit">Buat Tiket</button></div>
    </form>
@endsection

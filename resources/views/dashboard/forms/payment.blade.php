@extends('components.form-page')

@section('form-content')
    <div class="panel-heading"><div><h2>Catat Pembayaran</h2><p>Simpan pembayaran sewa untuk kontrak aktif.</p></div></div>
    <form method="POST" action="{{ route('billing.store') }}" enctype="multipart/form-data">@csrf
        <div class="form-grid"><label>Kontrak penghuni<select name="rental_id" required><option value="">Pilih penghuni / kamar</option>@foreach ($rentals as $rental)<option value="{{ $rental->id }}">{{ $rental->user->name }} · Kamar {{ $rental->room->room_number }}</option>@endforeach</select></label><label>Nominal pembayaran<input name="amount" value="{{ old('amount') }}" type="number" min="0" step="0.01" required></label><label>Jumlah bulan dibayar<input name="total_months_paid" value="{{ old('total_months_paid', 1) }}" type="number" min="1" required></label><label>Tanggal pembayaran<input name="payment_date" value="{{ old('payment_date', now()->format('Y-m-d')) }}" type="date" required></label><label style="grid-column:1/-1">Bukti pembayaran<input name="proof_image" type="file" accept="image/*"></label></div><div class="form-actions"><a class="button button-secondary" href="{{ route('billing') }}">Batal</a><button class="button button-primary" type="submit">Simpan Pembayaran</button></div>
    </form>
@endsection

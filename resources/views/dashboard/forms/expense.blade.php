@extends('components.form-page')

@section('form-content')
    <div class="panel-heading"><div><h2>Catat Pengeluaran</h2><p>Simpan biaya operasional kost untuk laporan keuangan.</p></div></div>
    <form method="POST" action="{{ route('expenses.store') }}">@csrf
        <div class="form-grid">
            <label style="grid-column:1/-1">Keterangan<input name="title" value="{{ old('title') }}" placeholder="Contoh: Token listrik lantai 2" maxlength="255" required></label>
            <label>Kategori
                <select name="category" required>
                    @foreach ($categories as $key => $label)<option value="{{ $key }}" @selected(old('category', 'lainnya') === $key)>{{ $label }}</option>@endforeach
                </select>
            </label>
            <label>Nominal (Rp)<input name="amount" value="{{ old('amount') }}" type="number" min="1" step="1" required></label>
            <label>Tanggal pengeluaran<input name="expense_date" value="{{ old('expense_date', now()->format('Y-m-d')) }}" type="date" max="{{ now()->format('Y-m-d') }}" required></label>
            <label style="grid-column:1/-1">Catatan (opsional)<textarea name="description" rows="3" maxlength="1000">{{ old('description') }}</textarea></label>
        </div>
        <div class="form-actions"><a class="button button-secondary" href="{{ route('expenses') }}">Batal</a><button class="button button-primary" type="submit">Simpan Pengeluaran</button></div>
    </form>
@endsection

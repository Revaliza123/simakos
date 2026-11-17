@extends('components.form-page')

@section('form-content')
    <div class="panel-heading"><div><h2>Catat Pembayaran</h2><p>Pilih tagihan yang dibayar. Nominal terisi otomatis termasuk denda keterlambatan.</p></div></div>
    <form method="POST" action="{{ route('billing.store') }}" enctype="multipart/form-data">@csrf
        <div class="form-grid">
            <label style="grid-column:1/-1">Tagihan
                <select name="invoice_id" id="invoice_id">
                    <option value="">Tanpa tagihan spesifik (otomatis ke tunggakan terlama)</option>
                    @foreach ($invoices as $invoice)
                        <option value="{{ $invoice->id }}" data-rental="{{ $invoice->rental_id }}" data-total="{{ $invoice->totalDue() }}" @selected((int) old('invoice_id', $selectedInvoice) === $invoice->id)>
                            {{ $invoice->invoice_number }} · {{ $invoice->rental?->user?->name }} · Kamar {{ $invoice->rental?->room?->room_number }} · {{ rupiah($invoice->totalDue()) }}{{ $invoice->late_fee > 0 ? ' (termasuk denda '.rupiah($invoice->late_fee).')' : '' }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label>Kontrak penghuni
                <select name="rental_id" id="rental_id" required>
                    <option value="">Pilih penghuni / kamar</option>
                    @foreach ($rentals as $rental)
                        <option value="{{ $rental->id }}" data-price="{{ (float) $rental->room->price }}" @selected((int) old('rental_id') === $rental->id)>{{ $rental->user->name }} · Kamar {{ $rental->room->room_number }}</option>
                    @endforeach
                </select>
            </label>
            <label>Nominal pembayaran<input name="amount" id="amount" value="{{ old('amount') }}" type="number" min="0" step="0.01" required></label>
            <label>Jumlah bulan dibayar<input name="total_months_paid" id="total_months_paid" value="{{ old('total_months_paid', 1) }}" type="number" min="1" max="12" required></label>
            <label>Tanggal pembayaran<input name="payment_date" value="{{ old('payment_date', now()->format('Y-m-d')) }}" type="date" max="{{ now()->format('Y-m-d') }}" required></label>
            <label style="grid-column:1/-1">Bukti pembayaran<input name="proof_image" type="file" accept="image/*"></label>
        </div>
        <div class="form-actions"><a class="button button-secondary" href="{{ route('billing') }}">Batal</a><button class="button button-primary" type="submit">Simpan Pembayaran</button></div>
    </form>
    <script>
        (() => {
            const invoice = document.getElementById('invoice_id');
            const rental = document.getElementById('rental_id');
            const amount = document.getElementById('amount');
            const months = document.getElementById('total_months_paid');
            const recalc = () => {
                const inv = invoice.selectedOptions[0];
                const room = rental.selectedOptions[0];
                const price = Number(room?.dataset.price || 0);
                const first = inv?.dataset.total ? Number(inv.dataset.total) : price;
                if (first || price) amount.value = first + price * (Math.max(1, Number(months.value)) - 1);
            };
            invoice.addEventListener('change', () => {
                const inv = invoice.selectedOptions[0];
                if (inv?.dataset.rental) rental.value = inv.dataset.rental;
                recalc();
            });
            rental.addEventListener('change', recalc);
            months.addEventListener('input', recalc);
            if (invoice.value && !amount.value) invoice.dispatchEvent(new Event('change'));
        })();
    </script>
@endsection

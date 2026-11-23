@extends('components.form-page')

@section('form-content')
    <div class="panel-heading"><div><h2>Catat Pembayaran</h2><p>Untuk uang yang diterima langsung oleh pengelola (tunai atau transfer yang sudah dicek). Pembayaran ini <b>langsung disetujui</b> dan tagihannya ditandai lunas. Nominal terisi otomatis, termasuk denda sewa bila terlambat.</p></div></div>
    <form method="POST" action="{{ route('billing.store') }}" enctype="multipart/form-data">@csrf
        <div class="form-grid">
            <label style="grid-column:1/-1">Tagihan
                <select name="invoice_id" id="invoice_id">
                    <option value="" data-type="rent">Sewa tanpa tagihan spesifik (otomatis ke tunggakan sewa terlama)</option>
                    @foreach ($invoices as $invoice)
                        <option value="{{ $invoice->id }}" data-rental="{{ $invoice->rental_id }}" data-type="{{ $invoice->type }}" data-total="{{ $invoice->totalDue() }}" @selected((int) old('invoice_id', $selectedInvoice) === $invoice->id)>
                            {{ $invoice->invoice_number }} · {{ $invoice->label() }} ({{ $invoice->periodLabel() }}) · {{ $invoice->rental?->user?->name }} · Kamar {{ $invoice->rental?->room?->room_number }} · {{ rupiah($invoice->totalDue()) }}{{ $invoice->late_fee > 0 ? ' (termasuk denda '.rupiah($invoice->late_fee).')' : '' }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label>Kontrak penghuni
                <select name="rental_id" id="rental_id" required>
                    <option value="">Pilih penghuni / kamar</option>
                    @foreach ($rentals as $rental)
                        <option value="{{ $rental->id }}" data-price="{{ (float) $rental->room->price }}" data-cycle="{{ $rental->billing_cycle }}" @selected((int) old('rental_id') === $rental->id)>{{ $rental->user->name }} · Kamar {{ $rental->room->room_number }} · {{ $rental->billingCycleLabel() }}</option>
                    @endforeach
                </select>
            </label>
            <label>Nominal pembayaran<input name="amount" id="amount" value="{{ old('amount') }}" type="number" min="0" step="0.01" required></label>
            <label data-months-field>Jumlah bulan sewa dibayar<input name="total_months_paid" id="total_months_paid" value="{{ old('total_months_paid', 1) }}" type="number" min="1" max="12" required></label>
            <label>Tanggal pembayaran<input name="payment_date" value="{{ old('payment_date', now()->format('Y-m-d')) }}" type="date" max="{{ now()->format('Y-m-d') }}" required></label>
            <label style="grid-column:1/-1">Bukti pembayaran<input name="proof_image" type="file" accept="image/*"></label>
        </div>
        <div class="form-actions"><a class="button button-secondary" href="{{ route('billing') }}">Batal</a><button class="button button-primary" type="submit">Simpan &amp; Setujui Pembayaran</button></div>
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
                const isCharge = inv?.dataset.type === 'charge';
                document.querySelector('[data-months-field]').hidden = isCharge;
                if (isCharge) { months.value = 1; amount.value = Number(inv.dataset.total); return; }
                // Perkiraan: tagihan terpilih + sewa bulanan untuk sisa bulan (nominal final tetap bisa diubah admin).
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

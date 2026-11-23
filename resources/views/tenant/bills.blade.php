@extends('layouts.tenant')

@section('title', 'Tagihan & Pembayaran')

@section('content')
    <div class="tenant-welcome"><div><span class="eyebrow">PORTAL PENGHUNI / TAGIHAN</span><h1>Tagihan & Pembayaran</h1><p>Transfer biaya sewa atau tagihan lain, lalu unggah bukti transfer untuk divalidasi pengelola.</p></div></div>

    <section>
        <article class="panel settings-form" style="max-width:820px">
            <div class="panel-heading"><div><h2>Kirim Bukti Pembayaran</h2><p>Format JPG/PNG, maksimal 2 MB.</p></div></div>
            @if (! $rental)
                <p class="empty-note">Anda belum memiliki kontrak sewa aktif sehingga belum dapat melakukan pembayaran.</p>
            @elseif ($hasPendingPayment)
                <div class="flash-message">Pembayaran Anda sedang menunggu validasi admin. Anda dapat mengunggah bukti baru setelah pembayaran tersebut diverifikasi.</div>
            @else
                @php($rentPayable = $payable->where('type', 'rent'))
                @php($chargePayable = $payable->where('type', 'charge'))
                <form method="POST" action="{{ route('tenant.payments.store') }}" enctype="multipart/form-data">@csrf
                    <div class="form-grid" style="padding-top:0">
                        <label style="grid-column:1/-1">Tagihan yang dibayar
                            <select name="invoice_id" id="invoice_id">
                                @if ($rentPayable->isNotEmpty())
                                    <optgroup label="Sewa kamar">
                                        @foreach ($rentPayable as $invoice)
                                            <option value="{{ $invoice->id }}" data-type="rent" @selected((int) old('invoice_id', $selectedInvoice) === $invoice->id)>{{ $invoice->label() }} · {{ $invoice->periodLabel() }} · {{ rupiah($invoice->totalDue()) }}{{ $invoice->late_fee > 0 ? ' (termasuk denda '.rupiah($invoice->late_fee).')' : '' }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                                @if ($chargePayable->isNotEmpty())
                                    <optgroup label="Tagihan lain">
                                        @foreach ($chargePayable as $invoice)
                                            <option value="{{ $invoice->id }}" data-type="charge" @selected((int) old('invoice_id', $selectedInvoice) === $invoice->id)>{{ $invoice->label() }} · {{ $invoice->periodLabel() }} · {{ rupiah($invoice->totalDue()) }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                                <option value="" data-type="rent" @selected($payable->isEmpty())>{{ $rentPayable->isEmpty() ? 'Bayar sewa di muka (periode berikutnya)' : 'Sewa: otomatis ke tagihan terlama' }}</option>
                            </select>
                        </label>
                        <label data-months-field>Jumlah bulan sewa dibayar
                            <select name="total_months_paid" id="total_months_paid" required>
                                @foreach ($monthOptions as $months)
                                    <option value="{{ $months }}" @selected((int) old('total_months_paid', $monthOptions[0]) === $months)>{{ $months }} bulan</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Nominal transfer<input id="amount_preview" value="" readonly></label>
                        <label style="grid-column:1/-1">Bukti transfer<input name="proof_image" type="file" accept=".jpg,.jpeg,.png,image/jpeg,image/png" required></label>
                    </div>
                    <p class="empty-note">Siklus bayar sewa Anda: <b>{{ $rental->billingCycleLabel() }}</b>. Nominal dihitung sistem: tagihan yang tercakup (termasuk denda sewa bila terlambat), ditambah sewa periode berikutnya bila bayar di muka. Tagihan lain dibayar sesuai nominal tagihannya.</p>
                    <div class="form-actions"><button class="button button-primary" type="submit"><span class="material-symbols-outlined">upload</span>Kirim Bukti Pembayaran</button></div>
                </form>
                <script>
                    (() => {
                        const invoice = document.getElementById('invoice_id');
                        const months = document.getElementById('total_months_paid');
                        const monthsField = document.querySelector('[data-months-field]');
                        const preview = document.getElementById('amount_preview');
                        const amounts = @json($amountPreview);
                        const fmt = (n) => 'Rp ' + Math.round(n).toLocaleString('id-ID');
                        const recalc = () => {
                            const isCharge = invoice.selectedOptions[0]?.dataset.type === 'charge';
                            monthsField.hidden = isCharge;
                            const byMonth = amounts[invoice.value || 'auto'] || {};
                            const value = isCharge ? Object.values(byMonth)[0] : byMonth[months.value];
                            preview.value = value !== undefined ? fmt(value) : '-';
                        };
                        invoice.addEventListener('change', recalc);
                        months.addEventListener('change', recalc);
                        recalc();
                    })();
                </script>
            @endif
        </article>

    </section>

    <section class="finance-section">
        <article class="panel table-panel">
            <div class="panel-heading"><div><h2>Riwayat Pembayaran</h2><p>Status validasi bukti transfer Anda</p></div></div>
            <div class="table-scroll">
                <table>
                    <thead><tr><th>Tanggal</th><th>Nominal</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse ($payments as $payment)
                        <tr>
                            <td>{{ $payment->payment_date?->translatedFormat('d M Y') }}<small>{{ $payment->invoice && ! $payment->invoice->isRent() ? $payment->invoice->label() : $payment->total_months_paid.' bulan sewa' }}{{ $payment->invoice ? ' · '.$payment->invoice->invoice_number : '' }}</small></td>
                            <td><b>{{ rupiah($payment->amount) }}</b>@if ($payment->proof_image)<small><a class="text-link" href="{{ asset('storage/'.$payment->proof_image) }}" target="_blank" rel="noopener">Lihat bukti</a></small>@endif</td>
                            <td><span class="status-badge {{ $payment->status === 'approved' ? 'available' : ($payment->status === 'rejected' ? 'occupied' : 'maintenance') }}"><i></i>{{ ['pending' => 'Menunggu Validasi', 'approved' => 'Lunas', 'rejected' => 'Ditolak'][$payment->status] ?? $payment->status }}</span>@if ($payment->status === 'rejected' && $payment->admin_notes)<small class="danger-text">Alasan: {{ $payment->admin_notes }} — silakan unggah ulang bukti yang sah.</small>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="3">Belum ada pembayaran.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </article>
    </section>

    <section class="panel table-panel finance-section">
        <div class="panel-heading"><div><h2>Daftar Tagihan</h2><p>Tagihan sewa dan tagihan lain (gas, iuran, dll.) yang diterbitkan pengelola</p></div></div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>No. Tagihan</th><th>Keterangan</th><th>Periode</th><th>Jatuh tempo</th><th>Nominal</th><th>Denda</th><th>Total</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($invoices as $invoice)
                    <tr>
                        <td><b>{{ $invoice->invoice_number }}</b><small>Kamar {{ $invoice->rental?->room?->room_number }}</small></td>
                        <td><span class="type-chip {{ $invoice->type }}">{{ $invoice->label() }}</span></td>
                        <td>{{ $invoice->periodLabel() }}</td>
                        <td>{{ $invoice->due_date->translatedFormat('d M Y') }}</td>
                        <td>{{ rupiah($invoice->amount) }}</td>
                        <td>{{ $invoice->late_fee > 0 ? rupiah($invoice->late_fee) : '-' }}</td>
                        <td><b>{{ rupiah($invoice->totalDue()) }}</b></td>
                        <td><span class="status-badge {{ $invoice->statusTone() }}"><i></i>{{ $invoice->statusLabel() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="8">Belum ada tagihan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

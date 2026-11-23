@extends('layouts.tenant')

@section('title', 'Tagihan & Pembayaran')

@section('content')
    <div class="tenant-welcome"><div><span class="eyebrow">PORTAL PENGHUNI / TAGIHAN</span><h1>Tagihan & Pembayaran</h1><p>Transfer biaya sewa, lalu unggah bukti transfer untuk divalidasi pengelola.</p></div></div>

    <section>
        <article class="panel settings-form" style="max-width:820px">
            <div class="panel-heading"><div><h2>Kirim Bukti Pembayaran</h2><p>Format JPG/PNG, maksimal 2 MB.</p></div></div>
            @if (! $rental)
                <p class="empty-note">Anda belum memiliki kontrak sewa aktif sehingga belum dapat melakukan pembayaran.</p>
            @elseif ($hasPendingPayment)
                <div class="flash-message">Pembayaran Anda sedang menunggu validasi admin. Anda dapat mengunggah bukti baru setelah pembayaran tersebut diverifikasi.</div>
            @else
                <form method="POST" action="{{ route('tenant.payments.store') }}" enctype="multipart/form-data">@csrf
                    <div class="form-grid" style="padding-top:0">
                        <label style="grid-column:1/-1">Tagihan yang dibayar
                            <select name="invoice_id" id="invoice_id">
                                @foreach ($payable as $invoice)
                                    <option value="{{ $invoice->id }}" data-total="{{ $invoice->totalDue() }}" @selected((int) old('invoice_id', $selectedInvoice) === $invoice->id)>{{ $invoice->billing_month->translatedFormat('F Y') }} · {{ rupiah($invoice->totalDue()) }}{{ $invoice->late_fee > 0 ? ' (termasuk denda '.rupiah($invoice->late_fee).')' : '' }}</option>
                                @endforeach
                                <option value="" data-total="" @selected($payable->isEmpty())>{{ $payable->isEmpty() ? 'Bayar di muka (bulan berikutnya)' : 'Otomatis ke tagihan terlama' }}</option>
                            </select>
                        </label>
                        <label>Durasi bulan pembayaran
                            <select name="total_months_paid" id="total_months_paid" required>
                                @foreach ([1, 2, 3, 6, 12] as $months)
                                    <option value="{{ $months }}" @selected((int) old('total_months_paid', 1) === $months)>{{ $months }} bulan</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Perkiraan nominal transfer<input id="amount_preview" value="" readonly></label>
                        <label style="grid-column:1/-1">Bukti transfer<input name="proof_image" type="file" accept=".jpg,.jpeg,.png,image/jpeg,image/png" required></label>
                    </div>
                    <p class="empty-note">Nominal akhir dihitung sistem: tagihan yang tercakup (termasuk denda bila terlambat) ditambah sewa {{ rupiah($rental->room->price) }} untuk setiap bulan bayar di muka.</p>
                    <div class="form-actions"><button class="button button-primary" type="submit"><span class="material-symbols-outlined">upload</span>Kirim Bukti Pembayaran</button></div>
                </form>
                <script>
                    (() => {
                        const invoice = document.getElementById('invoice_id');
                        const months = document.getElementById('total_months_paid');
                        const preview = document.getElementById('amount_preview');
                        const price = {{ (float) $rental->room->price }};
                        const totals = @json($payable->map(fn ($i) => ['id' => $i->id, 'total' => $i->totalDue()])->values());
                        const fmt = (n) => 'Rp ' + Math.round(n).toLocaleString('id-ID');
                        const recalc = () => {
                            const n = Number(months.value) || 1;
                            const selected = Number(invoice.value) || null;
                            const ordered = selected ? [...totals.filter(t => t.id === selected), ...totals.filter(t => t.id !== selected)] : totals;
                            const covered = ordered.slice(0, n);
                            preview.value = fmt(covered.reduce((s, t) => s + Number(t.total), 0) + price * Math.max(0, n - covered.length));
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
                            <td>{{ $payment->payment_date?->translatedFormat('d M Y') }}<small>{{ $payment->total_months_paid }} bulan{{ $payment->invoice ? ' · '.$payment->invoice->invoice_number : '' }}</small></td>
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
        <div class="panel-heading"><div><h2>Daftar Tagihan</h2><p>Tagihan bulanan diterbitkan otomatis oleh sistem</p></div></div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>No. Tagihan</th><th>Periode</th><th>Jatuh tempo</th><th>Sewa</th><th>Denda</th><th>Total</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($invoices as $invoice)
                    <tr>
                        <td><b>{{ $invoice->invoice_number }}</b><small>Kamar {{ $invoice->rental?->room?->room_number }}</small></td>
                        <td>{{ $invoice->billing_month->translatedFormat('F Y') }}</td>
                        <td>{{ $invoice->due_date->translatedFormat('d M Y') }}</td>
                        <td>{{ rupiah($invoice->amount) }}</td>
                        <td>{{ $invoice->late_fee > 0 ? rupiah($invoice->late_fee) : '-' }}</td>
                        <td><b>{{ rupiah($invoice->totalDue()) }}</b></td>
                        <td><span class="status-badge {{ $invoice->statusTone() }}"><i></i>{{ $invoice->statusLabel() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7">Belum ada tagihan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

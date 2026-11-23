@extends('layouts.app')

@section('content')
    @include('components.page-heading')
    @include('components.form-errors')

    <section class="stats-grid">
        @include('components.stat-card', ['icon' => 'task_alt', 'tone' => 'green', 'label' => 'Diterima Bulan Ini', 'value' => rupiah_short($summary['paid_this_month']), 'note' => 'Pembayaran disetujui '.$month->translatedFormat('M Y')])
        @include('components.stat-card', ['icon' => 'schedule', 'tone' => 'orange', 'label' => 'Menunggu Validasi', 'value' => rupiah_short($summary['pending_validation']), 'note' => $summary['pending_validation_count'].' pembayaran perlu ditinjau', 'trendTone' => $summary['pending_validation_count'] ? 'warning' : ''])
        @include('components.stat-card', ['icon' => 'receipt_long', 'tone' => 'blue', 'label' => 'Belum Dibayar', 'value' => rupiah_short($summary['unpaid']), 'note' => 'Tagihan belum jatuh tempo'])
        @include('components.stat-card', ['icon' => 'warning', 'tone' => 'red', 'label' => 'Tunggakan + Denda', 'value' => rupiah_short($summary['overdue']), 'note' => $summary['overdue_count'].' tagihan · denda '.rupiah_short($summary['late_fees']), 'trendTone' => $summary['overdue_count'] ? 'warning' : ''])
    </section>

    <section class="panel table-panel finance-section">
        <div class="panel-heading">
            <div><h2>Tagihan {{ $month->translatedFormat('F Y') }}</h2><p>Dibuat otomatis tiap bulan · jatuh tempo tgl {{ config('billing.due_day') }} · denda {{ rupiah(config('billing.late_fee_per_day')) }}/hari (maks. {{ config('billing.late_fee_max_percent') }}% sewa)</p></div>
            <form class="filter-bar" method="GET" action="{{ route('billing') }}">
                <input type="month" name="month" value="{{ $month->format('Y-m') }}" onchange="this.form.submit()" aria-label="Bulan tagihan">
                @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
                <a class="button button-secondary" href="{{ route('billing.export', ['month' => $month->format('Y-m')]) }}"><span class="material-symbols-outlined">download</span>Export CSV</a>
            </form>
        </div>
        <div class="tabs finance-tabs">
            <a class="tab {{ ! $status ? 'active' : '' }}" href="{{ route('billing', ['month' => $month->format('Y-m')]) }}">Semua <b>{{ $statusCounts->sum() }}</b></a>
            @foreach (\App\Models\Invoice::STATUS_LABELS as $key => $label)
                <a class="tab {{ $status === $key ? 'active' : '' }}" href="{{ route('billing', ['month' => $month->format('Y-m'), 'status' => $key]) }}">{{ $label }} <b>{{ $statusCounts[$key] ?? 0 }}</b></a>
            @endforeach
        </div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>No. Tagihan</th><th>Penghuni</th><th>Kamar</th><th>Jatuh Tempo</th><th>Sewa</th><th>Denda</th><th>Total</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($invoices as $invoice)
                    <tr>
                        <td><b>{{ $invoice->invoice_number }}</b><small>{{ $invoice->billing_month->translatedFormat('F Y') }}</small></td>
                        <td><b>{{ $invoice->rental?->user?->name ?? '-' }}</b><small>{{ $invoice->rental?->user?->email ?? '-' }}</small></td>
                        <td>{{ $invoice->rental?->room?->room_number ?? '-' }}</td>
                        <td>{{ $invoice->due_date->format('d M Y') }}@if ($invoice->status === 'overdue')<small class="danger-text">Terlambat {{ $invoice->daysOverdue() }} hari</small>@elseif ($invoice->paid_at)<small>Lunas {{ $invoice->paid_at->format('d M Y') }}</small>@endif</td>
                        <td>{{ rupiah($invoice->amount) }}</td>
                        <td class="{{ $invoice->late_fee > 0 ? 'danger-text' : '' }}">{{ $invoice->late_fee > 0 ? rupiah($invoice->late_fee) : '-' }}</td>
                        <td><b>{{ rupiah($invoice->totalDue()) }}</b></td>
                        <td><span class="status-badge {{ $invoice->statusTone() }}"><i></i>{{ $invoice->statusLabel() }}</span></td>
                        <td>@if (in_array($invoice->status, ['unpaid', 'overdue']))<a class="table-action" href="{{ route('billing.create', ['invoice' => $invoice->id]) }}">Catat bayar</a>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="9">Belum ada tagihan untuk periode ini. Pastikan ada kontrak sewa aktif, lalu klik <b style="display:inline">Generate Tagihan</b>.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="finance-grid finance-section">
        <article class="panel table-panel">
            <div class="panel-heading"><div><h2>Pembayaran Masuk</h2><p>Validasi bukti pembayaran dari penghuni.</p></div></div>
            <div class="table-scroll">
                <table>
                    <thead><tr><th>Penghuni</th><th>Tagihan</th><th>Nominal</th><th>Tanggal</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                    @forelse ($payments as $payment)
                        <tr>
                            <td><b>{{ $payment->rental?->user?->name ?? '-' }}</b><small>Kamar {{ $payment->rental?->room?->room_number ?? '-' }}</small></td>
                            <td>{{ $payment->invoice?->invoice_number ?? '-' }}<small>{{ $payment->total_months_paid }} bulan</small></td>
                            <td><b>{{ rupiah($payment->amount) }}</b>@if ($payment->proof_image)<small><a class="text-link" href="{{ asset('storage/'.$payment->proof_image) }}" target="_blank" rel="noopener">Lihat bukti</a></small>@endif</td>
                            <td>{{ $payment->payment_date?->format('d M Y') }}</td>
                            <td><span class="status-badge {{ $payment->status === 'approved' ? 'available' : ($payment->status === 'rejected' ? 'occupied' : 'maintenance') }}"><i></i>{{ ['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'][$payment->status] ?? $payment->status }}</span>@if ($payment->admin_notes)<small>{{ $payment->admin_notes }}</small>@endif</td>
                            <td>
                                @if ($payment->status === 'pending')
                                    <div class="verify-actions">
                                        <form method="POST" action="{{ route('billing.payments.approve', $payment) }}">@csrf @method('PATCH')<button class="table-action" type="submit">Setujui</button></form>
                                        <details class="reject-box" @if ($errors->has('admin_notes') && (int) old('payment_id') === $payment->id) open @endif><summary class="table-action danger-text">Tolak</summary><form method="POST" action="{{ route('billing.payments.reject', $payment) }}">@csrf @method('PATCH')<input type="hidden" name="payment_id" value="{{ $payment->id }}"><textarea name="admin_notes" rows="2" maxlength="500" minlength="5" placeholder="Alasan penolakan (wajib)" required aria-label="Alasan penolakan">{{ (int) old('payment_id') === $payment->id ? old('admin_notes') : '' }}</textarea><button class="table-action danger-text" type="submit">Kirim penolakan</button></form></details>
                                    </div>
                                @else
                                    <small>-</small>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">Belum ada pembayaran tersimpan.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <aside class="panel arrears-panel">
            <div class="panel-heading"><div><h2>Tunggakan Penghuni</h2><p>Akumulasi tagihan yang belum lunas</p></div></div>
            <div class="arrears-list">
                @forelse ($arrears as $row)
                    <div class="arrears-row">
                        <span class="activity-avatar {{ $row['late_fee'] > 0 ? 'orange' : 'blue' }}"><span class="material-symbols-outlined">person</span></span>
                        <div>
                            <strong>{{ $row['rental']?->user?->name ?? '-' }}</strong>
                            <p>Kamar {{ $row['rental']?->room?->room_number ?? '-' }} · {{ $row['months'] }} bulan sejak {{ \Illuminate\Support\Carbon::parse($row['oldest'])->translatedFormat('M Y') }}</p>
                        </div>
                        <div class="arrears-amount"><b>{{ rupiah($row['total']) }}</b>@if ($row['late_fee'] > 0)<small class="danger-text">denda {{ rupiah($row['late_fee']) }}</small>@endif</div>
                    </div>
                @empty
                    <p class="empty-note">Tidak ada tunggakan. Semua tagihan sudah lunas.</p>
                @endforelse
            </div>
        </aside>
    </section>
@endsection

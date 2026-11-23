@extends('layouts.app')

@section('content')
    @include('components.page-heading')
    @include('components.form-errors')

    <section class="panel table-panel">
        <div class="panel-heading"><div><h2>Tagihan Lain</h2><p>Biaya di luar sewa kamar, seperti gas, iuran kebersihan, atau biaya patungan. Bisa sekali tagih atau berulang tiap bulan, dengan nominal per penghuni atau total yang dibagi rata.</p></div></div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Tagihan</th><th>Nominal</th><th>Ditagihkan ke</th><th>Jadwal</th><th>Terbit / lunas</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                @forelse ($charges as $charge)
                    <tr data-search="{{ strtolower($charge->title.' '.$charge->categoryLabel()) }}">
                        <td><b>{{ $charge->title }}</b><small>{{ $charge->categoryLabel() }}{{ $charge->description ? ' · '.\Illuminate\Support\Str::limit($charge->description, 60) : '' }}</small></td>
                        <td><b>{{ $charge->amountLabel() }}</b><small>{{ \App\Models\Charge::AMOUNT_MODES[$charge->amount_mode] }}</small></td>
                        <td>{{ $charge->targetLabel() }}</td>
                        <td>{{ $charge->scheduleLabel() }}<small>Jatuh tempo tgl {{ $charge->due_day }}</small></td>
                        <td><b>{{ $charge->paid_count }} / {{ $charge->invoices_count }}</b> lunas<small>{{ rupiah($charge->paid_sum ?? 0) }} dari {{ rupiah($charge->invoices_sum_amount ?? 0) }}</small></td>
                        <td><span class="status-badge {{ $charge->is_active ? 'available' : 'neutral' }}"><i></i>{{ $charge->statusLabel() }}</span></td>
                        <td>
                            <div class="row-actions">
                                @if ($charge->is_active && $charge->is_recurring)
                                    <form method="POST" action="{{ route('billing.charges.stop', $charge) }}" onsubmit="return confirm('Hentikan tagihan berulang &quot;{{ addslashes($charge->title) }}&quot;? Tagihan yang sudah terbit tetap berlaku.')">@csrf @method('PATCH')<button class="table-action" type="submit">Hentikan</button></form>
                                @endif
                                @if ($charge->paid_count === 0)
                                    <form method="POST" action="{{ route('billing.charges.destroy', $charge) }}" onsubmit="return confirm('Hapus tagihan &quot;{{ addslashes($charge->title) }}&quot; beserta invoice yang belum dibayar?')">@csrf @method('DELETE')<button class="icon-action danger" type="submit" title="Hapus" aria-label="Hapus"><span class="material-symbols-outlined">delete</span></button></form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">Belum ada tagihan lain. Klik <b style="display:inline">Buat Tagihan Lain</b> untuk menagih gas, iuran kebersihan, atau biaya patungan lainnya.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

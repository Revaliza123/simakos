@extends('layouts.app')

@section('content')
    @include('components.page-heading')
    @include('components.form-errors')

    @php
        $net = $monthIncome - $monthTotal;
        $maxBar = max(1, $report->max(fn ($r) => max($r['income'], $r['expense'])));
    @endphp

    <section class="stats-grid">
        @include('components.stat-card', ['icon' => 'savings', 'tone' => 'green', 'label' => 'Pemasukan '.$month->translatedFormat('M Y'), 'value' => rupiah_short($monthIncome), 'note' => 'Pembayaran sewa disetujui'])
        @include('components.stat-card', ['icon' => 'shopping_cart', 'tone' => 'red', 'label' => 'Pengeluaran '.$month->translatedFormat('M Y'), 'value' => rupiah_short($monthTotal), 'note' => $expenses->count().' transaksi operasional'])
        @include('components.stat-card', ['icon' => 'account_balance', 'tone' => $net >= 0 ? 'blue' : 'orange', 'label' => 'Laba Bersih Bulan Ini', 'value' => rupiah_short($net), 'note' => $net >= 0 ? 'Arus kas positif' : 'Pengeluaran melebihi pemasukan', 'trendTone' => $net < 0 ? 'warning' : ''])
        @include('components.stat-card', ['icon' => 'query_stats', 'tone' => 'purple', 'label' => 'Laba Bersih '.$year, 'value' => rupiah_short($yearTotals['net']), 'note' => 'Margin '.($yearTotals['income'] > 0 ? number_format($yearTotals['net'] / $yearTotals['income'] * 100, 1, ',', '.') : 0).'% dari pemasukan'])
    </section>

    <section class="panel finance-section">
        <div class="panel-heading">
            <div><h2>Laporan Pemasukan vs Pengeluaran {{ $year }}</h2><p>Rekap bulanan pembayaran sewa yang diterima dan biaya operasional.</p></div>
            <form class="filter-bar" method="GET" action="{{ route('expenses') }}">
                <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                <select name="year" onchange="this.form.submit()" aria-label="Tahun laporan">
                    @foreach (range(now()->year - 3, now()->year + 1) as $y)<option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>@endforeach
                </select>
                <a class="button button-secondary" href="{{ route('expenses.report.export', ['year' => $year]) }}"><span class="material-symbols-outlined">download</span>Export Laporan</a>
            </form>
        </div>
        <div class="finance-chart" role="img" aria-label="Grafik pemasukan dan pengeluaran per bulan {{ $year }}">
            @foreach ($report as $row)
                <div class="finance-chart-col" title="{{ $row['label'] }}: pemasukan {{ rupiah($row['income']) }}, pengeluaran {{ rupiah($row['expense']) }}">
                    <div class="finance-bars">
                        <span class="bar income" style="height: {{ $row['income'] / $maxBar * 100 }}%"></span>
                        <span class="bar expense" style="height: {{ $row['expense'] / $maxBar * 100 }}%"></span>
                    </div>
                    <small>{{ $row['label'] }}</small>
                </div>
            @endforeach
        </div>
        <div class="legend finance-legend"><span><i class="dot green"></i>Pemasukan <b>{{ rupiah($yearTotals['income']) }}</b></span><span><i class="dot red"></i>Pengeluaran <b>{{ rupiah($yearTotals['expense']) }}</b></span><span><i class="dot blue"></i>Total ditagihkan <b>{{ rupiah($yearTotals['billed']) }}</b></span></div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Bulan</th><th>Ditagihkan</th><th>Pemasukan</th><th>Pengeluaran</th><th>Laba Bersih</th><th>Tingkat Koleksi</th></tr></thead>
                <tbody>
                @foreach ($report as $row)
                    <tr class="{{ $row['month'] === $month->month && $year === $month->year ? 'is-current' : '' }}">
                        <td><a class="text-link" href="{{ route('expenses', ['year' => $year, 'month' => sprintf('%d-%02d', $year, $row['month'])]) }}">{{ \Illuminate\Support\Carbon::create($year, $row['month'], 1)->translatedFormat('F') }}</a></td>
                        <td>{{ rupiah($row['billed']) }}</td>
                        <td class="green-text">{{ rupiah($row['income']) }}</td>
                        <td class="danger-text">{{ rupiah($row['expense']) }}</td>
                        <td><b class="{{ $row['net'] < 0 ? 'danger-text' : '' }}">{{ rupiah($row['net']) }}</b></td>
                        <td>{{ $row['billed'] > 0 ? number_format(min($row['income'] / $row['billed'], 1) * 100, 0).'%' : '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr><th>Total {{ $year }}</th><th>{{ rupiah($yearTotals['billed']) }}</th><th>{{ rupiah($yearTotals['income']) }}</th><th>{{ rupiah($yearTotals['expense']) }}</th><th>{{ rupiah($yearTotals['net']) }}</th><th></th></tr></tfoot>
            </table>
        </div>
    </section>

    <section class="finance-grid finance-section">
        <article class="panel table-panel">
            <div class="panel-heading">
                <div><h2>Pengeluaran {{ $month->translatedFormat('F Y') }}</h2><p>Biaya operasional kost yang tercatat.</p></div>
                <form class="filter-bar" method="GET" action="{{ route('expenses') }}">
                    <input type="hidden" name="year" value="{{ $year }}">
                    <input type="month" name="month" value="{{ $month->format('Y-m') }}" onchange="this.form.submit()" aria-label="Bulan pengeluaran">
                    <select name="category" onchange="this.form.submit()" aria-label="Kategori">
                        <option value="">Semua kategori</option>
                        @foreach ($categories as $key => $label)<option value="{{ $key }}" @selected($category === $key)>{{ $label }}</option>@endforeach
                    </select>
                </form>
            </div>
            <div class="table-scroll">
                <table>
                    <thead><tr><th>Tanggal</th><th>Keterangan</th><th>Kategori</th><th>Nominal</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($expenses as $expense)
                        <tr>
                            <td>{{ $expense->expense_date->format('d M Y') }}</td>
                            <td><b>{{ $expense->title }}</b>@if ($expense->description)<small>{{ $expense->description }}</small>@endif</td>
                            <td><span class="category-chip">{{ $expense->categoryLabel() }}</span></td>
                            <td><b>{{ rupiah($expense->amount) }}</b></td>
                            <td>
                                <form method="POST" action="{{ route('expenses.destroy', $expense) }}" onsubmit="return confirm('Hapus pengeluaran ini?')">@csrf @method('DELETE')<button class="table-action danger-text" type="submit">Hapus</button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">Belum ada pengeluaran di periode ini.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <aside class="panel arrears-panel">
            <div class="panel-heading"><div><h2>Per Kategori</h2><p>Komposisi pengeluaran {{ $month->translatedFormat('M Y') }}</p></div></div>
            <div class="category-list">
                @forelse ($byCategory as $key => $amount)
                    <div class="category-row">
                        <div class="category-row-head"><span>{{ $categories[$key] ?? ucfirst($key) }}</span><b>{{ rupiah($amount) }}</b></div>
                        <div class="progress"><span style="width: {{ $monthTotal > 0 ? $amount / $monthTotal * 100 : 0 }}%"></span></div>
                        <small>{{ $monthTotal > 0 ? number_format($amount / $monthTotal * 100, 1, ',', '.') : 0 }}% dari total</small>
                    </div>
                @empty
                    <p class="empty-note">Belum ada data pengeluaran.</p>
                @endforelse
            </div>
        </aside>
    </section>
@endsection

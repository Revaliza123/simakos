@extends('layouts.app')

@section('content')
    @include('components.page-heading')
    <section class="stats-grid">
        @include('components.stat-card', ['icon' => 'group', 'label' => 'Penghuni Aktif', 'value' => $stats['tenants'], 'note' => $stats['openComplaints'].' keluhan belum selesai'])
        @include('components.stat-card', ['icon' => 'meeting_room', 'label' => 'Total Kamar', 'value' => $stats['rooms'], 'note' => $stats['available'].' kamar tersedia', 'tone' => 'green'])
        @include('components.stat-card', ['icon' => 'payments', 'label' => 'Pendapatan Bulan Ini', 'value' => rupiah_short($finance['paid_this_month']), 'note' => 'Dari '.rupiah_short($finance['billed_this_month']).' yang ditagihkan', 'tone' => 'purple'])
        @include('components.stat-card', ['icon' => 'pending_actions', 'label' => 'Tagihan Tertunda', 'value' => rupiah_short($finance['unpaid'] + $finance['overdue']), 'note' => $finance['overdue_count'].' terlambat · '.$finance['pending_validation_count'].' perlu ditinjau', 'tone' => 'orange', 'trendTone' => 'warning'])
    </section>
    <section class="dashboard-grid">
        @php
            $count = count($trend['values']);
            $points = collect($trend['values'])->values()->map(fn ($v, $i) => [round($count > 1 ? $i * 700 / ($count - 1) : 0, 1), round(200 - ($v / $trend['max']) * 180, 1)]);
            $line = $points->map(fn ($p) => $p[0].','.$p[1])->join(' L');
            $occ = $stats['rooms'] > 0 ? $stats['occupied'] / $stats['rooms'] * 100 : 0;
            $avl = $stats['rooms'] > 0 ? $stats['available'] / $stats['rooms'] * 100 : 0;
        @endphp
        <article class="panel chart-panel"><div class="panel-heading"><div><h2>Tren Pemasukan</h2><p>Pembayaran disetujui selama 6 bulan terakhir</p></div><a class="select-control" href="{{ route('expenses') }}">Laporan <span class="material-symbols-outlined">arrow_forward</span></a></div><div class="chart"><div class="chart-y"><span>{{ rupiah_short($trend['max']) }}</span><span>{{ rupiah_short($trend['max'] * .75) }}</span><span>{{ rupiah_short($trend['max'] * .5) }}</span><span>{{ rupiah_short($trend['max'] * .25) }}</span><span>0</span></div><div class="chart-area"><div class="chart-lines"></div><svg viewBox="0 0 700 220" preserveAspectRatio="none" aria-label="Grafik pemasukan"><defs><linearGradient id="fillBlue" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#2563eb" stop-opacity=".22"/><stop offset="1" stop-color="#2563eb" stop-opacity="0"/></linearGradient></defs><path d="M{{ $line }} L700,220 L0,220 Z" fill="url(#fillBlue)"/><path d="M{{ $line }}" fill="none" stroke="#2563eb" stroke-width="3"/></svg><div class="chart-x">@foreach ($trend['labels'] as $label)<span>{{ $label }}</span>@endforeach</div></div></div></article>
        <article class="panel occupancy-panel"><div class="panel-heading"><div><h2>Status Okupansi</h2><p>Kondisi kamar saat ini (real-time)</p></div><span class="trend-badge">{{ $stats['occupancy'] }}% terisi</span></div><div class="donut-wrap"><div class="donut" style="background:conic-gradient(var(--blue) 0 {{ $occ }}%, var(--green) {{ $occ }}% {{ $occ + $avl }}%, var(--orange) {{ $occ + $avl }}% 100%)"><strong>{{ $stats['occupied'] }}</strong><small>dari {{ $stats['rooms'] }} kamar</small></div></div><div class="legend"><span><i class="dot blue"></i>Terisi <b>{{ $stats['occupied'] }}</b></span><span><i class="dot green"></i>Tersedia <b>{{ $stats['available'] }}</b></span><span><i class="dot orange"></i>Perbaikan <b>{{ $stats['maintenance'] }}</b></span></div></article>
    </section>
    <section class="panel activity-panel"><div class="panel-heading"><div><h2>Aktivitas Terbaru</h2><p>Transaksi sewa, pembayaran, dan laporan keluhan penghuni</p></div><a class="text-link" href="{{ route('billing') }}">Lihat semua <span class="material-symbols-outlined">arrow_forward</span></a></div><div class="activity-list">@forelse ($activities as $activity)<div class="activity-row"><span class="activity-avatar {{ $activity['tone'] }}"><span class="material-symbols-outlined">{{ $activity['icon'] }}</span></span><div><strong>{{ $activity['title'] }}</strong><p>{{ $activity['subtitle'] }}</p></div><time>{{ $activity['time']?->diffForHumans() }}</time><b class="amount muted">{{ $activity['value'] }}</b></div>@empty<p class="empty-note">Belum ada aktivitas.</p>@endforelse</div></section>
@endsection

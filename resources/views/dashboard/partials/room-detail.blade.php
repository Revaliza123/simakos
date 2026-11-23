{{-- Isi halaman detail kamar (dipakai oleh dashboard/room-show). --}}
@php($photos = $room->photos)
@php($facilities = $room->facilityList())
<div class="room-detail">

    <div class="room-detail-top">
        <div class="gallery">
            @if ($photos->isNotEmpty())
                <img class="gallery-main" src="{{ $photos->first()->url() }}" alt="Foto Kamar {{ $room->room_number }}" data-gallery-main>
                @if ($photos->count() > 1)
                    <div class="gallery-thumbs">
                        @foreach ($photos as $photo)
                            <button type="button" class="{{ $loop->first ? 'active' : '' }}" data-gallery-thumb="{{ $photo->url() }}" aria-label="Foto {{ $loop->iteration }}"><img src="{{ $photo->url() }}" alt="" loading="lazy"></button>
                        @endforeach
                    </div>
                @endif
            @else
                <div class="gallery-empty room-visual {{ $room->status }}">
                    <span class="material-symbols-outlined">add_photo_alternate</span>
                    <span>Belum ada foto. <a href="{{ route('rooms.edit', $room) }}">Unggah foto kamar</a></span>
                </div>
            @endif
        </div>

        <div class="spec-list">
            <div class="spec-price"><small>Harga sewa</small><strong>{{ rupiah($room->price) }}</strong><small>per bulan</small></div>
            <dl>
                <div><dt>Tipe</dt><dd>{{ $room->roomType?->name ?? '-' }}</dd></div>
                <div><dt>Lantai</dt><dd>{{ $room->floor !== null ? $room->floorLabel() : '-' }}</dd></div>
                <div><dt>Luas</dt><dd>{{ $room->sizeLabel() ?? '-' }}</dd></div>
                <div><dt>Disewa</dt><dd>{{ $stats['times_rented'] }} kali</dd></div>
                <div><dt>Total pendapatan</dt><dd>{{ rupiah($stats['income']) }}</dd></div>
                <div><dt>Keluhan</dt><dd>{{ $stats['complaints'] }}@if ($stats['open_complaints']) <span class="danger-text">({{ $stats['open_complaints'] }} belum selesai)</span>@endif</dd></div>
            </dl>
        </div>
    </div>

    @if ($room->description || $room->roomType?->description)
        <section class="detail-section">
            <h3>Deskripsi</h3>
            <p>{{ $room->description ?: $room->roomType->description }}</p>
        </section>
    @endif

    <section class="detail-section">
        <h3>Fasilitas</h3>
        @if ($facilities)
            <ul class="facility-chips large">
                @foreach ($facilities as $facility)
                    <li><span class="material-symbols-outlined">{{ $facility['icon'] }}</span>{{ $facility['label'] }}</li>
                @endforeach
            </ul>
        @else
            <p class="empty-note">Belum ada fasilitas yang dicatat.</p>
        @endif
    </section>

    <section class="detail-section">
        <h3>Penyewa Saat Ini</h3>
        @if ($rental)
            <div class="current-tenant">
                <div class="current-tenant-person">
                    <span class="tenant-icon"><span class="material-symbols-outlined">person</span></span>
                    <div>
                        <b>{{ $rental->user?->name ?? '-' }}</b>
                        <small>{{ $rental->user?->email }}</small>
                        @if ($rental->user?->phone_number)<small>{{ $rental->user->phone_number }}</small>@endif
                        @if ($rental->user?->emergency_contact)<small>Kontak darurat: {{ $rental->user->emergency_contact }}</small>@endif
                    </div>
                </div>
                <dl class="period-grid">
                    <div><dt>Mulai sewa</dt><dd>{{ $rental->start_date->translatedFormat('d M Y') }}</dd></div>
                    <div><dt>Berakhir</dt><dd>{{ $rental->endLabel() }}</dd></div>
                    <div><dt>Lama tinggal</dt><dd>{{ $rental->durationLabel() }}</dd></div>
                    <div><dt>Siklus bayar</dt><dd>{{ $rental->billingCycleLabel() }}</dd></div>
                    <div><dt>Sisa masa sewa</dt><dd class="{{ ($rental->remainingDays() ?? 99) <= 30 ? 'warning-text' : '' }}">{{ $rental->remainingLabel() ?? 'Tidak ditentukan' }}</dd></div>
                    <div><dt>Lunas sampai</dt><dd>{{ $paidThrough ? $paidThrough->translatedFormat('d M Y') : 'Belum ada pembayaran' }}</dd></div>
                    <div><dt>Tagihan belum lunas</dt><dd class="{{ $outstanding->isNotEmpty() ? 'danger-text' : '' }}">{{ $outstanding->isNotEmpty() ? $outstanding->count().' tagihan · '.rupiah($outstanding->sum(fn ($i) => $i->totalDue())) : 'Tidak ada' }}</dd></div>
                </dl>
                <div class="tenant-actions">
                <form class="cycle-form" method="POST" action="{{ route('rentals.billing-cycle', $rental) }}">@csrf @method('PATCH')
                    <select name="billing_cycle" aria-label="Siklus bayar">
                        @foreach (\App\Models\Rental::BILLING_CYCLES as $months => $label)
                            <option value="{{ $months }}" @selected((int) $rental->billing_cycle === $months)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button class="button button-secondary" type="submit" title="Berlaku untuk tagihan berikutnya"><span class="material-symbols-outlined">event_repeat</span>Ubah Siklus</button>
                </form>
                <form method="POST" action="{{ route('rentals.checkout', $rental) }}" onsubmit="return confirm('Proses check-out {{ addslashes($rental->user?->name ?? '') }} dari Kamar {{ $room->room_number }}?')">
                    @csrf @method('PATCH')
                    <button class="button button-secondary" type="submit"><span class="material-symbols-outlined">logout</span>Check-out Penghuni</button>
                </form>
                </div>
            </div>
        @elseif ($room->status === 'available')
            <p class="empty-note">Kamar kosong dan siap dihuni.</p>
        @else
            <p class="empty-note">Kamar sedang dalam perbaikan dan belum dapat disewa.</p>
        @endif
    </section>

    <section class="detail-section">
        <h3>Riwayat Penyewaan</h3>
        @if ($room->rentals->isNotEmpty())
            <div class="table-scroll">
                <table class="compact-table">
                    <thead><tr><th>Penyewa</th><th>Mulai</th><th>Selesai</th><th>Durasi</th><th>Status</th></tr></thead>
                    <tbody>
                    @foreach ($room->rentals as $history)
                        <tr>
                            <td><b>{{ $history->user?->name ?? '-' }}</b></td>
                            <td>{{ $history->start_date->translatedFormat('d M Y') }}</td>
                            <td>{{ $history->endLabel() }}</td>
                            <td>{{ $history->durationLabel() }}</td>
                            <td><span class="status-badge {{ $history->status === 'active' ? 'available' : ($history->status === 'cancelled' ? 'occupied' : 'neutral') }}"><i></i>{{ $history->statusLabel() }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="empty-note">Kamar ini belum pernah disewa.</p>
        @endif
    </section>

    @if ($complaints->isNotEmpty())
        <section class="detail-section">
            <h3>Keluhan Terbaru</h3>
            <ul class="complaint-mini">
                @foreach ($complaints as $complaint)
                    <li>
                        <span><b>{{ $complaint->title }}</b><small>{{ $complaint->rental?->user?->name }} · {{ $complaint->created_at->translatedFormat('d M Y') }}</small></span>
                        <span class="status-badge {{ $complaint->statusTone() }}"><i></i>{{ $complaint->statusLabel() }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

</div>

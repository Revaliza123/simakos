@extends('components.form-page')

@section('form-content')
    <div class="panel-heading"><div><h2>Buat Tagihan Lain</h2><p>Tagihan langsung muncul di portal penghuni dan dibayar dengan alur yang sama seperti sewa: unggah bukti, lalu divalidasi admin.</p></div></div>
    <form method="POST" action="{{ route('billing.charges.store') }}">@csrf
        <div class="form-grid">
            <label style="grid-column:1/-1">Nama tagihan<input name="title" value="{{ old('title') }}" maxlength="150" placeholder="mis. Isi ulang gas dapur bersama" required></label>
            <label>Kategori
                <select name="category" required>
                    @foreach ($categories as $key => $label)
                        <option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>Nominal (Rp)<input name="amount" id="charge_amount" value="{{ old('amount') }}" type="number" min="1000" step="500" placeholder="mis. 300000" required></label>

            <div style="grid-column:1/-1">
                <span class="field-label">Cara menghitung</span>
                <div class="choice-row">
                    @foreach (\App\Models\Charge::AMOUNT_MODES as $key => $label)
                        <label class="choice-card"><input type="radio" name="amount_mode" value="{{ $key }}" @checked(old('amount_mode', 'per_tenant') === $key)>
                            <span><b>{{ $label }}</b><small>{{ $key === 'split' ? 'mis. gas Rp 300.000 dibagi ke semua penghuni yang ditagih' : 'mis. iuran kebersihan Rp 25.000 untuk setiap penghuni' }}</small></span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div style="grid-column:1/-1">
                <span class="field-label">Ditagihkan ke</span>
                <div class="choice-row">
                    @foreach (\App\Models\Charge::TARGETS as $key => $label)
                        <label class="choice-card"><input type="radio" name="target" value="{{ $key }}" @checked(old('target', 'all') === $key)>
                            <span><b>{{ $label }}</b><small>{{ $key === 'all' ? $activeTenants.' penghuni aktif saat ini' : 'Pilih satu atau beberapa kamar' }}</small></span>
                        </label>
                    @endforeach
                </div>
                <div class="room-picker" data-room-picker>
                    @foreach ($rooms as $room)
                        <label class="facility-option">
                            <input type="checkbox" name="room_ids[]" value="{{ $room->id }}" @checked(in_array($room->id, old('room_ids', []))) @disabled(! $room->activeRental)>
                            <span><b>Kamar {{ $room->room_number }}</b><small>{{ $room->activeRental?->user?->name ?? 'Kosong' }}</small></span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div style="grid-column:1/-1">
                <span class="field-label">Jadwal</span>
                <div class="choice-row">
                    <label class="choice-card"><input type="radio" name="is_recurring" value="0" @checked(old('is_recurring', '0') === '0')><span><b>Sekali tagih</b><small>Diterbitkan satu kali pada bulan yang dipilih</small></span></label>
                    <label class="choice-card"><input type="radio" name="is_recurring" value="1" @checked(old('is_recurring') === '1')><span><b>Berulang tiap bulan</b><small>Diterbitkan otomatis setiap bulan sampai dihentikan</small></span></label>
                </div>
            </div>
            <label>Bulan tagihan / mulai<input name="start_month" type="month" value="{{ old('start_month', now()->format('Y-m')) }}" required></label>
            <label data-recurring-only>Berakhir (opsional)<input name="end_month" type="month" value="{{ old('end_month') }}"></label>
            <label>Jatuh tempo tanggal<input name="due_day" type="number" min="1" max="28" value="{{ old('due_day', config('billing.due_day')) }}" required></label>
            <label style="grid-column:1/-1">Keterangan untuk penghuni (opsional)<textarea name="description" rows="3" maxlength="1000" placeholder="mis. Tabung gas 12 kg untuk dapur bersama, dibagi rata ke semua penghuni">{{ old('description') }}</textarea></label>
        </div>
        <p class="empty-note" data-split-preview></p>
        <p class="empty-note">Tagihan lain tidak dikenai denda keterlambatan. Bila jatuh tempo bulan ini sudah lewat saat diterbitkan, jatuh tempo otomatis menjadi 7 hari dari hari ini.</p>
        <div class="form-actions"><a class="button button-secondary" href="{{ route('billing.charges') }}">Batal</a><button class="button button-primary" type="submit">Simpan &amp; Terbitkan</button></div>
    </form>
    <script>
        (() => {
            const form = document.currentScript.previousElementSibling;
            const picker = form.querySelector('[data-room-picker]');
            const endField = form.querySelector('[data-recurring-only]');
            const preview = form.querySelector('[data-split-preview]');
            const amount = form.querySelector('#charge_amount');
            const totalTenants = {{ $activeTenants }};
            const fmt = (n) => 'Rp ' + Math.floor(n).toLocaleString('id-ID');
            const sync = () => {
                const target = form.querySelector('[name=target]:checked')?.value;
                const mode = form.querySelector('[name=amount_mode]:checked')?.value;
                const recurring = form.querySelector('[name=is_recurring]:checked')?.value === '1';
                picker.hidden = target !== 'rooms';
                endField.hidden = !recurring;
                const count = target === 'rooms' ? picker.querySelectorAll('input:checked').length : totalTenants;
                const value = Number(amount.value) || 0;
                preview.textContent = !value || !count ? '' : (mode === 'split'
                    ? `Perkiraan: ${fmt(value)} dibagi ${count} penghuni ≈ ${fmt(value / count)} per penghuni.`
                    : `Perkiraan: ${count} penghuni × ${fmt(value)} = ${fmt(value * count)}.`);
            };
            form.addEventListener('change', sync);
            amount.addEventListener('input', sync);
            sync();
        })();
    </script>
@endsection

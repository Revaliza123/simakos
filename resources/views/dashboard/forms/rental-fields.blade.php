{{-- Field bersama check-in: hanya kamar berstatus Tersedia yang ditampilkan (UC-03 alt. flow 1a). --}}
<label>Kamar tersedia
    <select name="room_id" required>
        <option value="">Pilih kamar</option>
        @foreach ($rooms as $room)
            <option value="{{ $room->id }}" @selected((int) old('room_id', $selectedRoom) === $room->id)>Kamar {{ $room->room_number }} · {{ $room->roomType?->name ?? 'Tanpa tipe' }} · {{ rupiah($room->price) }}</option>
        @endforeach
    </select>
</label>
<label>Tanggal mulai sewa<input name="start_date" value="{{ old('start_date', now()->format('Y-m-d')) }}" type="date" required></label>
<label>Durasi sewa
    <select name="duration_months">
        <option value="">Tidak ditentukan (bulanan)</option>
        @foreach ([1, 3, 6, 12, 24] as $months)
            <option value="{{ $months }}" @selected((int) old('duration_months') === $months)>{{ $months }} bulan</option>
        @endforeach
    </select>
</label>
@if ($rooms->isEmpty())
    <p class="empty-note" style="grid-column:1/-1">Tidak ada kamar berstatus Tersedia saat ini.</p>
@endif

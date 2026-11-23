@extends('components.form-page')

@section('form-content')
    @php($editing = $room->exists)
    @php($typeFacilities = $room->roomType?->facilities ?? [])
    <div class="panel-heading"><div><h2>{{ $editing ? 'Ubah Kamar '.$room->room_number : 'Tambah Kamar' }}</h2><p>{{ $editing ? 'Perbarui spesifikasi, tarif, fasilitas, status, atau foto kamar.' : 'Masukkan data kamar baru beserta spesifikasi dan fotonya.' }}</p></div></div>
    <form method="POST" action="{{ $editing ? route('rooms.update', $room) : route('rooms.store') }}" enctype="multipart/form-data">@csrf
        @if ($editing) @method('PUT') @endif
        <div class="form-grid">
            <label>Nomor kamar<input name="room_number" value="{{ old('room_number', $room->room_number) }}" maxlength="10" required></label>
            <label>Tipe kamar
                <select name="room_type_id">
                    <option value="">Pilih tipe kamar</option>
                    @foreach ($roomTypes as $type)
                        <option value="{{ $type->id }}" @selected((int) old('room_type_id', $room->room_type_id) === $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Lantai<input name="floor" value="{{ old('floor', $room->floor) }}" type="number" min="0" max="50" placeholder="mis. 2"></label>
            <label>Luas kamar (m²)<input name="size_m2" value="{{ old('size_m2', $room->size_m2 !== null ? (float) $room->size_m2 : '') }}" type="number" min="1" max="999" step="0.5" placeholder="mis. 12"></label>
            <label>Harga per bulan<input name="price" value="{{ old('price', $room->price !== null ? (float) $room->price : '') }}" type="number" min="0" step="1000" required></label>
            <label>Status
                @if ($hasActiveRental)
                    <input value="Terisi (terkunci sampai check-out)" disabled>
                @else
                    <select name="status" required>
                        <option value="available" @selected(old('status', $room->status) === 'available')>Tersedia</option>
                        <option value="maintenance" @selected(old('status', $room->status) === 'maintenance')>Perbaikan</option>
                    </select>
                @endif
            </label>
            <label style="grid-column:1/-1">Deskripsi kamar<textarea name="description" rows="3" maxlength="2000" placeholder="mis. Menghadap taman, jendela besar, dekat tangga">{{ old('description', $room->description) }}</textarea></label>
            <div style="grid-column:1/-1">
                <span class="field-label">Fasilitas tambahan unit ini</span>
                <p class="field-hint">Fasilitas bawaan dari tipe kamar{{ $room->roomType ? ' ('.$room->roomType->name.')' : '' }} sudah tercentang otomatis. Centang fasilitas lain yang hanya ada di kamar ini.</p>
                @include('dashboard.forms.facility-picker', ['selected' => old('facilities', $room->facilities ?? []), 'inherited' => $typeFacilities])
            </div>
            <div style="grid-column:1/-1">
                <span class="field-label">Foto kamar</span>
                @if ($editing && $room->photos->isNotEmpty())
                    <p class="field-hint">Pilih foto sampul, atau centang <b>Hapus</b> untuk membuang foto.</p>
                    <div class="photo-manager">
                        @foreach ($room->photos as $photo)
                            <div class="photo-tile">
                                <img src="{{ $photo->url() }}" alt="Foto Kamar {{ $room->room_number }}" loading="lazy">
                                <label class="photo-choice"><input type="radio" name="cover_photo" value="{{ $photo->id }}" @checked($loop->first)> Sampul</label>
                                <label class="photo-choice danger-text"><input type="checkbox" name="delete_photos[]" value="{{ $photo->id }}"> Hapus</label>
                            </div>
                        @endforeach
                    </div>
                @endif
                <label class="upload-drop">
                    <span class="material-symbols-outlined">add_photo_alternate</span>
                    <span><b>Tambah foto</b> · JPG, PNG, atau WebP, maks. {{ (int) config('simakos.room_photos.max_kb') / 1024 }} MB per foto, total maks. {{ $maxPhotos }} foto per kamar</span>
                    <input name="photos[]" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple data-photo-input>
                </label>
                <div class="photo-preview" data-photo-preview></div>
            </div>
        </div>
        @if ($roomTypes->isEmpty())
            <p class="empty-note">Belum ada tipe kamar. <a class="text-link" href="{{ route('room-types.create') }}">Tambah tipe kamar</a></p>
        @endif
        <p class="empty-note">Status <b>Terisi</b> diatur otomatis oleh sistem saat check-in dan dikembalikan ke Tersedia saat check-out.</p>
        <div class="form-actions"><a class="button button-secondary" href="{{ route('rooms') }}">Batal</a><button class="button button-primary" type="submit">{{ $editing ? 'Simpan Perubahan' : 'Simpan Kamar' }}</button></div>
    </form>
    <script>
        // Pratinjau foto yang akan diunggah.
        (() => {
            const input = document.querySelector('[data-photo-input]');
            const preview = document.querySelector('[data-photo-preview]');
            input?.addEventListener('change', () => {
                preview.innerHTML = '';
                [...input.files].forEach((file) => {
                    const img = document.createElement('img');
                    img.src = URL.createObjectURL(file);
                    img.alt = file.name;
                    preview.appendChild(img);
                });
            });
        })();
    </script>
@endsection

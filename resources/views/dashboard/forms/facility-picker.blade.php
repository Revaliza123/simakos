{{-- Pilihan fasilitas (checkbox). $selected = kunci yang tercentang, $inherited = kunci bawaan tipe (terkunci). --}}
<div class="facility-picker">
    @foreach ($facilities as $key => [$label, $icon])
        @php($isInherited = in_array($key, $inherited ?? [], true))
        <label class="facility-option {{ $isInherited ? 'inherited' : '' }}" title="{{ $isInherited ? 'Sudah termasuk dari tipe kamar' : '' }}">
            <input type="checkbox" name="facilities[]" value="{{ $key }}" @checked($isInherited || in_array($key, $selected ?? [], true)) @disabled($isInherited)>
            <span class="material-symbols-outlined">{{ $icon }}</span>
            <span>{{ $label }}</span>
        </label>
    @endforeach
</div>

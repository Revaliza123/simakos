@extends('components.form-page')

@section('form-content')
    @php($editing = $announcement->exists)
    <div class="panel-heading"><div><h2>{{ $editing ? 'Ubah Pengumuman' : 'Buat Pengumuman' }}</h2><p>Pengumuman yang dipublikasikan tampil di beranda seluruh penghuni. Bisa dilengkapi gambar, video, atau link YouTube.</p></div></div>
    <form method="POST" action="{{ $editing ? route('announcements.update', $announcement) : route('announcements.store') }}" enctype="multipart/form-data">@csrf
        @if ($editing) @method('PUT') @endif
        <div class="form-grid">
            <label style="grid-column:1/-1">Judul pengumuman<input name="title" value="{{ old('title', $announcement->title) }}" maxlength="255" required></label>
            <label style="grid-column:1/-1">Isi pengumuman<textarea name="content" rows="7" maxlength="5000" required>{{ old('content', $announcement->content) }}</textarea></label>

            <div style="grid-column:1/-1">
                <span class="field-label">Gambar &amp; video</span>
                @if ($editing && $announcement->media->isNotEmpty())
                    <p class="field-hint">Centang <b>Hapus</b> untuk membuang lampiran.</p>
                    <div class="photo-manager">
                        @foreach ($announcement->media as $media)
                            <div class="photo-tile">
                                @if ($media->isVideo())
                                    <video src="{{ $media->url() }}" muted preload="metadata"></video>
                                @else
                                    <img src="{{ $media->url() }}" alt="{{ $media->original_name }}" loading="lazy">
                                @endif
                                <label class="photo-choice danger-text"><input type="checkbox" name="delete_media[]" value="{{ $media->id }}"> Hapus {{ $media->isVideo() ? 'video' : 'gambar' }}</label>
                            </div>
                        @endforeach
                    </div>
                @endif
                <label class="upload-drop">
                    <span class="material-symbols-outlined">perm_media</span>
                    <span><b>Tambah gambar atau video</b> · gambar JPG/PNG/WebP/GIF maks. {{ $mediaLimits['image_max_kb'] / 1024 }} MB, video MP4/WebM maks. {{ $mediaLimits['video_max_kb'] / 1024 }} MB · total maks. {{ $mediaLimits['max_files'] }} lampiran</span>
                    <input name="media[]" type="file" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm" multiple data-media-input>
                </label>
                <div class="photo-preview" data-media-preview></div>
            </div>

            <label style="grid-column:1/-1">Link video YouTube (opsional)
                <input name="video_url" type="url" value="{{ old('video_url', $announcement->video_url) }}" placeholder="mis. https://youtu.be/xxxxxxxxxxx">
                <small class="field-hint" style="margin:0">Untuk video yang panjang atau berukuran besar, unggah ke YouTube lalu tempel link-nya di sini.</small>
            </label>

            <label class="check-label" style="grid-column:1/-1"><input type="hidden" name="publish" value="0"><input name="publish" type="checkbox" value="1" @checked(old('publish', $announcement->published_at !== null))> Publikasikan ke portal penghuni</label>
        </div>
        <div class="form-actions"><a class="button button-secondary" href="{{ route('announcements') }}">Batal</a><button class="button button-primary" type="submit">{{ $editing ? 'Simpan Perubahan' : 'Simpan' }}</button></div>
    </form>
    <script>
        // Pratinjau lampiran sebelum diunggah.
        (() => {
            const input = document.querySelector('[data-media-input]');
            const preview = document.querySelector('[data-media-preview]');
            input?.addEventListener('change', () => {
                preview.innerHTML = '';
                [...input.files].forEach((file) => {
                    const el = document.createElement(file.type.startsWith('video/') ? 'video' : 'img');
                    el.src = URL.createObjectURL(file);
                    if (el.tagName === 'VIDEO') { el.muted = true; el.preload = 'metadata'; }
                    el.title = file.name;
                    preview.appendChild(el);
                });
            });
        })();
    </script>
@endsection

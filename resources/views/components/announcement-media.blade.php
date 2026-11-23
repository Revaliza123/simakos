{{--
    Lampiran pengumuman. $compact = true → satu baris thumbnail (daftar admin / beranda penghuni);
    false → galeri penuh dengan pemutar video dan embed YouTube.
--}}
@php($compact = $compact ?? false)
@php($items = $announcement->media)
@php($youtube = $announcement->youtubeEmbedUrl())
@if ($items->isNotEmpty() || $youtube)
    @if ($compact)
        <div class="media-strip">
            @foreach ($items->take(4) as $media)
                <a class="media-thumb" href="{{ $media->url() }}" target="_blank" rel="noopener" title="{{ $media->original_name }}">
                    @if ($media->isVideo())
                        <video src="{{ $media->url() }}#t=0.5" muted preload="metadata"></video><span class="media-play material-symbols-outlined">play_circle</span>
                    @else
                        <img src="{{ $media->url() }}" alt="" loading="lazy">
                    @endif
                </a>
            @endforeach
            @if ($youtube)
                <a class="media-thumb" href="{{ $announcement->video_url }}" target="_blank" rel="noopener" title="Tonton di YouTube">
                    <img src="{{ $announcement->youtubeThumbnail() }}" alt="" loading="lazy"><span class="media-play material-symbols-outlined">smart_display</span>
                </a>
            @endif
            @if ($items->count() > 4)
                <span class="media-more">+{{ $items->count() - 4 }}</span>
            @endif
        </div>
    @else
        <div class="media-gallery {{ $items->count() + ($youtube ? 1 : 0) === 1 ? 'single' : '' }}">
            @foreach ($items as $media)
                @if ($media->isVideo())
                    <video controls preload="metadata" playsinline><source src="{{ $media->url() }}" type="{{ $media->mimeType() }}">Browser Anda tidak mendukung pemutar video.</video>
                @else
                    <a href="{{ $media->url() }}" target="_blank" rel="noopener"><img src="{{ $media->url() }}" alt="Lampiran {{ $announcement->title }}" loading="lazy"></a>
                @endif
            @endforeach
            @if ($youtube)
                <div class="media-embed"><iframe src="{{ $youtube }}" title="{{ $announcement->title }}" loading="lazy" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>
            @endif
        </div>
    @endif
@endif

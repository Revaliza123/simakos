<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * UC-08 Kelola Pengumuman (FR-09): membuat, mengubah, dan mempublikasikan pengumuman,
 * lengkap dengan lampiran gambar/video dan tautan video YouTube.
 */
class AnnouncementController extends Controller
{
    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    private const VIDEO_MIMES = ['video/mp4', 'video/webm', 'video/ogg'];

    public function index(): View
    {
        return view('dashboard.announcements', [
            ...$this->sharedData('announcements'),
            'announcements' => Announcement::with(['creator', 'media'])->latest()->get(),
        ]);
    }

    public function create(): View
    {
        return view('dashboard.forms.announcement', [
            ...$this->sharedData('announcements'),
            'announcement' => new Announcement(['published_at' => now()]),
            'mediaLimits' => config('simakos.announcement_media'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        DB::transaction(function () use ($request, $validated): void {
            $announcement = Announcement::create([
                'title' => $validated['title'],
                'content' => $validated['content'],
                'video_url' => $validated['video_url'] ?? null,
                'created_by' => $request->user()->id,
                'published_at' => $request->boolean('publish') ? now() : null,
            ]);

            $this->storeMedia($request, $announcement);
        });

        return redirect()->route('announcements')->with('status', $request->boolean('publish')
            ? 'Pengumuman berhasil dipublikasikan ke portal penghuni.'
            : 'Pengumuman disimpan sebagai draf.');
    }

    public function edit(Announcement $announcement): View
    {
        return view('dashboard.forms.announcement', [
            ...$this->sharedData('announcements'),
            'announcement' => $announcement->load('media'),
            'mediaLimits' => config('simakos.announcement_media'),
        ]);
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $validated = $this->validated($request, $announcement);

        DB::transaction(function () use ($request, $validated, $announcement): void {
            $announcement->update([
                'title' => $validated['title'],
                'content' => $validated['content'],
                'video_url' => $validated['video_url'] ?? null,
                // Pertahankan tanggal terbit lama bila tetap dipublikasikan.
                'published_at' => $request->boolean('publish') ? ($announcement->published_at ?? now()) : null,
            ]);

            $removed = $announcement->media()->whereIn('id', $validated['delete_media'] ?? [])->get();
            Storage::disk('public')->delete($removed->pluck('path')->all());
            $announcement->media()->whereKey($removed->modelKeys())->delete();

            $this->storeMedia($request, $announcement);
        });

        return redirect()->route('announcements')->with('status', 'Pengumuman berhasil diperbarui.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        Storage::disk('public')->delete($announcement->media()->pluck('path')->all());
        $announcement->delete();

        return redirect()->route('announcements')->with('status', 'Pengumuman dihapus.');
    }

    private function validated(Request $request, ?Announcement $announcement = null): array
    {
        $limits = config('simakos.announcement_media');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:5000'],
            'publish' => ['nullable', 'boolean'],
            'video_url' => ['nullable', 'url', 'max:255', function ($attribute, $value, $fail) {
                if (! Announcement::youtubeId($value)) {
                    $fail('Tautan video harus berupa link YouTube (youtube.com atau youtu.be).');
                }
            }],
            'media' => ['nullable', 'array', 'max:'.$limits['max_files']],
            'media.*' => ['file', 'mimetypes:'.implode(',', [...self::IMAGE_MIMES, ...self::VIDEO_MIMES])],
            'delete_media' => ['nullable', 'array'],
            'delete_media.*' => ['integer'],
        ], [
            'title.required' => 'Judul pengumuman wajib diisi.',
            'content.required' => 'Isi pengumuman wajib diisi.',
            'video_url.url' => 'Tautan video tidak valid.',
            'media.max' => "Maksimal {$limits['max_files']} file per unggahan.",
            'media.*.mimetypes' => 'Format lampiran harus gambar (JPG, PNG, WebP, GIF) atau video (MP4, WebM).',
        ]);

        // Batas ukuran berbeda untuk gambar dan video.
        foreach ($request->file('media', []) as $index => $file) {
            $isVideo = $this->isVideo($file);
            $maxKb = $isVideo ? $limits['video_max_kb'] : $limits['image_max_kb'];

            if ($file->getSize() > $maxKb * 1024) {
                throw ValidationException::withMessages([
                    "media.{$index}" => sprintf('%s "%s" melebihi batas %d MB.', $isVideo ? 'Video' : 'Gambar', $file->getClientOriginalName(), $maxKb / 1024),
                ]);
            }
        }

        $remaining = $announcement
            ? $announcement->media()->whereNotIn('id', $validated['delete_media'] ?? [])->count()
            : 0;

        if ($remaining + count($request->file('media', [])) > $limits['max_files']) {
            throw ValidationException::withMessages([
                'media' => "Satu pengumuman maksimal memiliki {$limits['max_files']} lampiran.",
            ]);
        }

        return $validated;
    }

    private function storeMedia(Request $request, Announcement $announcement): void
    {
        $order = (int) $announcement->media()->max('sort_order');

        foreach ($request->file('media', []) as $file) {
            $announcement->media()->create([
                'type' => $this->isVideo($file) ? 'video' : 'image',
                'path' => $file->store('announcements', 'public'),
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 150),
                'sort_order' => ++$order,
            ]);
        }
    }

    private function isVideo(UploadedFile $file): bool
    {
        return in_array($file->getMimeType(), self::VIDEO_MIMES, true);
    }
}

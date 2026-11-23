<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** UC-08 Kelola Pengumuman (FR-09): membuat, mengubah, dan mempublikasikan pengumuman. */
class AnnouncementController extends Controller
{
    public function index(): View
    {
        return view('dashboard.announcements', [
            ...$this->sharedData('announcements'),
            'announcements' => Announcement::with('creator')->latest()->get(),
        ]);
    }

    public function create(): View
    {
        return view('dashboard.forms.announcement', [
            ...$this->sharedData('announcements'),
            'announcement' => new Announcement(['published_at' => now()]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        Announcement::create([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'created_by' => $request->user()->id,
            'published_at' => $request->boolean('publish') ? now() : null,
        ]);

        return redirect()->route('announcements')->with('status', $request->boolean('publish')
            ? 'Pengumuman berhasil dipublikasikan ke portal penghuni.'
            : 'Pengumuman disimpan sebagai draf.');
    }

    public function edit(Announcement $announcement): View
    {
        return view('dashboard.forms.announcement', [
            ...$this->sharedData('announcements'),
            'announcement' => $announcement,
        ]);
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $validated = $this->validated($request);

        $announcement->update([
            'title' => $validated['title'],
            'content' => $validated['content'],
            // Pertahankan tanggal terbit lama bila tetap dipublikasikan.
            'published_at' => $request->boolean('publish') ? ($announcement->published_at ?? now()) : null,
        ]);

        return redirect()->route('announcements')->with('status', 'Pengumuman berhasil diperbarui.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return redirect()->route('announcements')->with('status', 'Pengumuman dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:5000'],
            'publish' => ['nullable', 'boolean'],
        ]);
    }
}

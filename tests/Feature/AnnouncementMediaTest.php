<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\AnnouncementMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Pengumuman dengan lampiran gambar, video, dan link YouTube. */
class AnnouncementMediaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function payload(array $overrides = []): array
    {
        return ['title' => 'Kerja bakti', 'content' => 'Minggu pagi kerja bakti.', 'publish' => 1, ...$overrides];
    }

    public function test_announcement_with_image_video_and_youtube_is_shown_to_tenants(): void
    {
        $this->actingAs($this->admin)->post(route('announcements.store'), $this->payload([
            'media' => [
                UploadedFile::fake()->image('denah.jpg'),
                UploadedFile::fake()->create('tur-kos.mp4', 2048, 'video/mp4'),
            ],
            'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
        ]))->assertSessionHasNoErrors()->assertRedirect(route('announcements'));

        $announcement = Announcement::with('media')->sole();
        $this->assertSame(['image', 'video'], $announcement->media->pluck('type')->all());
        $announcement->media->each(fn (AnnouncementMedia $m) => Storage::disk('public')->assertExists($m->path));
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $announcement->youtubeEmbedUrl());

        $this->get(route('announcements'))->assertOk()->assertSee($announcement->media[0]->url());

        $tenant = User::factory()->create(['role' => 'tenant']);
        $this->actingAs($tenant)->get(route('tenant.announcements'))
            ->assertOk()
            ->assertSee($announcement->media[1]->url())
            ->assertSee('<video controls', false)
            ->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false);
        $this->get(route('tenant.overview'))->assertOk()->assertSee($announcement->media[0]->url());
    }

    public function test_invalid_attachments_and_links_are_rejected(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('announcements.store'), $this->payload(['media' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]]))
            ->assertSessionHasErrors('media.0');
        $this->post(route('announcements.store'), $this->payload(['media' => [UploadedFile::fake()->image('besar.jpg')->size(6000)]]))
            ->assertSessionHasErrors('media.0');
        $this->post(route('announcements.store'), $this->payload(['media' => [UploadedFile::fake()->create('besar.mp4', 31000, 'video/mp4')]]))
            ->assertSessionHasErrors('media.0');
        $this->post(route('announcements.store'), $this->payload(['video_url' => 'https://vimeo.com/123']))
            ->assertSessionHasErrors('video_url');
        $this->post(route('announcements.store'), $this->payload(['media' => array_map(fn ($i) => UploadedFile::fake()->image("$i.jpg"), range(1, 7))]))
            ->assertSessionHasErrors('media');

        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_attachments_can_be_removed_and_are_deleted_with_announcement(): void
    {
        $this->actingAs($this->admin)->post(route('announcements.store'), $this->payload([
            'media' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ]));
        $announcement = Announcement::with('media')->sole();
        [$first, $second] = $announcement->media->all();

        $this->get(route('announcements.edit', $announcement))->assertOk()->assertSee($first->url());
        $this->put(route('announcements.update', $announcement), $this->payload(['delete_media' => [$first->id], 'video_url' => '']))
            ->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($first->path);
        $this->assertSame([$second->id], $announcement->fresh()->media->pluck('id')->all());

        $this->delete(route('announcements.destroy', $announcement));
        Storage::disk('public')->assertMissing($second->path);
        $this->assertDatabaseCount('announcement_media', 0);
    }
}

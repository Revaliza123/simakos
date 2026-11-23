<?php

namespace Tests\Feature;

use App\Models\Rental;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Room Management: spesifikasi, fasilitas, foto, masa sewa, dan popup detail kamar.
 */
class RoomManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private RoomType $type;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 09:00:00');
        Storage::fake('public');
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->type = RoomType::create(['name' => 'Deluxe', 'facilities' => ['ac', 'wifi', 'km_dalam']]);
    }

    private function roomPayload(array $overrides = []): array
    {
        return [
            'room_number' => 'A101',
            'room_type_id' => $this->type->id,
            'floor' => 1,
            'size_m2' => 12,
            'price' => 1500000,
            'status' => 'available',
            'description' => 'Menghadap taman',
            'facilities' => ['balkon'],
            ...$overrides,
        ];
    }

    public function test_room_is_saved_with_specs_facilities_and_photos(): void
    {
        $this->actingAs($this->admin)->post(route('rooms.store'), $this->roomPayload([
            'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.png')],
        ]))->assertSessionHasNoErrors();

        $room = Room::with('photos')->sole();
        $this->assertSame(1, $room->floor);
        $this->assertSame('12 m²', $room->sizeLabel());
        $this->assertSame(['ac', 'wifi', 'km_dalam', 'balkon'], $room->facilityKeys());
        $this->assertCount(2, $room->photos);
        $room->photos->each(fn ($photo) => Storage::disk('public')->assertExists($photo->path));

        $this->get(route('rooms'))->assertOk()->assertSee($room->photos->first()->url())->assertSee('Balkon');
    }

    public function test_invalid_facility_photo_format_and_photo_limit_are_rejected(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('rooms.store'), $this->roomPayload(['facilities' => ['kolam_renang']]))
            ->assertSessionHasErrors('facilities.0');
        $this->post(route('rooms.store'), $this->roomPayload(['photos' => [UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')]]))
            ->assertSessionHasErrors('photos.0');

        $photos = array_map(fn ($i) => UploadedFile::fake()->image("p{$i}.jpg"), range(1, 9));
        $this->post(route('rooms.store'), $this->roomPayload(['photos' => $photos]))
            ->assertSessionHasErrors('photos');
        $this->assertDatabaseCount('rooms', 0);
    }

    public function test_photos_can_be_deleted_and_cover_changed(): void
    {
        $this->actingAs($this->admin)->post(route('rooms.store'), $this->roomPayload([
            'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg'), UploadedFile::fake()->image('c.jpg')],
        ]));
        $room = Room::with('photos')->sole();
        [$first, $second, $third] = $room->photos->all();

        $this->put(route('rooms.update', $room), $this->roomPayload([
            'delete_photos' => [$first->id],
            'cover_photo' => $third->id,
        ]))->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing($first->path);
        $this->assertSame([$third->id, $second->id], $room->fresh()->photos->pluck('id')->all());
    }

    public function test_room_type_facilities_are_saved(): void
    {
        $this->actingAs($this->admin)->post(route('room-types.store'), ['name' => 'Studio', 'facilities' => ['tv', 'kulkas']])
            ->assertSessionHasNoErrors();

        $this->assertSame(['tv', 'kulkas'], RoomType::where('name', 'Studio')->sole()->facilities);
        $this->get(route('room-types.index'))->assertSee('Kulkas');
    }

    public function test_room_detail_page_shows_tenant_period_and_history(): void
    {
        $room = Room::create(['room_number' => 'B201', 'room_type_id' => $this->type->id, 'price' => 2000000, 'status' => 'occupied']);
        $old = User::factory()->create(['role' => 'tenant', 'name' => 'Penyewa Lama']);
        $now = User::factory()->create(['role' => 'tenant', 'name' => 'Penyewa Sekarang', 'phone_number' => '0812000111']);
        Rental::create(['user_id' => $old->id, 'room_id' => $room->id, 'start_date' => '2025-01-01', 'end_date' => '2025-12-31', 'status' => 'completed']);
        Rental::create(['user_id' => $now->id, 'room_id' => $room->id, 'start_date' => '2026-06-01', 'end_date' => '2027-05-31', 'status' => 'active']);

        $this->actingAs($this->admin);

        // Tautan lama ?room=ID diarahkan ke halaman detail.
        $this->get(route('rooms', ['room' => $room->id]))->assertRedirect(route('rooms.show', $room));
        $this->get(route('rooms'))->assertSee(route('rooms.show', $room));

        $this->get(route('rooms.show', $room))
            ->assertOk()
            ->assertSee('Kamar B201')
            ->assertSee('Ubah Kamar')
            ->assertSee('Penyewa Sekarang')
            ->assertSee('0812000111')
            ->assertSee('31 Mei 2027')
            ->assertSee('Sisa 207 hari')
            ->assertSee('Penyewa Lama')
            ->assertSee('Selesai')
            ->assertSee('AC');

        $this->get(route('rooms'))->assertSee('01 Jun 2026 – 31 Mei 2027');
    }

    public function test_rental_without_end_date_is_shown_as_not_determined(): void
    {
        $room = Room::create(['room_number' => 'C301', 'room_type_id' => $this->type->id, 'price' => 2000000, 'status' => 'occupied']);
        $tenant = User::factory()->create(['role' => 'tenant']);
        $rental = Rental::create(['user_id' => $tenant->id, 'room_id' => $room->id, 'start_date' => '2026-06-01', 'status' => 'active']);

        $this->assertSame('01 Jun 2026 – Tidak ditentukan', $rental->periodLabel());
        $this->assertNull($rental->remainingLabel());

        $this->actingAs($this->admin)->get(route('rooms'))->assertSee('01 Jun 2026 – Tidak ditentukan');
        $this->get(route('rooms.show', $room))->assertSee('Tidak ditentukan');
        $this->actingAs($tenant)->get(route('tenant.overview'))->assertSee('Selesai: Tidak ditentukan');
    }

    public function test_tenant_cannot_open_room_detail(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant']);
        $room = Room::create(['room_number' => 'D1', 'room_type_id' => $this->type->id, 'price' => 1, 'status' => 'available']);

        $this->actingAs($tenant)->get(route('rooms.show', $room))->assertForbidden();
    }
}

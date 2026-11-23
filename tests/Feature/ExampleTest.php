<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /** Landing page publik menampilkan katalog kamar kosong saja (A5 In-Scope). */
    public function test_landing_page_lists_only_available_rooms(): void
    {
        $type = RoomType::create(['name' => 'Deluxe Balcony', 'description' => 'AC, KM dalam, balkon']);
        Room::create(['room_number' => 'A101', 'room_type_id' => $type->id, 'price' => 2750000, 'status' => 'available']);
        Room::create(['room_number' => 'A102', 'room_type_id' => $type->id, 'price' => 2750000, 'status' => 'occupied']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Deluxe Balcony')
            ->assertSee('Kamar A101')
            ->assertDontSee('Kamar A102');
    }

    public function test_dashboard_redirects_guest_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }
}

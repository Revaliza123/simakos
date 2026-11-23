<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Complaint;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pengujian Black-Box otomatis sesuai bagian I dokumen SAD SIMAKOS
 * (TC-UC02-01 s.d. TC-UC08-02) ditambah skenario negatif & NFR-03.
 */
class SimakosRequirementsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 09:00:00');
        config(['billing.due_day' => 10, 'billing.late_fee_per_day' => 10000, 'billing.late_fee_max_percent' => 20]);
        Storage::fake('public');
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function room(string $number = 'A101', string $status = 'available', float $price = 1500000): Room
    {
        $type = RoomType::firstOrCreate(['name' => 'Deluxe'], ['description' => 'AC, KM dalam']);

        return Room::create(['room_number' => $number, 'room_type_id' => $type->id, 'price' => $price, 'status' => $status]);
    }

    /** Check-in lewat HTTP seperti yang dilakukan admin. */
    private function checkIn(Room $room, string $email = 'john@example.com'): User
    {
        $this->actingAs($this->admin)->post(route('tenants.store'), [
            'name' => 'John Doe',
            'email' => $email,
            'password' => 'password-aman',
            'password_confirmation' => 'password-aman',
            'room_id' => $room->id,
            'start_date' => '2026-11-01',
        ])->assertSessionHasNoErrors();

        return User::where('email', $email)->firstOrFail();
    }

    /* ---------------- FR-02 / UC-02 Kelola Master Kamar ---------------- */

    public function test_tc_uc02_01_admin_adds_room_and_duplicate_number_is_rejected(): void
    {
        $type = RoomType::create(['name' => 'Deluxe']);
        $this->actingAs($this->admin);

        $this->post(route('rooms.store'), ['room_number' => 'A101', 'room_type_id' => $type->id, 'price' => 1500000, 'status' => 'available'])
            ->assertRedirect(route('rooms'));
        $this->assertDatabaseHas('rooms', ['room_number' => 'A101', 'status' => 'available']);
        $this->get(route('rooms'))->assertOk()->assertSee('Kamar A101');

        $this->post(route('rooms.store'), ['room_number' => 'A101', 'price' => 1, 'status' => 'available'])
            ->assertSessionHasErrors(['room_number' => 'Nomor kamar sudah terdaftar.']);
    }

    public function test_admin_can_update_and_delete_rooms_and_room_types(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('room-types.store'), ['name' => 'Standard', 'description' => 'Kipas angin'])->assertRedirect(route('room-types.index'));
        $type = RoomType::where('name', 'Standard')->sole();
        $this->put(route('room-types.update', $type), ['name' => 'Standard Single', 'description' => 'Kipas, KM luar'])->assertRedirect();
        $this->assertSame('Standard Single', $type->fresh()->name);

        $room = Room::create(['room_number' => 'B201', 'room_type_id' => $type->id, 'price' => 900000, 'status' => 'available']);
        $this->put(route('rooms.update', $room), ['room_number' => 'B202', 'room_type_id' => $type->id, 'price' => 950000, 'status' => 'maintenance'])->assertRedirect(route('rooms'));
        $this->assertDatabaseHas('rooms', ['id' => $room->id, 'room_number' => 'B202', 'status' => 'maintenance']);

        // Tipe yang masih dipakai tidak boleh dihapus.
        $this->delete(route('room-types.destroy', $type))->assertSessionHasErrors('room_type');

        $this->delete(route('rooms.destroy', $room))->assertRedirect(route('rooms'));
        $this->delete(route('room-types.destroy', $type))->assertRedirect(route('room-types.index'));
        $this->assertDatabaseCount('rooms', 0);
        $this->assertDatabaseCount('room_types', 0);
    }

    public function test_room_with_rental_history_cannot_be_deleted_and_occupied_status_is_locked(): void
    {
        $room = $this->room();
        $this->checkIn($room);

        $this->put(route('rooms.update', $room), ['room_number' => 'A101', 'price' => 1600000, 'status' => 'available'])->assertRedirect();
        $this->assertSame('occupied', $room->fresh()->status);

        $this->delete(route('rooms.destroy', $room))->assertSessionHasErrors('room');
        $this->assertDatabaseHas('rooms', ['id' => $room->id]);
    }

    /* ---------------- FR-03 / UC-03 Check-in & Check-out ---------------- */

    public function test_tc_uc03_01_check_in_locks_room_as_occupied_and_issues_invoice(): void
    {
        $room = $this->room();
        $tenant = $this->checkIn($room);

        $this->assertSame('occupied', $room->fresh()->status);
        $this->assertDatabaseHas('rentals', ['user_id' => $tenant->id, 'room_id' => $room->id, 'status' => 'active']);
        $this->assertSame('unpaid', Invoice::sole()->status);
        // NFR-02: password tersimpan sebagai hash Bcrypt, bukan teks asli.
        $this->assertNotSame('password-aman', $tenant->password);
        $this->assertTrue(Hash::check('password-aman', $tenant->password));
    }

    public function test_double_booking_is_rejected_even_when_request_is_forged(): void
    {
        $room = $this->room();
        $this->checkIn($room);

        // Kamar terisi tidak muncul di pilihan…
        $this->get(route('tenants.create'))->assertOk()->assertDontSee('Kamar A101 ·');

        // …dan request yang dimanipulasi tetap ditolak server.
        $this->post(route('tenants.store'), [
            'name' => 'Penyewa Kedua', 'email' => 'kedua@example.com',
            'password' => 'password-aman', 'password_confirmation' => 'password-aman',
            'room_id' => $room->id, 'start_date' => '2026-11-02',
        ])->assertSessionHasErrors('room_id');

        $this->assertDatabaseCount('rentals', 1);
        $this->assertDatabaseMissing('users', ['email' => 'kedua@example.com']);
    }

    public function test_check_out_completes_rental_and_returns_room_to_available(): void
    {
        $room = $this->room();
        $tenant = $this->checkIn($room);
        $rental = Rental::sole();

        $this->patch(route('rentals.checkout', $rental), ['checkout_date' => '2026-11-30'])->assertRedirect();

        $this->assertSame('completed', $rental->fresh()->status);
        $this->assertSame('2026-11-30', $rental->fresh()->end_date->toDateString());
        $this->assertSame('available', $room->fresh()->status);

        // Penghuni lama bisa check-in lagi ke kamar yang sama; riwayat tetap tersimpan.
        $this->post(route('rentals.store'), ['user_id' => $tenant->id, 'room_id' => $room->id, 'start_date' => '2026-12-01', 'duration_months' => 6])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('rentals', 2);
        $this->assertSame('2027-05-31', Rental::where('status', 'active')->sole()->end_date->toDateString());
        $this->get(route('rentals.index'))->assertOk()->assertSee('John Doe');
    }

    /* ---------------- FR-04 s.d. FR-06 / UC-04, UC-05 Pembayaran ---------------- */

    public function test_tc_uc04_01_tenant_sees_bill_and_uploads_valid_proof(): void
    {
        $tenant = $this->checkIn($this->room());
        $invoice = Invoice::sole();

        $this->actingAs($tenant)->get(route('tenant.overview'))->assertOk()->assertSee('Rp 1.500.000')->assertSee('Menunggu Pembayaran');
        $this->get(route('tenant.bills'))->assertOk()->assertSee($invoice->invoice_number)->assertSee('Belum Dibayar');

        $this->post(route('tenant.payments.store'), [
            'invoice_id' => $invoice->id,
            'total_months_paid' => 1,
            'proof_image' => UploadedFile::fake()->image('bukti_tf.jpg')->size(500),
        ])->assertRedirect(route('tenant.bills'))->assertSessionHasNoErrors();

        $payment = Payment::sole();
        $this->assertSame('pending', $payment->status);
        $this->assertEquals(1500000, $payment->amount);
        Storage::disk('public')->assertExists($payment->proof_image);
        $this->assertSame('pending', $invoice->fresh()->status);
    }

    public function test_tc_uc04_02_pdf_proof_is_rejected_with_format_message(): void
    {
        $tenant = $this->checkIn($this->room());

        $this->actingAs($tenant)->post(route('tenant.payments.store'), [
            'invoice_id' => Invoice::sole()->id,
            'total_months_paid' => 1,
            'proof_image' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors(['proof_image' => 'Format file harus jpg, png.']);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_prepaid_months_are_priced_by_the_server(): void
    {
        $tenant = $this->checkIn($this->room());

        $this->actingAs($tenant)->post(route('tenant.payments.store'), [
            'total_months_paid' => 3,
            'amount' => 1, // diabaikan: nominal dihitung server
            'proof_image' => UploadedFile::fake()->image('bukti.png'),
        ])->assertSessionHasNoErrors();

        $this->assertEquals(4500000, Payment::sole()->amount);
    }

    public function test_reject_requires_reason_and_tenant_can_reupload(): void
    {
        $tenant = $this->checkIn($this->room());
        $invoice = Invoice::sole();
        $this->actingAs($tenant)->post(route('tenant.payments.store'), [
            'invoice_id' => $invoice->id, 'total_months_paid' => 1, 'proof_image' => UploadedFile::fake()->image('a.jpg'),
        ]);

        // Selama masih pending, unggahan kedua ditahan.
        $this->post(route('tenant.payments.store'), [
            'invoice_id' => $invoice->id, 'total_months_paid' => 1, 'proof_image' => UploadedFile::fake()->image('b.jpg'),
        ])->assertSessionHasErrors('proof_image');

        $payment = Payment::sole();
        $this->actingAs($this->admin)->patch(route('billing.payments.reject', $payment))->assertSessionHasErrors('admin_notes');
        $this->patch(route('billing.payments.reject', $payment), ['admin_notes' => 'Nominal transfer kurang'])->assertRedirect();
        $this->assertSame('rejected', $payment->fresh()->status);
        $this->assertSame('unpaid', $invoice->fresh()->status);

        $this->actingAs($tenant)->get(route('tenant.bills'))->assertSee('Nominal transfer kurang');
        $this->post(route('tenant.payments.store'), [
            'invoice_id' => $invoice->id, 'total_months_paid' => 1, 'proof_image' => UploadedFile::fake()->image('c.jpg'),
        ])->assertSessionHasNoErrors();

        $second = Payment::where('status', 'pending')->sole();
        $this->actingAs($this->admin)->patch(route('billing.payments.approve', $second))->assertRedirect();
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->actingAs($tenant)->get(route('tenant.bills'))->assertSee('Lunas');
    }

    /* ---------------- FR-07, FR-08 / UC-06, UC-07 Keluhan ---------------- */

    public function test_tc_uc06_01_tenant_submits_complaint_with_photo(): void
    {
        $tenant = $this->checkIn($this->room());

        $this->actingAs($tenant)->post(route('tenant.complaints.store'), [
            'title' => 'AC Bocor',
            'description' => 'Air menetes',
            'photo' => UploadedFile::fake()->image('ac.jpg'),
        ])->assertRedirect(route('tenant.complaints'));

        $complaint = Complaint::sole();
        $this->assertSame('open', $complaint->status);
        $this->assertSame(Rental::sole()->id, $complaint->rental_id);
        Storage::disk('public')->assertExists($complaint->photo);

        $this->get(route('tenant.complaints'))->assertSee('AC Bocor')->assertSee('Menunggu');
        $this->actingAs($this->admin)->get(route('complaints'))->assertSee('AC Bocor');
    }

    public function test_complaint_requires_fields_and_active_rental(): void
    {
        $tenant = $this->checkIn($this->room());
        $this->actingAs($tenant)->post(route('tenant.complaints.store'), ['title' => '', 'description' => ''])
            ->assertSessionHasErrors(['title', 'description']);

        $noRental = User::factory()->create(['role' => 'tenant']);
        $this->actingAs($noRental)->get(route('tenant.complaints.create'))->assertRedirect(route('tenant.complaints'));
        $this->post(route('tenant.complaints.store'), ['title' => 'X', 'description' => 'Y'])->assertSessionHasErrors('rental');
        $this->assertDatabaseCount('complaints', 0);
    }

    public function test_tc_uc07_01_admin_updates_complaint_status_and_response(): void
    {
        $tenant = $this->checkIn($this->room());
        $complaint = Complaint::create(['rental_id' => Rental::sole()->id, 'title' => 'AC Bocor', 'description' => 'Air menetes', 'status' => 'open']);

        $this->actingAs($this->admin)->patch(route('complaints.update', $complaint), [
            'status' => 'in_progress', 'response' => 'Teknisi menuju lokasi',
        ])->assertRedirect();

        $this->assertSame('in_progress', $complaint->fresh()->status);
        $this->assertNotNull($complaint->fresh()->responded_at);
        $this->actingAs($tenant)->get(route('tenant.complaints'))->assertSee('Sedang Diproses')->assertSee('Teknisi menuju lokasi');

        $this->actingAs($this->admin)->patch(route('complaints.update', $complaint), ['status' => 'resolved'])->assertRedirect();
        $this->assertSame('resolved', $complaint->fresh()->status);
        $this->assertSame('Teknisi menuju lokasi', $complaint->fresh()->response);

        $this->patch(route('complaints.update', $complaint), ['status' => 'bukan-status'])->assertSessionHasErrors('status');
    }

    /* ---------------- FR-09, FR-10 / UC-08 ---------------- */

    public function test_tc_uc08_01_published_announcement_appears_for_tenants_and_drafts_do_not(): void
    {
        $tenant = $this->checkIn($this->room());

        $this->actingAs($this->admin)->post(route('announcements.store'), ['title' => 'Jadwal Pembersihan Tandon', 'content' => 'Sabtu pagi', 'publish' => 1])->assertRedirect();
        $this->post(route('announcements.store'), ['title' => 'Draf Internal', 'content' => 'Belum final', 'publish' => 0])->assertRedirect();

        $draft = Announcement::where('title', 'Draf Internal')->sole();
        $this->assertNull($draft->published_at);

        $this->actingAs($tenant)->get(route('tenant.overview'))->assertSee('Jadwal Pembersihan Tandon')->assertDontSee('Draf Internal');
        $this->get(route('tenant.announcements'))->assertSee('Jadwal Pembersihan Tandon');

        $this->actingAs($this->admin)->put(route('announcements.update', $draft), ['title' => 'Info Parkir', 'content' => 'Gunakan slot sesuai kamar', 'publish' => 1])->assertRedirect();
        $this->actingAs($tenant)->get(route('tenant.announcements'))->assertSee('Info Parkir');

        $this->actingAs($this->admin)->delete(route('announcements.destroy', $draft))->assertRedirect();
        $this->assertDatabaseCount('announcements', 1);
    }

    public function test_tc_uc08_02_negative_expense_is_rejected(): void
    {
        $this->actingAs($this->admin)->post(route('expenses.store'), [
            'title' => 'Listrik', 'category' => 'listrik', 'amount' => -450000, 'expense_date' => '2026-11-03',
        ])->assertSessionHasErrors('amount');

        $this->post(route('expenses.store'), [
            'title' => 'Listrik', 'category' => 'listrik', 'amount' => 450000, 'expense_date' => '2026-11-03',
        ])->assertRedirect();
        $this->assertDatabaseHas('expenses', ['category' => 'listrik']);
    }

    /* ---------------- NFR-03 Kepemilikan data ---------------- */

    public function test_tenant_cannot_access_other_tenants_data(): void
    {
        $alice = $this->checkIn($this->room('A101'), 'alice@example.com');
        $bob = $this->checkIn($this->room('A102'), 'bob@example.com');
        $bobRental = Rental::where('user_id', $bob->id)->sole();
        $bobInvoice = Invoice::where('rental_id', $bobRental->id)->sole();
        Complaint::create(['rental_id' => $bobRental->id, 'title' => 'Rahasia Bob', 'description' => 'x', 'status' => 'open']);

        $this->actingAs($alice);
        $this->get(route('tenant.complaints'))->assertDontSee('Rahasia Bob');
        $this->get(route('tenant.bills'))->assertDontSee($bobInvoice->invoice_number);

        // Membayar tagihan milik penghuni lain ditolak.
        $this->post(route('tenant.payments.store'), [
            'invoice_id' => $bobInvoice->id, 'total_months_paid' => 1, 'proof_image' => UploadedFile::fake()->image('x.jpg'),
        ])->assertSessionHasErrors('invoice_id');

        // Rute admin tidak dapat diakses penghuni.
        $this->get(route('billing'))->assertForbidden();
        $this->patch(route('complaints.update', Complaint::sole()), ['status' => 'resolved'])->assertForbidden();
        $this->patch(route('rentals.checkout', $bobRental))->assertForbidden();
    }

    public function test_all_admin_and_tenant_pages_render(): void
    {
        $tenant = $this->checkIn($this->room());

        foreach (['overview', 'rooms', 'rooms.create', 'room-types.index', 'room-types.create', 'rentals.index', 'rentals.create', 'tenants.create', 'complaints', 'complaints.create', 'announcements', 'announcements.create', 'billing'] as $route) {
            $this->actingAs($this->admin)->get(route($route))->assertOk();
        }
        $this->get(route('rooms.edit', Room::first()))->assertOk();

        foreach (['tenant.overview', 'tenant.bills', 'tenant.complaints', 'tenant.complaints.create', 'tenant.announcements'] as $route) {
            $this->actingAs($tenant)->get(route($route))->assertOk();
        }
    }
}

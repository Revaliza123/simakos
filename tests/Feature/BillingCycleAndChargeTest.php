<?php

namespace Tests\Feature;

use App\Models\Charge;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\Room;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Siklus bayar sewa (bulanan / 3 / 6 / 12 bulan) dan tagihan lain (gas, iuran, dll.).
 */
class BillingCycleAndChargeTest extends TestCase
{
    use RefreshDatabase;

    private BillingService $billing;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 09:00:00');
        config(['billing.due_day' => 10, 'billing.late_fee_per_day' => 10000, 'billing.late_fee_max_percent' => 20]);
        Storage::fake('public');
        $this->billing = app(BillingService::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function rental(string $room, int $cycle = 1, string $start = '2026-09-01', ?string $end = null, float $price = 1000000): Rental
    {
        $room = Room::create(['room_number' => $room, 'price' => $price, 'status' => 'occupied']);
        $user = User::factory()->create(['role' => 'tenant']);

        return Rental::create(['user_id' => $user->id, 'room_id' => $room->id, 'start_date' => $start, 'end_date' => $end, 'billing_cycle' => $cycle, 'status' => 'active']);
    }

    /* ---------------- Siklus bayar ---------------- */

    public function test_six_month_cycle_bills_once_per_cycle(): void
    {
        $rental = $this->rental('A1', cycle: 6);

        $this->assertSame(1, $this->billing->generateForMonth(Carbon::parse('2026-09-01')));
        $invoice = Invoice::sole();
        $this->assertSame(6, $invoice->period_months);
        $this->assertEquals(6000000, $invoice->amount);
        $this->assertSame('Sep 2026 – Feb 2027', $invoice->periodLabel());

        // Okt s.d. Feb sudah tercakup → tidak ditagih lagi.
        foreach (['2026-10-01', '2026-11-01', '2026-12-01', '2027-01-01', '2027-02-01'] as $month) {
            $this->assertSame(0, $this->billing->generateForMonth(Carbon::parse($month)), $month);
        }

        // Siklus berikutnya dimulai Maret.
        $this->assertSame(1, $this->billing->generateForMonth(Carbon::parse('2027-03-01')));
        $this->assertSame(2, Invoice::where('rental_id', $rental->id)->count());
    }

    public function test_cycle_is_cut_at_contract_end(): void
    {
        $this->rental('A2', cycle: 3, start: '2026-09-01', end: '2026-12-31');

        $this->billing->generateForMonth(Carbon::parse('2026-09-01'));
        $this->billing->generateForMonth(Carbon::parse('2026-12-01'));

        $this->assertSame([3, 1], Invoice::orderBy('billing_month')->pluck('period_months')->all());
        $this->assertSame(0, $this->billing->generateForMonth(Carbon::parse('2027-01-01')));
    }

    public function test_monthly_prepaid_months_are_not_billed_again(): void
    {
        $rental = $this->rental('A3', start: '2026-11-01');
        $this->billing->generateForMonth(Carbon::parse('2026-11-01'));

        $payment = Payment::create(['rental_id' => $rental->id, 'amount' => 3000000, 'total_months_paid' => 3, 'payment_date' => '2026-11-05', 'status' => 'pending']);
        $this->billing->approvePayment($payment);

        $this->assertSame(0, $this->billing->generateForMonth(Carbon::parse('2026-12-01')));
        $this->assertSame(0, $this->billing->generateForMonth(Carbon::parse('2027-01-01')));
        $this->assertSame(1, $this->billing->generateForMonth(Carbon::parse('2027-02-01')));
        $this->assertSame('2027-01-31', $rental->fresh()->paidThrough()->toDateString());
    }

    public function test_tenant_with_six_month_cycle_pays_whole_cycle_and_can_prepay_next(): void
    {
        $rental = $this->rental('A4', cycle: 6, start: '2026-11-01');
        $this->actingAs($rental->user)->get(route('tenant.bills'))->assertOk()->assertSee('Per 6 bulan');
        $invoice = Invoice::sole();

        // Pilihan bulan harus kelipatan siklus.
        $this->post(route('tenant.payments.store'), ['invoice_id' => $invoice->id, 'total_months_paid' => 1, 'proof_image' => UploadedFile::fake()->image('a.jpg')])
            ->assertSessionHasErrors('total_months_paid');

        $this->post(route('tenant.payments.store'), ['invoice_id' => $invoice->id, 'total_months_paid' => 12, 'proof_image' => UploadedFile::fake()->image('a.jpg')])
            ->assertSessionHasNoErrors();
        $payment = Payment::sole();
        $this->assertEquals(12000000, $payment->amount);

        $paid = $this->billing->approvePayment($payment);
        $this->assertCount(2, $paid);
        $this->assertSame(['paid', 'paid'], Invoice::orderBy('billing_month')->pluck('status')->all());
        $this->assertSame('2027-10-31', $rental->fresh()->paidThrough()->toDateString());
    }

    public function test_check_in_saves_cycle_and_admin_can_change_it(): void
    {
        $room = Room::create(['room_number' => 'B1', 'price' => 1000000, 'status' => 'available']);
        $this->actingAs($this->admin)->post(route('tenants.store'), [
            'name' => 'Tono', 'email' => 'tono@example.com', 'password' => 'password-aman', 'password_confirmation' => 'password-aman',
            'room_id' => $room->id, 'start_date' => '2026-11-01', 'duration_months' => 12, 'billing_cycle' => 3,
        ])->assertSessionHasNoErrors();

        $rental = Rental::sole();
        $this->assertSame(3, $rental->billing_cycle);
        $this->assertSame(3, Invoice::sole()->period_months);

        $this->patch(route('rentals.billing-cycle', $rental), ['billing_cycle' => 12])->assertSessionHasNoErrors();
        $this->assertSame(12, $rental->fresh()->billing_cycle);
        $this->patch(route('rentals.billing-cycle', $rental), ['billing_cycle' => 5])->assertSessionHasErrors('billing_cycle');
    }

    /* ---------------- Tagihan lain ---------------- */

    public function test_split_charge_is_divided_evenly_and_issued_once(): void
    {
        $a = $this->rental('101');
        $b = $this->rental('102');
        $c = $this->rental('103');

        $this->actingAs($this->admin)->post(route('billing.charges.store'), [
            'title' => 'Isi ulang gas', 'category' => 'gas', 'amount' => 100000, 'amount_mode' => 'split',
            'target' => 'all', 'is_recurring' => 0, 'start_month' => '2026-11', 'due_day' => 15,
        ])->assertSessionHasNoErrors()->assertRedirect(route('billing.charges'));

        $invoices = Invoice::charges()->orderBy('id')->get();
        $this->assertSame([$a->id, $b->id, $c->id], $invoices->pluck('rental_id')->all());
        $this->assertEquals([33334, 33333, 33333], $invoices->pluck('amount')->map(fn ($v) => (float) $v)->all());
        $this->assertEquals(100000, $invoices->sum('amount'));
        $this->assertSame('Isi ulang gas', $invoices->first()->label());
        $this->assertSame('2026-11-15', $invoices->first()->due_date->toDateString());

        // Tidak diterbitkan ulang, termasuk bulan berikutnya (sekali tagih).
        $this->assertSame(0, $this->billing->generateChargesForMonth(Carbon::parse('2026-11-01')));
        $this->assertSame(0, $this->billing->generateChargesForMonth(Carbon::parse('2026-12-01')));
    }

    public function test_recurring_per_tenant_charge_for_selected_rooms_until_stopped(): void
    {
        $a = $this->rental('201');
        $this->rental('202');

        $this->actingAs($this->admin)->post(route('billing.charges.store'), [
            'title' => 'Iuran kebersihan', 'category' => 'kebersihan', 'amount' => 25000, 'amount_mode' => 'per_tenant',
            'target' => 'rooms', 'room_ids' => [$a->room_id], 'is_recurring' => 1, 'start_month' => '2026-11', 'due_day' => 10,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Invoice::charges()->count());
        $this->assertSame($a->id, Invoice::charges()->sole()->rental_id);
        // Jatuh tempo tgl 10 sudah lewat saat diterbitkan (5 Nov? belum) → tetap tgl 10.
        $this->assertSame('2026-11-10', Invoice::charges()->sole()->due_date->toDateString());

        $this->assertSame(1, $this->billing->generateChargesForMonth(Carbon::parse('2026-12-01')));

        $charge = Charge::sole();
        $this->patch(route('billing.charges.stop', $charge))->assertRedirect();
        $this->assertSame(0, $this->billing->generateChargesForMonth(Carbon::parse('2027-01-01')));
        $this->assertSame(2, Invoice::charges()->count());
    }

    public function test_charge_has_no_late_fee_and_is_paid_separately_from_rent(): void
    {
        $rental = $this->rental('301', start: '2026-11-01');
        $this->billing->generateForMonth(Carbon::parse('2026-11-01'));
        $charge = Charge::create(['title' => 'Ganti kunci', 'category' => 'kerusakan', 'amount' => 75000, 'amount_mode' => 'per_tenant', 'target' => 'all', 'is_recurring' => false, 'start_month' => '2026-11-01', 'due_day' => 1]);
        $this->billing->issueCharge($charge, Carbon::parse('2026-11-01'));

        Carbon::setTestNow('2026-12-20');
        $this->billing->refreshOverdue();
        $chargeInvoice = Invoice::charges()->sole();
        $this->assertSame('overdue', $chargeInvoice->status);
        $this->assertEquals(0, $chargeInvoice->late_fee);
        $this->assertGreaterThan(0, (float) Invoice::rent()->forMonth(Carbon::parse('2026-11-01'))->sole()->late_fee);

        // Penghuni membayar tagihan lain: nominal = tagihan tersebut, hanya tagihan itu yang lunas.
        $this->actingAs($rental->user)->post(route('tenant.payments.store'), [
            'invoice_id' => $chargeInvoice->id, 'total_months_paid' => 1, 'proof_image' => UploadedFile::fake()->image('a.jpg'),
        ])->assertSessionHasNoErrors();
        $payment = Payment::sole();
        $this->assertEquals(75000, $payment->amount);

        $this->billing->approvePayment($payment);
        $this->assertSame('paid', $chargeInvoice->fresh()->status);
        $this->assertSame(0, Invoice::rent()->where('status', 'paid')->count());
    }

    public function test_charge_with_payments_cannot_be_deleted_but_unpaid_one_can(): void
    {
        $this->rental('401');
        $this->actingAs($this->admin);

        $paid = Charge::create(['title' => 'Gas', 'category' => 'gas', 'amount' => 50000, 'amount_mode' => 'per_tenant', 'target' => 'all', 'is_recurring' => false, 'start_month' => '2026-11-01', 'due_day' => 10]);
        $this->billing->issueCharge($paid, Carbon::parse('2026-11-01'));
        $paid->invoices()->update(['status' => 'paid']);
        $this->delete(route('billing.charges.destroy', $paid))->assertSessionHasErrors('charge');

        $unpaid = Charge::create(['title' => 'Air', 'category' => 'air', 'amount' => 50000, 'amount_mode' => 'per_tenant', 'target' => 'all', 'is_recurring' => false, 'start_month' => '2026-11-01', 'due_day' => 10]);
        $this->billing->issueCharge($unpaid, Carbon::parse('2026-11-01'));
        $this->delete(route('billing.charges.destroy', $unpaid))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('charges', ['id' => $unpaid->id]);
        $this->assertSame(1, Invoice::charges()->count());
    }

    public function test_charge_pages_render_and_validate(): void
    {
        $rental = $this->rental('501', start: '2026-11-01');
        $charge = Charge::create(['title' => 'Iuran keamanan', 'category' => 'keamanan', 'amount' => 20000, 'amount_mode' => 'per_tenant', 'target' => 'all', 'is_recurring' => true, 'start_month' => '2026-11-01', 'due_day' => 10]);
        $this->billing->generateForMonth(Carbon::parse('2026-11-01'));

        $this->actingAs($this->admin);
        $this->get(route('billing.charges'))->assertOk()->assertSee('Iuran keamanan')->assertSee('Tiap bulan');
        $this->get(route('billing.charges.create'))->assertOk();
        $this->get(route('billing'))->assertOk()->assertSee('Iuran keamanan')->assertSee('Tagihan Lain');
        $this->get(route('billing.create'))->assertOk()->assertSee('Iuran keamanan');

        $this->post(route('billing.charges.store'), ['title' => '', 'category' => 'x', 'amount' => 10, 'amount_mode' => 'split', 'target' => 'rooms', 'is_recurring' => 0, 'start_month' => '2026-11', 'due_day' => 40])
            ->assertSessionHasErrors(['title', 'category', 'amount', 'room_ids', 'due_day']);

        $this->actingAs($rental->user)->get(route('tenant.bills'))->assertOk()->assertSee('Iuran keamanan');
        $this->actingAs($rental->user)->get(route('billing.charges'))->assertForbidden();
        $this->assertNotNull($charge);
    }
}

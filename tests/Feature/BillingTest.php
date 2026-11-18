<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\Room;
use App\Models\User;
use App\Services\BillingService;
use Database\Seeders\FinanceDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private BillingService $billing;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-20 09:00:00');
        config(['billing.due_day' => 10, 'billing.late_fee_per_day' => 10000, 'billing.late_fee_max_percent' => 20]);
        $this->billing = app(BillingService::class);
    }

    private function rental(float $price = 1500000, string $start = '2026-09-01'): Rental
    {
        $room = Room::create(['room_number' => (string) random_int(100, 999), 'price' => $price, 'status' => 'occupied']);
        $user = User::factory()->create(['role' => 'tenant']);

        return Rental::create(['user_id' => $user->id, 'room_id' => $room->id, 'start_date' => $start, 'status' => 'active']);
    }

    public function test_generates_one_invoice_per_active_rental_and_is_idempotent(): void
    {
        $rental = $this->rental();
        $this->rental()->update(['status' => 'completed']);

        $this->assertSame(1, $this->billing->generateForMonth(Carbon::parse('2026-11-01')));
        $this->assertSame(0, $this->billing->generateForMonth(Carbon::parse('2026-11-01')));

        $invoice = Invoice::sole();
        $this->assertSame($rental->id, $invoice->rental_id);
        $this->assertSame('2026-11-10', $invoice->due_date->toDateString());
        $this->assertEquals(1500000, $invoice->amount);
        $this->assertSame("INV-202611-".str_pad((string) $rental->id, 4, '0', STR_PAD_LEFT), $invoice->invoice_number);
    }

    public function test_rental_starting_after_due_day_gets_seven_day_grace(): void
    {
        $this->rental(start: '2026-11-15');
        $this->billing->generateForMonth(Carbon::parse('2026-11-01'));

        $this->assertSame('2026-11-22', Invoice::sole()->due_date->toDateString());
    }

    public function test_late_fee_is_daily_and_capped(): void
    {
        $this->rental(1500000);
        $this->billing->generateForMonth(Carbon::parse('2026-11-01')); // due 10 Nov, now 20 Nov
        $this->billing->generateForMonth(Carbon::parse('2026-09-01')); // due 10 Sep, 71 days late

        $this->billing->refreshOverdue();

        $nov = Invoice::forMonth(Carbon::parse('2026-11-01'))->sole();
        $sep = Invoice::forMonth(Carbon::parse('2026-09-01'))->sole();

        $this->assertSame('overdue', $nov->status);
        $this->assertEquals(100000, $nov->late_fee);  // 10 hari x 10.000
        $this->assertEquals(300000, $sep->late_fee);  // dibatasi 20% x 1.500.000
    }

    public function test_approving_payment_marks_invoices_paid_and_prepays_future_months(): void
    {
        $rental = $this->rental(1000000);
        $this->billing->generateForMonth(Carbon::parse('2026-11-01'));
        $invoice = Invoice::sole();

        $payment = $this->billing->recordPayment([
            'rental_id' => $rental->id, 'amount' => 3000000, 'total_months_paid' => 3, 'payment_date' => '2026-11-08',
        ]);
        $this->assertSame($invoice->id, $payment->invoice_id);
        $this->assertSame('pending', $invoice->fresh()->status);

        $paid = $this->billing->approvePayment($payment);

        $this->assertCount(3, $paid);
        $this->assertSame(['2026-11', '2026-12', '2027-01'], $paid->map(fn ($i) => $i->billing_month->format('Y-m'))->all());
        $this->assertSame(3, Invoice::where('status', 'paid')->count());
        $this->assertEquals(0, $invoice->fresh()->late_fee); // dibayar sebelum jatuh tempo
        $this->assertSame('approved', $payment->fresh()->status);
    }

    public function test_rejected_payment_returns_invoice_to_overdue(): void
    {
        $rental = $this->rental();
        $this->billing->generateForMonth(Carbon::parse('2026-11-01'));
        $payment = $this->billing->recordPayment(['rental_id' => $rental->id, 'amount' => 1, 'total_months_paid' => 1, 'payment_date' => '2026-11-19']);

        $this->billing->rejectPayment($payment, 'Bukti buram');

        $this->assertSame('overdue', Invoice::sole()->status);
        $this->assertSame('Bukti buram', $payment->fresh()->admin_notes);
    }

    public function test_monthly_report_aggregates_income_and_expenses(): void
    {
        $rental = $this->rental();
        Payment::create(['rental_id' => $rental->id, 'amount' => 1500000, 'total_months_paid' => 1, 'payment_date' => '2026-10-05', 'status' => 'approved']);
        Payment::create(['rental_id' => $rental->id, 'amount' => 999, 'total_months_paid' => 1, 'payment_date' => '2026-10-06', 'status' => 'pending']);
        Expense::create(['title' => 'Listrik', 'category' => 'listrik', 'amount' => 400000, 'expense_date' => '2026-10-02']);

        $october = $this->billing->monthlyReport(2026)->firstWhere('month', 10);

        $this->assertEquals(1500000, $october['income']);
        $this->assertEquals(400000, $october['expense']);
        $this->assertEquals(1100000, $october['net']);
    }

    public function test_finance_pages_render_with_demo_data(): void
    {
        $this->seed(FinanceDemoSeeder::class);
        $this->actingAs(User::where('role', 'admin')->firstOrFail());

        $this->get('/billing')->assertOk()->assertSee('Tunggakan Penghuni')->assertSee('INV-202611');
        $this->get('/billing?status=overdue')->assertOk();
        $this->get('/billing/create')->assertOk();
        $this->get('/expenses')->assertOk()->assertSee('Laporan Pemasukan vs Pengeluaran 2026');
        $this->get('/expenses/create')->assertOk();
        $this->get('/overview')->assertOk();
        $this->get('/billing/export')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->get('/expenses/report/export')->assertOk();
    }

    public function test_admin_can_approve_payment_and_record_expense_via_http(): void
    {
        $rental = $this->rental();
        $this->billing->generateForMonth();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->post('/billing', ['rental_id' => $rental->id, 'amount' => 1600000, 'total_months_paid' => 1, 'payment_date' => '2026-11-20'])
            ->assertRedirect('/billing');
        $payment = Payment::sole();

        $this->patch("/billing/payments/{$payment->id}/approve")->assertRedirect();
        $this->assertSame('paid', Invoice::sole()->status);
        $this->assertEquals(100000, Invoice::sole()->late_fee);

        $this->post('/expenses', ['title' => 'Air', 'category' => 'air', 'amount' => 300000, 'expense_date' => '2026-11-03'])->assertRedirect();
        $this->assertDatabaseHas('expenses', ['title' => 'Air', 'category' => 'air']);

        $this->post('/expenses', ['title' => 'X', 'category' => 'bukan-kategori', 'amount' => 1, 'expense_date' => '2026-11-03'])
            ->assertSessionHasErrors('category');
    }
}

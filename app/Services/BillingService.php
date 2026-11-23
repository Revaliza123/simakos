<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Rental;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BillingService
{
    /**
     * Terbitkan semua tagihan untuk bulan tertentu: sewa kamar (sesuai siklus bayar tiap kontrak)
     * dan tagihan lain (charge) yang berlaku bulan itu.
     * Aman dijalankan berulang kali: tagihan yang sudah ada tidak diduplikasi.
     *
     * @return int jumlah tagihan baru yang dibuat
     */
    public function generateForMonth(?CarbonInterface $month = null): int
    {
        $monthStart = Carbon::parse($month ?? now())->startOfMonth();

        return $this->generateRentForMonth($monthStart) + $this->generateChargesForMonth($monthStart);
    }

    /**
     * Tagihan sewa. Kontrak bulanan ditagih tiap bulan; kontrak dengan siklus 3/6/12 bulan hanya ditagih
     * di awal tiap siklus dengan nominal sewa x jumlah bulan. Bulan yang sudah tercakup tagihan
     * sebelumnya (termasuk hasil bayar di muka) dilewati.
     */
    public function generateRentForMonth(CarbonInterface $monthStart): int
    {
        $monthStart = Carbon::parse($monthStart)->startOfMonth();
        $created = 0;

        foreach ($this->activeRentalsIn($monthStart) as $rental) {
            if ($this->rentInvoiceCovering($rental, $monthStart)) {
                continue;
            }

            if ($this->createRentInvoice($rental, $monthStart, $this->periodFor($rental, $monthStart))) {
                $created++;
            }
        }

        return $created;
    }

    /** Terbitkan tagihan lain (gas, iuran, dll.) yang berlaku pada bulan tertentu. */
    public function generateChargesForMonth(CarbonInterface $monthStart): int
    {
        $monthStart = Carbon::parse($monthStart)->startOfMonth();

        return Charge::dueInMonth($monthStart)->get()
            ->sum(fn (Charge $charge) => $this->issueCharge($charge, $monthStart));
    }

    /**
     * Buat invoice charge untuk setiap penghuni target pada bulan tertentu.
     * Mode "split": total dibagi rata ke jumlah penghuni saat diterbitkan (sisa pembulatan ke penghuni pertama),
     * dan tidak diterbitkan ulang. Mode "per_tenant": penghuni yang belum ditagih (mis. baru check-in) ikut ditagih.
     */
    public function issueCharge(Charge $charge, CarbonInterface $monthStart): int
    {
        $monthStart = Carbon::parse($monthStart)->startOfMonth();

        $rentals = $this->activeRentalsIn($monthStart)
            ->when($charge->target === 'rooms', fn (Collection $c) => $c->whereIn('room_id', $charge->room_ids ?? []))
            ->sortBy(fn (Rental $r) => $r->room?->room_number)
            ->values();

        if ($rentals->isEmpty()) {
            return 0;
        }

        $alreadyBilled = Invoice::charges()->where('charge_id', $charge->id)->forMonth($monthStart)->pluck('rental_id');

        if ($charge->amount_mode === 'split' && $alreadyBilled->isNotEmpty()) {
            return 0;
        }

        $amounts = $this->chargeAmounts($charge, $rentals->count());
        $dueDate = $this->chargeDueDate($charge, $monthStart);
        $created = 0;

        foreach ($rentals as $index => $rental) {
            if ($alreadyBilled->contains($rental->id)) {
                continue;
            }

            Invoice::create([
                'invoice_number' => sprintf('CHG-%s-%04d-%04d', $monthStart->format('Ym'), $charge->id, $rental->id),
                'rental_id' => $rental->id,
                'type' => 'charge',
                'charge_id' => $charge->id,
                'title' => $charge->title,
                'billing_month' => $monthStart->toDateString(),
                'period_months' => 1,
                'due_date' => $dueDate->toDateString(),
                'amount' => $amounts[$index],
                'status' => 'unpaid',
            ]);
            $created++;
        }

        return $created;
    }

    /**
     * Rencana pembayaran sewa untuk N bulan: tagihan sewa tertunggak (yang dipilih didahulukan),
     * lalu bulan-bulan berikutnya bila bayar di muka. Satu tagihan siklus 3/6/12 bulan dibayar utuh.
     *
     * @return Collection<int, array{invoice: ?Invoice, month: Carbon, period: int, amount: float}>
     */
    public function rentPaymentPlan(Rental $rental, int $months, ?int $invoiceId = null, bool $includePending = false): Collection
    {
        $rental->loadMissing('room');
        $months = max(1, $months);

        $outstanding = Invoice::rent()
            ->where('rental_id', $rental->id)
            ->whereIn('status', $includePending ? ['unpaid', 'pending', 'overdue'] : ['unpaid', 'overdue'])
            ->when($invoiceId, fn ($q) => $q->orderByRaw('id = ? desc', [$invoiceId]))
            ->orderBy('billing_month')
            ->get();

        $plan = collect();
        $covered = 0;

        foreach ($outstanding as $invoice) {
            if ($covered >= $months) {
                break;
            }
            $plan->push(['invoice' => $invoice, 'month' => $invoice->billing_month->copy(), 'period' => (int) $invoice->period_months, 'amount' => $invoice->totalDue()]);
            $covered += max(1, (int) $invoice->period_months);
        }

        $next = $this->nextBillingMonth($rental);
        while ($covered < $months) {
            $period = $this->periodFor($rental, $next);
            $plan->push(['invoice' => null, 'month' => $next->copy(), 'period' => $period, 'amount' => (float) $rental->room->price * $period]);
            $covered += $period;
            $next = $next->copy()->addMonthsNoOverflow($period);
        }

        return $plan;
    }

    /** Pilihan "jumlah bulan dibayar" yang sesuai siklus bayar kontrak (kelipatan siklus, maks. 12 bulan). */
    public function monthOptionsFor(Rental $rental): array
    {
        $cycle = max(1, (int) $rental->billing_cycle);

        return $cycle === 1 ? [1, 2, 3, 6, 12] : range($cycle, 12, $cycle);
    }

    /**
     * Perbarui status & denda untuk tagihan yang sudah lewat jatuh tempo.
     *
     * @return int jumlah tagihan yang diperbarui
     */
    public function refreshOverdue(?CarbonInterface $today = null): int
    {
        $today = Carbon::parse($today ?? now())->startOfDay();
        $updated = 0;

        Invoice::whereIn('status', ['unpaid', 'overdue'])
            ->whereDate('due_date', '<', $today)
            ->each(function (Invoice $invoice) use ($today, &$updated): void {
                $invoice->late_fee = $invoice->calculateLateFee($today);
                $invoice->status = 'overdue';

                if ($invoice->isDirty()) {
                    $invoice->save();
                    $updated++;
                }
            });

        return $updated;
    }

    /**
     * Catat pembayaran dari penghuni. Jika tagihan tidak dipilih, otomatis
     * dihubungkan ke tagihan tertunggak paling lama milik kontrak tersebut.
     */
    public function recordPayment(array $data): Payment
    {
        return DB::transaction(function () use ($data): Payment {
            if (empty($data['invoice_id'])) {
                $data['invoice_id'] = Invoice::rent()->where('rental_id', $data['rental_id'])
                    ->whereIn('status', ['unpaid', 'overdue'])
                    ->orderBy('billing_month')
                    ->value('id');
            }

            $payment = Payment::create([...$data, 'status' => 'pending']);

            if ($payment->invoice_id) {
                Invoice::whereKey($payment->invoice_id)
                    ->whereIn('status', ['unpaid', 'overdue'])
                    ->update(['status' => 'pending']);
            }

            return $payment;
        });
    }

    /**
     * Setujui pembayaran.
     * - Tagihan lain (charge): hanya tagihan tersebut yang ditandai lunas.
     * - Sewa: tagihan sewa dilunasi sebanyak jumlah bulan yang dibayar. Bila melebihi tagihan yang ada,
     *   tagihan siklus berikutnya dibuat dan langsung ditandai lunas (bayar di muka).
     *
     * @return Collection<int, Invoice> tagihan yang dilunasi
     */
    public function approvePayment(Payment $payment, ?string $notes = null): Collection
    {
        return DB::transaction(function () use ($payment, $notes): Collection {
            $payment->loadMissing('rental.room', 'invoice');
            $paidAt = Carbon::parse($payment->payment_date ?? now());

            if ($payment->invoice && ! $payment->invoice->isRent()) {
                $invoices = collect([$payment->invoice]);
            } else {
                $invoices = $this->rentPaymentPlan($payment->rental, (int) $payment->total_months_paid, $payment->invoice_id, includePending: true)
                    ->map(fn (array $item) => $item['invoice']
                        ?? $this->createRentInvoice($payment->rental, $item['month'], $item['period'], force: true));
            }

            foreach ($invoices as $invoice) {
                $invoice->update([
                    'late_fee' => $invoice->calculateLateFee($paidAt),
                    'status' => 'paid',
                    'paid_at' => $paidAt,
                ]);
            }

            $payment->update([
                'status' => 'approved',
                'invoice_id' => $payment->invoice_id ?? $invoices->first()?->id,
                'admin_notes' => $notes ?? $payment->admin_notes,
            ]);

            return $invoices->values();
        });
    }

    public function rejectPayment(Payment $payment, ?string $notes = null): void
    {
        DB::transaction(function () use ($payment, $notes): void {
            $payment->update(['status' => 'rejected', 'admin_notes' => $notes ?? $payment->admin_notes]);

            if ($payment->invoice_id) {
                Invoice::whereKey($payment->invoice_id)->where('status', 'pending')->update(['status' => 'unpaid']);
            }
        });

        $this->refreshOverdue();
    }

    /** Ringkasan angka untuk kartu statistik halaman billing. */
    public function summary(?CarbonInterface $month = null): array
    {
        $month = Carbon::parse($month ?? now())->startOfMonth();

        $outstanding = Invoice::outstanding()->get();

        return [
            'paid_this_month' => (float) Payment::where('status', 'approved')
                ->whereBetween('payment_date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
                ->sum('amount'),
            'pending_validation' => (float) Payment::where('status', 'pending')->sum('amount'),
            'pending_validation_count' => Payment::where('status', 'pending')->count(),
            'unpaid' => $outstanding->whereIn('status', ['unpaid', 'pending'])->sum(fn (Invoice $i) => $i->totalDue()),
            'overdue' => $outstanding->where('status', 'overdue')->sum(fn (Invoice $i) => $i->totalDue()),
            'overdue_count' => $outstanding->where('status', 'overdue')->count(),
            'late_fees' => (float) $outstanding->sum('late_fee'),
            'billed_this_month' => (float) Invoice::forMonth($month)->sum('amount'),
        ];
    }

    /**
     * Rekap tunggakan per penghuni (kontrak) yang masih punya tagihan belum lunas.
     *
     * @return Collection<int, array>
     */
    public function arrears(): Collection
    {
        return Invoice::with('rental.user', 'rental.room')
            ->outstanding()
            ->get()
            ->groupBy('rental_id')
            ->map(fn (Collection $invoices) => [
                'rental' => $invoices->first()->rental,
                'months' => (int) $invoices->where('type', 'rent')->sum('period_months'),
                'charges' => $invoices->where('type', 'charge')->count(),
                'amount' => (float) $invoices->sum('amount'),
                'late_fee' => (float) $invoices->sum('late_fee'),
                'total' => $invoices->sum(fn (Invoice $i) => $i->totalDue()),
                'oldest' => $invoices->min('billing_month'),
            ])
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Laporan keuangan bulanan (pemasukan vs pengeluaran) untuk satu tahun.
     *
     * @return Collection<int, array>
     */
    public function monthlyReport(int $year): Collection
    {
        $income = Payment::where('status', 'approved')
            ->whereYear('payment_date', $year)
            ->get(['amount', 'payment_date'])
            ->groupBy(fn (Payment $p) => (int) $p->payment_date->format('n'))
            ->map(fn (Collection $rows) => (float) $rows->sum('amount'));

        $expenses = Expense::whereYear('expense_date', $year)
            ->get(['amount', 'expense_date'])
            ->groupBy(fn (Expense $e) => (int) $e->expense_date->format('n'))
            ->map(fn (Collection $rows) => (float) $rows->sum('amount'));

        $billed = Invoice::whereYear('billing_month', $year)
            ->get(['amount', 'late_fee', 'billing_month'])
            ->groupBy(fn (Invoice $i) => (int) $i->billing_month->format('n'))
            ->map(fn (Collection $rows) => $rows->sum(fn (Invoice $i) => $i->totalDue()));

        return collect(range(1, 12))->map(fn (int $m) => [
            'month' => $m,
            'label' => Carbon::create($year, $m, 1)->translatedFormat('M'),
            'billed' => (float) ($billed[$m] ?? 0),
            'income' => (float) ($income[$m] ?? 0),
            'expense' => (float) ($expenses[$m] ?? 0),
            'net' => (float) ($income[$m] ?? 0) - (float) ($expenses[$m] ?? 0),
        ]);
    }

    public function dueDateFor(Rental $rental, CarbonInterface $monthStart): Carbon
    {
        $dueDay = min(max((int) config('billing.due_day'), 1), 28);
        $due = Carbon::parse($monthStart)->startOfMonth()->day($dueDay);

        // Kontrak yang baru mulai setelah tanggal jatuh tempo diberi tenggat 7 hari.
        if ($rental->start_date && $rental->start_date->greaterThanOrEqualTo($due)) {
            $due = $rental->start_date->copy()->addDays(7);
        }

        return $due;
    }

    private function createRentInvoice(Rental $rental, CarbonInterface $monthStart, int $period = 1, bool $force = false): ?Invoice
    {
        $monthStart = Carbon::parse($monthStart)->startOfMonth();
        $number = sprintf('INV-%s-%04d', $monthStart->format('Ym'), $rental->id);

        $existing = Invoice::where('invoice_number', $number)->first();
        if ($existing) {
            return $force ? $existing : null;
        }

        $period = max(1, $period);

        return Invoice::create([
            'invoice_number' => $number,
            'rental_id' => $rental->id,
            'type' => 'rent',
            'billing_month' => $monthStart->toDateString(),
            'period_months' => $period,
            'due_date' => $this->dueDateFor($rental, $monthStart)->toDateString(),
            'amount' => (float) ($rental->room?->price ?? 0) * $period,
            'status' => 'unpaid',
        ]);
    }

    /** Tagihan sewa yang sudah mencakup bulan tertentu (mis. tagihan 6 bulan yang diterbitkan 3 bulan lalu). */
    private function rentInvoiceCovering(Rental $rental, Carbon $monthStart): ?Invoice
    {
        return Invoice::rent()
            ->where('rental_id', $rental->id)
            ->whereDate('billing_month', '<=', $monthStart->toDateString())
            ->orderByDesc('billing_month')
            ->get()
            ->first(fn (Invoice $invoice) => $invoice->coversUntil()->greaterThanOrEqualTo($monthStart));
    }

    /**
     * Jumlah bulan yang dicakup tagihan sewa yang dimulai pada bulan tertentu:
     * sisa bulan sampai batas siklus berikutnya, dan tidak melewati akhir kontrak.
     */
    private function periodFor(Rental $rental, Carbon $monthStart): int
    {
        $cycle = max(1, (int) $rental->billing_cycle);
        $startMonth = Carbon::parse($rental->start_date ?? $monthStart)->startOfMonth();
        $offset = $monthStart->greaterThan($startMonth) ? (int) $startMonth->diffInMonths($monthStart) : 0;
        $period = $cycle - ($offset % $cycle);

        if ($rental->end_date) {
            $monthsLeft = (int) $monthStart->diffInMonths($rental->end_date->copy()->startOfMonth()) + 1;
            $period = min($period, max(1, $monthsLeft));
        }

        return max(1, $period);
    }

    private function nextBillingMonth(Rental $rental): Carbon
    {
        $last = Invoice::rent()->where('rental_id', $rental->id)->orderByDesc('billing_month')->first();

        return $last
            ? $last->coversUntil()->addMonthNoOverflow()
            : Carbon::parse($rental->start_date ?? now())->startOfMonth();
    }

    /** @return Collection<int, Rental> kontrak aktif yang masa sewanya mencakup bulan tertentu */
    private function activeRentalsIn(Carbon $monthStart): Collection
    {
        return Rental::with('room')
            ->where('status', 'active')
            ->whereDate('start_date', '<=', $monthStart->copy()->endOfMonth())
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $monthStart))
            ->get();
    }

    /** @return array<int, float> nominal per penghuni (urut sesuai daftar penghuni) */
    private function chargeAmounts(Charge $charge, int $count): array
    {
        $total = (float) $charge->amount;

        if ($charge->amount_mode !== 'split') {
            return array_fill(0, $count, $total);
        }

        $share = floor($total / $count);
        $amounts = array_fill(0, $count, $share);
        $amounts[0] += round($total - $share * $count, 2);

        return $amounts;
    }

    /** Jatuh tempo charge: tanggal due_day pada bulan tagihan; bila sudah lewat saat diterbitkan, 7 hari dari hari ini. */
    private function chargeDueDate(Charge $charge, Carbon $monthStart): Carbon
    {
        $due = $monthStart->copy()->day(min(max((int) $charge->due_day, 1), 28));
        $minimum = now()->startOfDay()->addDays(7);

        return $due->lessThan(now()->startOfDay()) && $monthStart->isSameMonth(now()) ? $minimum : $due;
    }
}

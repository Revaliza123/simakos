<?php

namespace App\Services;

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
     * Buat tagihan bulanan untuk setiap kontrak aktif pada bulan tertentu.
     * Aman dijalankan berulang kali: tagihan yang sudah ada tidak diduplikasi.
     *
     * @return int jumlah tagihan baru yang dibuat
     */
    public function generateForMonth(?CarbonInterface $month = null): int
    {
        $monthStart = Carbon::parse($month ?? now())->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $rentals = Rental::with('room')
            ->where('status', 'active')
            ->whereDate('start_date', '<=', $monthEnd)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $monthStart))
            ->get();

        $created = 0;

        foreach ($rentals as $rental) {
            if ($this->createInvoice($rental, $monthStart)) {
                $created++;
            }
        }

        return $created;
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
                $data['invoice_id'] = Invoice::where('rental_id', $data['rental_id'])
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
     * Setujui pembayaran: tandai tagihan lunas sebanyak jumlah bulan yang dibayar.
     * Bila jumlah bulan melebihi tagihan yang ada, tagihan bulan berikutnya dibuat
     * dan langsung ditandai lunas (bayar di muka).
     *
     * @return Collection<int, Invoice> tagihan yang dilunasi
     */
    public function approvePayment(Payment $payment, ?string $notes = null): Collection
    {
        return DB::transaction(function () use ($payment, $notes): Collection {
            $payment->loadMissing('rental.room');
            $paidAt = $payment->payment_date ?? now();
            $months = max(1, (int) $payment->total_months_paid);

            $invoices = Invoice::where('rental_id', $payment->rental_id)
                ->outstanding()
                ->when($payment->invoice_id, fn ($q) => $q->orderByRaw('id = ? desc', [$payment->invoice_id]))
                ->orderBy('billing_month')
                ->limit($months)
                ->get();

            // Bayar di muka: buat tagihan bulan-bulan berikutnya.
            $nextMonth = $this->nextBillingMonth($payment->rental);
            while ($invoices->count() < $months) {
                $invoice = $this->createInvoice($payment->rental, $nextMonth, force: true);
                $invoices->push($invoice);
                $nextMonth = $nextMonth->copy()->addMonthNoOverflow();
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

            return $invoices;
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
                'months' => $invoices->count(),
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

    private function createInvoice(Rental $rental, CarbonInterface $monthStart, bool $force = false): ?Invoice
    {
        $monthStart = Carbon::parse($monthStart)->startOfMonth();

        $existing = Invoice::where('rental_id', $rental->id)->forMonth($monthStart)->first();
        if ($existing) {
            return $force ? $existing : null;
        }

        return Invoice::create([
            'invoice_number' => sprintf('INV-%s-%04d', $monthStart->format('Ym'), $rental->id),
            'rental_id' => $rental->id,
            'billing_month' => $monthStart->toDateString(),
            'due_date' => $this->dueDateFor($rental, $monthStart)->toDateString(),
            'amount' => (float) ($rental->room?->price ?? 0),
            'status' => 'unpaid',
        ]);
    }

    private function nextBillingMonth(Rental $rental): Carbon
    {
        $last = Invoice::where('rental_id', $rental->id)->max('billing_month');

        return $last
            ? Carbon::parse($last)->startOfMonth()->addMonthNoOverflow()
            : Carbon::parse($rental->start_date ?? now())->startOfMonth();
    }
}

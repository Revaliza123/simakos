<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Rental;
use App\Services\BillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    public function __construct(private readonly BillingService $billing) {}

    /* ------------------------------------------------------------------
     | Billing: tagihan & pembayaran
     * ------------------------------------------------------------------ */

    public function billing(Request $request): View
    {
        // Pengaman bila scheduler belum jalan: tagihan bulan berjalan & denda selalu up to date.
        $this->billing->generateForMonth();
        $this->billing->refreshOverdue();

        $month = $this->monthFromRequest($request);
        $status = $request->query('status');

        $invoices = Invoice::with('rental.user', 'rental.room')
            ->forMonth($month)
            ->when(array_key_exists((string) $status, Invoice::STATUS_LABELS), fn ($q) => $q->where('status', $status))
            ->orderBy('due_date')
            ->get();

        return view('dashboard.billing', [
            ...$this->sharedData('billing'),
            'month' => $month,
            'status' => $status,
            'invoices' => $invoices,
            'statusCounts' => Invoice::forMonth($month)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'payments' => Payment::with('rental.user', 'rental.room', 'invoice')->latest('payment_date')->latest('id')->limit(50)->get(),
            'summary' => $this->billing->summary($month),
            'arrears' => $this->billing->arrears(),
        ]);
    }

    public function generateInvoices(Request $request): RedirectResponse
    {
        $validated = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $month = Carbon::createFromFormat('!Y-m', $validated['month'])->startOfMonth();

        $created = $this->billing->generateForMonth($month);
        $this->billing->refreshOverdue();

        return redirect()->route('billing', ['month' => $month->format('Y-m')])
            ->with('status', "{$created} tagihan baru dibuat untuk {$month->translatedFormat('F Y')}.");
    }

    public function createPayment(Request $request): View
    {
        $this->billing->refreshOverdue();

        return view('dashboard.forms.payment', [
            ...$this->sharedData('billing'),
            'rentals' => Rental::with(['user', 'room'])->where('status', 'active')->get(),
            'invoices' => Invoice::with('rental.user', 'rental.room')->whereIn('status', ['unpaid', 'overdue'])->orderBy('billing_month')->get(),
            'selectedInvoice' => $request->integer('invoice') ?: null,
        ]);
    }

    public function storePayment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'rental_id' => ['required', 'exists:rentals,id'],
            'invoice_id' => ['nullable', Rule::exists('invoices', 'id')->where('rental_id', $request->input('rental_id'))],
            'amount' => ['required', 'numeric', 'min:0'],
            'total_months_paid' => ['required', 'integer', 'min:1', 'max:12'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'proof_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
        ], [
            'invoice_id.exists' => 'Tagihan yang dipilih bukan milik kontrak penghuni tersebut.',
            'proof_image.mimes' => 'Format file harus jpg, png.',
            'proof_image.max' => 'Ukuran file bukti transfer maksimal 2 MB.',
        ]);

        if ($request->hasFile('proof_image')) {
            $validated['proof_image'] = $request->file('proof_image')->store('payment-proofs', 'public');
        }

        $this->billing->recordPayment($validated);

        return redirect()->route('billing')->with('status', 'Pembayaran berhasil dicatat dan menunggu persetujuan.');
    }

    public function approvePayment(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless($payment->status === 'pending', 422, 'Pembayaran sudah diproses.');

        $invoices = $this->billing->approvePayment($payment, $request->input('admin_notes'));

        return back()->with('status', 'Pembayaran disetujui. '.$invoices->count().' tagihan ditandai lunas ('.$invoices->pluck('invoice_number')->join(', ').').');
    }

    public function rejectPayment(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless($payment->status === 'pending', 422, 'Pembayaran sudah diproses.');

        // FR-06: penolakan wajib disertai catatan alasan agar penghuni tahu apa yang harus diperbaiki.
        $validated = $request->validate([
            'admin_notes' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'admin_notes.required' => 'Alasan penolakan wajib diisi.',
            'admin_notes.min' => 'Alasan penolakan minimal 5 karakter.',
        ]);

        $this->billing->rejectPayment($payment, $validated['admin_notes']);

        return back()->with('status', 'Pembayaran ditolak. Penghuni dapat mengunggah ulang bukti yang sah.');
    }

    public function exportBilling(Request $request): StreamedResponse
    {
        $month = $this->monthFromRequest($request);
        $invoices = Invoice::with('rental.user', 'rental.room')->forMonth($month)->orderBy('due_date')->get();

        return $this->csv("simakos-tagihan-{$month->format('Y-m')}.csv",
            ['No. Tagihan', 'Penghuni', 'Kamar', 'Bulan', 'Jatuh Tempo', 'Sewa', 'Denda', 'Total', 'Status', 'Tanggal Lunas'],
            $invoices->map(fn (Invoice $i) => [
                $i->invoice_number,
                $i->rental?->user?->name ?? '-',
                $i->rental?->room?->room_number ?? '-',
                $i->billing_month->format('Y-m'),
                $i->due_date->format('Y-m-d'),
                (float) $i->amount,
                (float) $i->late_fee,
                $i->totalDue(),
                $i->statusLabel(),
                $i->paid_at?->format('Y-m-d') ?? '-',
            ]),
        );
    }

    /* ------------------------------------------------------------------
     | Expenses & laporan keuangan
     * ------------------------------------------------------------------ */

    public function expenses(Request $request): View
    {
        $year = (int) $request->query('year', now()->year);
        $month = $this->monthFromRequest($request);
        $category = $request->query('category');
        $categories = config('billing.expense_categories');

        $monthExpenses = Expense::whereYear('expense_date', $month->year)
            ->whereMonth('expense_date', $month->month)
            ->get();

        $report = $this->billing->monthlyReport($year);

        return view('dashboard.expenses', [
            ...$this->sharedData('expenses'),
            'year' => $year,
            'month' => $month,
            'category' => $category,
            'categories' => $categories,
            'expenses' => $monthExpenses
                ->when(array_key_exists((string) $category, $categories), fn ($c) => $c->where('category', $category))
                ->sortByDesc('expense_date')
                ->values(),
            'byCategory' => $monthExpenses->groupBy('category')
                ->map(fn ($rows) => (float) $rows->sum('amount'))
                ->sortDesc(),
            'monthTotal' => (float) $monthExpenses->sum('amount'),
            'monthIncome' => (float) Payment::where('status', 'approved')
                ->whereYear('payment_date', $month->year)->whereMonth('payment_date', $month->month)->sum('amount'),
            'report' => $report,
            'yearTotals' => [
                'billed' => $report->sum('billed'),
                'income' => $report->sum('income'),
                'expense' => $report->sum('expense'),
                'net' => $report->sum('net'),
            ],
        ]);
    }

    public function createExpense(): View
    {
        return view('dashboard.forms.expense', [
            ...$this->sharedData('expenses'),
            'categories' => config('billing.expense_categories'),
        ]);
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(array_keys(config('billing.expense_categories')))],
            'amount' => ['required', 'numeric', 'min:1'],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $expense = Expense::create($validated);

        return redirect()->route('expenses', ['month' => $expense->expense_date->format('Y-m'), 'year' => $expense->expense_date->year])
            ->with('status', 'Pengeluaran berhasil dicatat.');
    }

    public function destroyExpense(Expense $expense): RedirectResponse
    {
        $expense->delete();

        return back()->with('status', 'Pengeluaran dihapus.');
    }

    public function exportReport(Request $request): StreamedResponse
    {
        $year = (int) $request->query('year', now()->year);

        return $this->csv("simakos-laporan-keuangan-{$year}.csv",
            ['Bulan', 'Total Tagihan', 'Pemasukan', 'Pengeluaran', 'Laba Bersih'],
            $this->billing->monthlyReport($year)->map(fn (array $r) => [
                Carbon::create($year, $r['month'], 1)->format('Y-m'), $r['billed'], $r['income'], $r['expense'], $r['net'],
            ]),
        );
    }

    /* ------------------------------------------------------------------ */

    private function monthFromRequest(Request $request): Carbon
    {
        $value = (string) $request->query('month');

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value)
            ? Carbon::createFromFormat('!Y-m', $value)->startOfMonth()
            : now()->startOfMonth();
    }

    private function csv(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

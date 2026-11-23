<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Complaint;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Portal Tenant mandiri.
 * NFR-03: setiap query di sini hanya membaca data milik penghuni yang sedang login
 * (rental->user_id === auth()->id()); ID milik penghuni lain tidak pernah diterima dari request.
 */
class TenantPortalController extends Controller
{
    public function __construct(private readonly BillingService $billing) {}

    public function overview(Request $request): View
    {
        $user = $request->user();
        $this->syncInvoices();

        return view('tenant.overview', [
            ...$this->portalData($user, 'tenant-overview'),
            'outstanding' => $this->invoicesOf($user)->outstanding()->orderBy('billing_month')->get(),
            'latestPayment' => $this->paymentsOf($user)->latest('id')->first(),
            'complaints' => $this->complaintsOf($user)->latest()->limit(3)->get(),
            'announcements' => Announcement::published()->with('media')->latest('published_at')->limit(3)->get(),
        ]);
    }

    /** FR-04: tagihan bulanan tampil otomatis dengan status awal Belum Dibayar. */
    public function bills(Request $request): View
    {
        $user = $request->user();
        $this->syncInvoices();
        $rental = $user->activeRental();

        $payable = $rental
            ? Invoice::where('rental_id', $rental->id)->whereIn('status', ['unpaid', 'overdue'])->orderBy('type')->orderBy('billing_month')->get()
            : collect();
        $monthOptions = $rental ? $this->billing->monthOptionsFor($rental) : [];

        return view('tenant.bills', [
            ...$this->portalData($user, 'tenant-bills', $rental),
            'invoices' => $this->invoicesOf($user)->with('rental.room')->orderByDesc('billing_month')->orderBy('type')->get(),
            'payable' => $payable,
            'monthOptions' => $monthOptions,
            // Nominal dihitung server untuk setiap kombinasi tagihan x jumlah bulan (dipakai pratinjau di form).
            'amountPreview' => $rental ? $this->amountPreview($rental, $payable, $monthOptions) : [],
            'payments' => $this->paymentsOf($user)->with('invoice')->latest('id')->get(),
            'hasPendingPayment' => $rental && Payment::where('rental_id', $rental->id)->where('status', 'pending')->exists(),
            'selectedInvoice' => $request->integer('invoice') ?: null,
        ]);
    }

    /** FR-05 / UC-04: penghuni memilih durasi bulan dan mengunggah bukti transfer. */
    public function storePayment(Request $request): RedirectResponse
    {
        $rental = $request->user()->activeRental();

        if (! $rental) {
            return back()->withErrors(['rental' => 'Anda belum memiliki kontrak sewa aktif. Hubungi pengelola kos.']);
        }

        if (Payment::where('rental_id', $rental->id)->where('status', 'pending')->exists()) {
            return back()->withErrors(['proof_image' => 'Masih ada pembayaran yang menunggu validasi admin. Tunggu hingga diverifikasi sebelum mengunggah bukti baru.']);
        }

        $validated = $request->validate([
            'invoice_id' => [
                'nullable',
                Rule::exists('invoices', 'id')->where('rental_id', $rental->id)->whereIn('status', ['unpaid', 'overdue']),
            ],
            'total_months_paid' => ['required', 'integer', Rule::in($this->billing->monthOptionsFor($rental))],
            'proof_image' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
        ], [
            'total_months_paid.in' => 'Jumlah bulan harus sesuai siklus bayar kontrak Anda ('.$rental->billingCycleLabel().').',
            'invoice_id.exists' => 'Tagihan yang dipilih tidak valid atau sudah dibayar.',
            'proof_image.required' => 'Bukti transfer wajib diunggah.',
            'proof_image.mimes' => 'Format file harus jpg, png.',
            'proof_image.max' => 'Ukuran file bukti transfer maksimal 2 MB.',
        ]);

        $this->billing->refreshOverdue();
        $invoice = isset($validated['invoice_id']) ? Invoice::find($validated['invoice_id']) : null;
        // Tagihan lain (gas, iuran, dll.) dibayar per tagihan, bukan per bulan sewa.
        $months = $invoice && ! $invoice->isRent() ? 1 : (int) $validated['total_months_paid'];

        $this->billing->recordPayment([
            'rental_id' => $rental->id,
            'invoice_id' => $validated['invoice_id'] ?? null,
            'amount' => $this->amountFor($rental, $months, $validated['invoice_id'] ?? null),
            'total_months_paid' => $months,
            'payment_date' => now()->toDateString(),
            'proof_image' => $request->file('proof_image')->store('payment-proofs', 'public'),
        ]);

        return redirect()->route('tenant.bills')->with('status', 'Bukti pembayaran berhasil dikirim dan menunggu validasi admin.');
    }

    public function complaints(Request $request): View
    {
        $user = $request->user();

        return view('tenant.complaints', [
            ...$this->portalData($user, 'tenant-complaints'),
            'complaints' => $this->complaintsOf($user)->latest()->get(),
        ]);
    }

    public function createComplaint(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $rental = $user->activeRental();

        if (! $rental) {
            return redirect()->route('tenant.complaints')->withErrors(['rental' => 'Keluhan hanya dapat diajukan oleh penghuni dengan kontrak sewa aktif.']);
        }

        return view('tenant.complaint-form', $this->portalData($user, 'tenant-complaints', $rental));
    }

    /** FR-07 / UC-06: tiket keluhan terikat ke rental_id aktif milik penghuni, status awal Menunggu. */
    public function storeComplaint(Request $request): RedirectResponse
    {
        $rental = $request->user()->activeRental();

        if (! $rental) {
            return redirect()->route('tenant.complaints')->withErrors(['rental' => 'Keluhan hanya dapat diajukan oleh penghuni dengan kontrak sewa aktif.']);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
        ], [
            'title.required' => 'Judul keluhan wajib diisi.',
            'description.required' => 'Deskripsi kerusakan wajib diisi.',
            ...ComplaintController::photoMessages(),
        ]);

        Complaint::create([
            'rental_id' => $rental->id,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'photo' => $request->hasFile('photo') ? $request->file('photo')->store('complaint-photos', 'public') : null,
            'status' => 'open',
        ]);

        return redirect()->route('tenant.complaints')->with('status', 'Keluhan berhasil dikirim. Pantau status penanganannya di halaman ini.');
    }

    public function announcements(Request $request): View
    {
        return view('tenant.announcements', [
            ...$this->portalData($request->user(), 'tenant-announcements'),
            'announcements' => Announcement::published()->with(['creator', 'media'])->latest('published_at')->get(),
        ]);
    }

    /* ------------------------------------------------------------------ */

    private function portalData(User $user, string $activeMenu, ?Rental $rental = null): array
    {
        $rental ??= $user->activeRental();

        return [
            'activeMenu' => $activeMenu,
            'rental' => $rental,
            'tenant' => [
                'name' => $user->name,
                'email' => $user->email,
                'room' => $rental?->room?->room_number ?? 'Belum ditentukan',
                'floor' => $rental?->room?->roomType?->name ?? 'Properti SIMAKOS',
                'lease_end' => $rental?->end_date?->translatedFormat('d M Y') ?? ($rental ? 'Tanpa batas waktu' : 'Belum ada kontrak'),
            ],
        ];
    }

    /** Tagihan dari seluruh kontrak milik penghuni (termasuk tunggakan kontrak lama). */
    private function invoicesOf(User $user)
    {
        return Invoice::whereHas('rental', fn ($q) => $q->where('user_id', $user->id));
    }

    private function paymentsOf(User $user)
    {
        return Payment::whereHas('rental', fn ($q) => $q->where('user_id', $user->id));
    }

    private function complaintsOf(User $user)
    {
        return Complaint::with('rental.room')->whereHas('rental', fn ($q) => $q->where('user_id', $user->id));
    }

    /** Pastikan tagihan bulan berjalan & denda terbaru tersedia walau scheduler belum jalan. */
    private function syncInvoices(): void
    {
        $this->billing->generateForMonth();
        $this->billing->refreshOverdue();
    }

    /**
     * Hitung nominal di sisi server (penghuni tidak mengetik nominal sendiri):
     * - tagihan lain: total tagihan tersebut;
     * - sewa: tagihan sewa tertunggak yang tercakup (termasuk denda) + sewa siklus berikutnya bila bayar di muka.
     */
    private function amountFor(Rental $rental, int $months, ?int $invoiceId): float
    {
        $invoice = $invoiceId ? Invoice::find($invoiceId) : null;

        if ($invoice && ! $invoice->isRent()) {
            return round($invoice->totalDue(), 2);
        }

        return round($this->billing->rentPaymentPlan($rental, $months, $invoiceId)->sum('amount'), 2);
    }

    /** @return array<string, array<int, float>> [invoiceId|"auto" => [jumlah bulan => nominal]] */
    private function amountPreview(Rental $rental, Collection $payable, array $monthOptions): array
    {
        $preview = [];

        foreach ([null, ...$payable->pluck('id')->all()] as $invoiceId) {
            foreach ($monthOptions as $months) {
                $preview[$invoiceId ?? 'auto'][$months] = $this->amountFor($rental, $months, $invoiceId);
            }
        }

        return $preview;
    }
}

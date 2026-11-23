<?php

namespace App\Http\Controllers;

use App\Models\Rental;
use App\Models\Room;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * UC-03 Kelola Penyewaan Kamar (FR-03, BR-02).
 * Check-in mengunci status kamar menjadi Occupied, check-out mengembalikannya ke Available,
 * dan seluruh riwayat penyewaan tetap tersimpan di tabel rentals.
 */
class RentalController extends Controller
{
    public function __construct(private readonly BillingService $billing) {}

    public function index(Request $request): View
    {
        $status = $request->query('status');

        $rentals = Rental::with(['user', 'room.roomType'])
            ->when(in_array($status, ['active', 'completed', 'cancelled'], true), fn ($q) => $q->where('status', $status))
            ->orderByRaw("case when status = 'active' then 0 else 1 end")
            ->latest('start_date')
            ->get();

        return view('dashboard.rentals', [
            ...$this->sharedData('rentals'),
            'rentals' => $rentals,
            'status' => $status,
            'counts' => Rental::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    /** Check-in untuk penghuni yang sudah memiliki akun. */
    public function create(Request $request): View
    {
        return view('dashboard.forms.rental', [
            ...$this->sharedData('rentals'),
            'tenants' => User::where('role', 'tenant')
                ->whereDoesntHave('rentals', fn ($q) => $q->where('status', 'active'))
                ->orderBy('name')
                ->get(),
            'rooms' => $this->availableRooms(),
            'selectedRoom' => $request->integer('room') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            ...$this->rentalRules(),
        ]);

        abort_unless(User::whereKey($validated['user_id'])->where('role', 'tenant')->exists(), 422, 'Akun yang dipilih bukan penghuni.');

        $rental = $this->checkIn(fn () => (int) $validated['user_id'], $validated);

        return redirect()->route('rentals.index')
            ->with('status', "Check-in berhasil. Kamar {$rental->room->room_number} kini berstatus Terisi.");
    }

    /** Daftarkan akun penghuni baru sekaligus check-in (BPMN-TOBE-02 langkah 2). */
    public function createTenant(Request $request): View
    {
        return view('dashboard.forms.tenant', [
            ...$this->sharedData('rentals'),
            'rooms' => $this->availableRooms(),
            'selectedRoom' => $request->integer('room') ?: null,
        ]);
    }

    public function storeTenant(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'emergency_contact' => ['nullable', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            ...$this->rentalRules(),
        ]);

        $rental = $this->checkIn(fn () => User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone_number' => $validated['phone_number'] ?? null,
            'emergency_contact' => $validated['emergency_contact'] ?? null,
            'password' => $validated['password'],
            'role' => 'tenant',
        ])->id, $validated);

        return redirect()->route('rentals.index')
            ->with('status', "Penghuni {$validated['name']} terdaftar dan menempati Kamar {$rental->room->room_number}.");
    }

    public function checkout(Request $request, Rental $rental): RedirectResponse
    {
        if ($rental->status !== 'active') {
            return back()->withErrors(['rental' => 'Kontrak sewa ini sudah tidak aktif.']);
        }

        $validated = $request->validate([
            'checkout_date' => ['nullable', 'date', 'after_or_equal:'.$rental->start_date->toDateString()],
        ]);

        DB::transaction(function () use ($rental, $validated): void {
            $rental->update([
                'status' => 'completed',
                'end_date' => $validated['checkout_date'] ?? now()->toDateString(),
            ]);

            // Kamar otomatis kembali Tersedia setelah penghuni check-out (FR-03).
            $rental->room()->update(['status' => 'available']);
        });

        return back()->with('status', "Check-out berhasil. Kamar {$rental->room->room_number} kembali Tersedia.");
    }

    /**
     * Ubah siklus bayar kontrak aktif. Berlaku untuk tagihan yang diterbitkan berikutnya;
     * tagihan yang sudah terbit tidak diubah.
     */
    public function updateBillingCycle(Request $request, Rental $rental): RedirectResponse
    {
        abort_unless($rental->status === 'active', 422, 'Kontrak sudah tidak aktif.');

        $validated = $request->validate(['billing_cycle' => ['required', Rule::in(array_keys(Rental::BILLING_CYCLES))]]);
        $rental->update($validated);

        return back()->with('status', "Siklus bayar {$rental->user?->name} diubah menjadi {$rental->billingCycleLabel()}. Berlaku untuk tagihan berikutnya.");
    }

    private function rentalRules(): array
    {
        return [
            'room_id' => ['required', 'exists:rooms,id'],
            'start_date' => ['required', 'date'],
            'duration_months' => ['nullable', 'integer', 'min:1', 'max:60'],
            'billing_cycle' => ['nullable', Rule::in(array_keys(Rental::BILLING_CYCLES))],
        ];
    }

    private function availableRooms()
    {
        // UC-03 alternate flow 1a: kamar Occupied tidak ditampilkan pada daftar pilihan.
        return Room::with('roomType')->where('status', 'available')->orderBy('room_number')->get();
    }

    /**
     * Simpan transaksi sewa dan kunci kamar dalam satu transaksi database.
     * Validasi ulang di sisi server mencegah double-booking meskipun request dimanipulasi.
     *
     * @param  callable(): int  $resolveUserId
     */
    private function checkIn(callable $resolveUserId, array $data): Rental
    {
        $rental = DB::transaction(function () use ($resolveUserId, $data): Rental {
            $room = Room::whereKey($data['room_id'])->lockForUpdate()->first();

            if (! $room || $room->status !== 'available' || $room->rentals()->where('status', 'active')->exists()) {
                throw ValidationException::withMessages([
                    'room_id' => 'Kamar sudah terisi atau sedang tidak tersedia. Silakan pilih kamar lain.',
                ]);
            }

            $userId = $resolveUserId();

            if (Rental::where('user_id', $userId)->where('status', 'active')->exists()) {
                throw ValidationException::withMessages([
                    'user_id' => 'Penghuni ini masih memiliki kontrak sewa aktif. Lakukan check-out terlebih dahulu.',
                ]);
            }

            $start = Carbon::parse($data['start_date'])->startOfDay();
            $months = (int) ($data['duration_months'] ?? 0);

            $rental = Rental::create([
                'user_id' => $userId,
                'room_id' => $room->id,
                'start_date' => $start->toDateString(),
                'end_date' => $months > 0 ? $start->copy()->addMonthsNoOverflow($months)->subDay()->toDateString() : null,
                'billing_cycle' => (int) ($data['billing_cycle'] ?? 1),
                'status' => 'active',
            ]);

            $room->update(['status' => 'occupied']);

            return $rental->setRelation('room', $room);
        });

        // Langsung terbitkan tagihan bulan berjalan untuk kontrak baru.
        $this->billing->generateForMonth();

        return $rental;
    }
}

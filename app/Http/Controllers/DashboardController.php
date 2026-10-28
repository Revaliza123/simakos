<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Complaint;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Response;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function overview(): View
    {
        return view('dashboard.overview', $this->sharedData('overview'));
    }

    public function rooms(): View
    {
        $data = $this->sharedData('rooms');
        $data['roomsData'] = Schema::hasTable('rooms')
            ? Room::with(['roomType', 'rentals.user'])->latest()->get()->map(function (Room $room): array {
                $rental = $room->rentals->firstWhere('status', 'active');

                return [
                    'number' => $room->room_number,
                    'type' => $room->roomType?->name ?? 'Tanpa tipe',
                    'floor' => 'Properti SIMAKOS',
                    'price' => 'Rp '.number_format((float) $room->price, 0, ',', '.'),
                    'status' => ucfirst($room->status),
                    'tenant' => $rental?->user?->name ?? ($room->status === 'available' ? 'Siap huni' : 'Belum ada data penghuni'),
                    'tone' => $room->status,
                ];
            })
            : collect($data['roomsData']);

        return view('dashboard.rooms', $data);
    }

    public function createRoom(): View
    {
        return view('dashboard.forms.room', array_merge($this->sharedData('rooms'), [
            'roomTypes' => Schema::hasTable('room_types') ? RoomType::orderBy('name')->get() : collect(),
        ]));
    }

    public function storeRoom(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'room_number' => ['required', 'string', 'max:10', 'unique:rooms,room_number'],
            'room_type_id' => ['nullable', 'exists:room_types,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:available,occupied,maintenance'],
        ]);

        Room::create($validated);

        return redirect()->route('rooms')->with('status', 'Kamar berhasil ditambahkan.');
    }

    public function billing(): View
    {
        $data = $this->sharedData('billing');
        $data['payments'] = Schema::hasTable('payments') ? Payment::with('rental.user', 'rental.room')->latest()->get() : collect();

        return view('dashboard.billing', $data);
    }

    public function createPayment(): View
    {
        return view('dashboard.forms.payment', array_merge($this->sharedData('billing'), [
            'rentals' => Schema::hasTable('rentals') ? Rental::with(['user', 'room'])->where('status', 'active')->get() : collect(),
        ]));
    }

    public function storePayment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'rental_id' => ['required', 'exists:rentals,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'total_months_paid' => ['required', 'integer', 'min:1'],
            'payment_date' => ['required', 'date'],
            'proof_image' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('proof_image')) {
            $validated['proof_image'] = $request->file('proof_image')->store('payment-proofs', 'public');
        }

        Payment::create($validated);

        return redirect()->route('billing')->with('status', 'Pembayaran berhasil dicatat dan menunggu persetujuan.');
    }

    public function exportBilling(): Response
    {
        $payments = Schema::hasTable('payments') ? Payment::with('rental.user', 'rental.room')->latest()->get() : collect();
        $csv = "Penghuni,Kamar,Nominal,Tanggal,Status\n";

        foreach ($payments as $payment) {
            $csv .= sprintf("%s,%s,%s,%s,%s\n", $payment->rental?->user?->name ?? '-', $payment->rental?->room?->room_number ?? '-', $payment->amount, $payment->payment_date?->format('Y-m-d') ?? '-', $payment->status);
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="simakos-payments.csv"',
        ]);
    }

    public function complaints(): View
    {
        $data = $this->sharedData('complaints');
        $data['complaints'] = Schema::hasTable('complaints') ? Complaint::with('rental.user', 'rental.room')->latest()->get() : collect();

        return view('dashboard.complaints', $data);
    }

    public function createComplaint(): View
    {
        return view('dashboard.forms.complaint', array_merge($this->sharedData('complaints'), [
            'rentals' => Schema::hasTable('rentals') ? Rental::with(['user', 'room'])->where('status', 'active')->get() : collect(),
        ]));
    }

    public function storeComplaint(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'rental_id' => ['required', 'exists:rentals,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
        ]);

        Complaint::create($validated);

        return redirect()->route('complaints')->with('status', 'Tiket keluhan berhasil dibuat.');
    }

    public function announcements(): View
    {
        $data = $this->sharedData('announcements');
        $data['announcements'] = Schema::hasTable('announcements') ? Announcement::with('creator')->latest()->get() : collect();

        return view('dashboard.announcements', $data);
    }

    public function createAnnouncement(): View
    {
        return view('dashboard.forms.announcement', $this->sharedData('announcements'));
    }

    public function storeAnnouncement(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ]);

        $admin = User::firstOrCreate(
            ['email' => 'admin@simakos.id'],
            ['name' => 'Admin SIMAKOS', 'password' => Hash::make('password'), 'role' => 'admin'],
        );

        Announcement::create([...$validated, 'created_by' => $admin->id]);

        return redirect()->route('announcements')->with('status', 'Pengumuman berhasil dipublikasikan.');
    }

    public function createTenant(): View
    {
        return view('dashboard.forms.tenant', array_merge($this->sharedData('overview'), [
            'rooms' => Schema::hasTable('rooms') ? Room::where('status', 'available')->orderBy('room_number')->get() : collect(),
        ]));
    }

    public function storeTenant(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'room_id' => ['required', 'exists:rooms,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
        ]);

        DB::transaction(function () use ($validated): void {
            $tenant = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => 'tenant',
            ]);

            Rental::create([
                'user_id' => $tenant->id,
                'room_id' => $validated['room_id'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'] ?? null,
                'status' => 'active',
            ]);

            Room::whereKey($validated['room_id'])->update(['status' => 'occupied']);
        });

        return redirect()->route('overview')->with('status', 'Penghuni dan kontrak sewa berhasil ditambahkan.');
    }

    public function settings(): View
    {
        return view('dashboard.settings', $this->sharedData('settings'));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $admin = User::firstOrNew(['email' => 'admin@simakos.id']);
        $admin->name = $validated['name'];
        $admin->email = $validated['email'];
        $admin->role = 'admin';
        $admin->password ??= Hash::make('password');
        $admin->save();

        return redirect()->route('settings')->with('status', 'Pengaturan berhasil disimpan.');
    }

    public function logout(): RedirectResponse
    {
        return redirect()->route('overview')->with('status', 'Sesi demo tetap aktif untuk preview desain.');
    }

    private function sharedData(string $activeMenu): array
    {
        return [
            'activeMenu' => $activeMenu,
            'pageTitles' => [
                'overview' => ['Overview', 'Ringkasan operasional dan keuangan kost'],
                'rooms' => ['Room Management', 'Pantau ketersediaan dan kondisi setiap kamar'],
                'billing' => ['Payment & Billing', 'Kelola tagihan, pembayaran, dan rekonsiliasi sewa'],
                'complaints' => ['Complaints & Tickets', 'Tindak lanjuti laporan dan permintaan penghuni'],
                'announcements' => ['Announcements', 'Sampaikan informasi penting kepada penghuni'],
                'settings' => ['Settings', 'Atur preferensi properti dan akun pengelola'],
            ],
            'roomsData' => [
                ['number' => '101', 'type' => 'Standard Single', 'floor' => 'Lantai 1', 'price' => 'Rp 1.450.000', 'status' => 'Terisi', 'tenant' => 'Alya Putri', 'tone' => 'occupied'],
                ['number' => '102', 'type' => 'Deluxe Studio', 'floor' => 'Lantai 1', 'price' => 'Rp 2.400.000', 'status' => 'Perbaikan', 'tenant' => 'Perbaikan AC', 'tone' => 'maintenance'],
                ['number' => '201', 'type' => 'Superior Single', 'floor' => 'Lantai 2', 'price' => 'Rp 1.850.000', 'status' => 'Tersedia', 'tenant' => 'Siap huni', 'tone' => 'available'],
                ['number' => '204', 'type' => 'Deluxe Balcony', 'floor' => 'Lantai 2', 'price' => 'Rp 2.750.000', 'status' => 'Terisi', 'tenant' => 'Raka Mahendra', 'tone' => 'occupied'],
                ['number' => '301', 'type' => 'Deluxe Studio', 'floor' => 'Lantai 3', 'price' => 'Rp 2.900.000', 'status' => 'Tersedia', 'tenant' => 'Siap huni', 'tone' => 'available'],
                ['number' => '302', 'type' => 'Superior Single', 'floor' => 'Lantai 3', 'price' => 'Rp 2.050.000', 'status' => 'Terisi', 'tenant' => 'Nadia Clarissa', 'tone' => 'occupied'],
            ],
        ];
    }
}

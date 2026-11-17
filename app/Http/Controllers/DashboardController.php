<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Complaint;
use App\Models\Rental;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function overview(BillingService $billing): View
    {
        $data = $this->sharedData('overview');
        $data['finance'] = Schema::hasTable('invoices') ? $billing->summary() : null;

        return view('dashboard.overview', $data);
    }

    public function tenantOverview(): View
    {
        return view('tenant.overview', [
            'activeMenu' => 'tenant-overview',
            'tenant' => [
                'name' => 'Dimas Arya',
                'email' => 'dimas.arya@email.com',
                'room' => '204',
                'floor' => 'Lt. 2',
                'lease_end' => '31 Des 2026',
            ],
        ]);
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

        // Langsung terbitkan tagihan bulan berjalan untuk penghuni baru.
        app(BillingService::class)->generateForMonth();

        return redirect()->route('overview')->with('status', 'Penghuni dan kontrak sewa berhasil ditambahkan.');
    }

    public function settings(): RedirectResponse
    {
        return redirect()->route('administrator');
    }

    public function administrator(): View
    {
        return view('dashboard.administrator', [
            'activeMenu' => 'administrator',
            'pageTitles' => ['administrator' => ['Administrator', 'Kelola profil administrator dan pengaturan sistem']],
        ]);
    }

    public function users(): View
    {
        return view('dashboard.users', [
            'activeMenu' => 'administrator-users',
            'pageTitles' => ['administrator-users' => ['User Management', 'Kelola akun admin dan penghuni SIMAKOS']],
            'users' => User::latest()->get(),
        ]);
    }

    public function createUser(): View
    {
        return view('dashboard.forms.user', [
            'activeMenu' => 'administrator-users',
            'pageTitles' => ['administrator-users' => ['User Management', 'Tambah akun baru ke dalam SIMAKOS']],
        ]);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'in:admin,tenant'],
        ]);

        User::create($validated);

        return redirect()->route('administrator.users')->with('status', 'User berhasil ditambahkan.');
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
}

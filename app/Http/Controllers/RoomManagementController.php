<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomType;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class RoomManagementController extends Controller
{
    public function rooms(): View
    {
        $rooms = Room::with(['roomType', 'rentals' => fn ($q) => $q->where('status', 'active')->with('user')])
            ->orderBy('room_number')->get();

        return view('dashboard.rooms', [
            'activeMenu' => 'rooms',
            'pageTitles' => ['rooms' => ['Room Management', 'Kelola kamar dan status hunian']],
            'rooms' => $rooms,
            'roomsData' => $rooms->map(fn (Room $room) => [
                'id' => $room->id,
                'number' => $room->room_number,
                'type' => $room->roomType?->name ?? 'Belum ditentukan',
                'price' => 'Rp '.number_format((float) $room->price, 0, ',', '.'),
                'tone' => $room->status,
                'status' => ['available' => 'Tersedia', 'occupied' => 'Terisi', 'maintenance' => 'Perbaikan'][$room->status] ?? $room->status,
                'tenant' => $room->rentals->first()?->user?->name ?? 'Belum ada penghuni',
                'floor' => 'Kamar '.$room->room_number,
            ]),
            'roomCounts' => [
                'all' => $rooms->count(),
                'available' => $rooms->where('status', 'available')->count(),
                'occupied' => $rooms->where('status', 'occupied')->count(),
                'maintenance' => $rooms->where('status', 'maintenance')->count(),
            ],
        ]);
    }

    public function createRoom(): View
    {
        return view('dashboard.forms.room', [
            'activeMenu' => 'rooms',
            'pageTitles' => ['rooms' => ['Tambah Kamar', 'Masukkan data kamar baru']],
            'roomTypes' => RoomType::orderBy('name')->get(),
        ]);
    }

    public function storeRoom(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'room_number' => ['required', 'string', 'max:10', 'unique:rooms,room_number'],
            'room_type_id' => ['nullable', 'exists:room_types,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['available', 'maintenance'])],
        ]);
        Room::create($data);
        return redirect()->route('rooms')->with('status', 'Kamar berhasil ditambahkan.');
    }

    public function editRoom(Room $room): View
    {
        return view('forms.room-edit', [
            'activeMenu' => 'rooms',
            'pageTitles' => ['rooms' => ['Edit Kamar', 'Perbarui informasi kamar']],
            'room' => $room,
            'roomTypes' => RoomType::orderBy('name')->get(),
        ]);
    }

    public function updateRoom(Request $request, Room $room): RedirectResponse
    {
        $data = $request->validate([
            'room_number' => ['required', 'string', 'max:10', Rule::unique('rooms', 'room_number')->ignore($room->id)],
            'room_type_id' => ['nullable', 'exists:room_types,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['available', 'maintenance'])],
        ]);

        if ($room->rentals()->where('status', 'active')->exists()) {
            $data['status'] = 'occupied';
        }
        $room->update($data);
        return redirect()->route('rooms')->with('status', 'Data kamar berhasil diperbarui.');
    }

    public function destroyRoom(Room $room): RedirectResponse
    {
        if ($room->rentals()->exists()) {
            return back()->withErrors(['room' => 'Kamar tidak dapat dihapus karena memiliki riwayat penyewaan.']);
        }
        $room->delete();
        return redirect()->route('rooms')->with('status', 'Kamar berhasil dihapus.');
    }

    public function tenants(): View
    {
        $tenants = User::where('role', 'tenant')->with(['rentals' => fn ($q) => $q->with('room')->latest()])->latest()->get();
        return view('tenants', [
            'activeMenu' => 'tenants',
            'pageTitles' => ['tenants' => ['Tenant Management', 'Kelola data penghuni dan riwayat sewa']],
            'tenants' => $tenants,
            'availableRooms' => Room::where('status', 'available')->orderBy('room_number')->get(),
        ]);
    }

    public function updateTenant(Request $request, User $tenant): RedirectResponse
    {
        abort_unless($tenant->role === 'tenant', 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($tenant->id)],
        ]);
        $tenant->update($data);
        return redirect()->route('tenants')->with('status', 'Data penghuni berhasil diperbarui.');
    }

    public function destroyTenant(User $tenant): RedirectResponse
    {
        abort_unless($tenant->role === 'tenant', 404);
        if ($tenant->rentals()->exists()) {
            return back()->withErrors(['tenant' => 'Akun tidak dapat dihapus karena memiliki riwayat sewa. Akhiri sewanya dan nonaktifkan akun secara terpisah.']);
        }
        $tenant->delete();
        return redirect()->route('tenants')->with('status', 'Penghuni berhasil dihapus.');
    }

    public function assignRoom(Request $request, User $tenant): RedirectResponse
    {
        abort_unless($tenant->role === 'tenant', 404);
        $data = $request->validate([
            'room_id' => ['required', 'exists:rooms,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
        ]);

        DB::transaction(function () use ($tenant, $data): void {
            if ($tenant->rentals()->where('status', 'active')->exists()) {
                throw ValidationException::withMessages(['tenant' => 'Penghuni masih memiliki penyewaan aktif. Akhiri sewa sebelumnya terlebih dahulu.']);
            }
            $room = Room::whereKey($data['room_id'])->lockForUpdate()->firstOrFail();
            if ($room->status !== 'available' || $room->rentals()->where('status', 'active')->exists()) {
                throw ValidationException::withMessages(['room_id' => 'Kamar tidak tersedia untuk ditempati.']);
            }
            Rental::create([
                'user_id' => $tenant->id,
                'room_id' => $room->id,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'status' => 'active',
            ]);
            $room->update(['status' => 'occupied']);
        });
        return redirect()->route('tenants')->with('status', 'Penghuni berhasil ditempatkan ke kamar.');
    }

    public function completeRental(Rental $rental): RedirectResponse
    {
        DB::transaction(function () use ($rental): void {
            $rental = Rental::whereKey($rental->id)->lockForUpdate()->firstOrFail();
            if ($rental->status !== 'active') {
                throw ValidationException::withMessages(['rental' => 'Riwayat sewa ini tidak berstatus aktif.']);
            }
            $rental->update(['status' => 'completed', 'end_date' => $rental->end_date ?? now()->toDateString()]);
            $room = Room::whereKey($rental->room_id)->lockForUpdate()->first();
            if ($room && $room->status === 'occupied' && ! $room->rentals()->where('status', 'active')->exists()) {
                $room->update(['status' => 'available']);
            }
        });
        return redirect()->route('tenants')->with('status', 'Masa sewa diakhiri dan riwayat tetap tersimpan.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * UC-02 Kelola Master Kamar (FR-02): CRUD unit kamar.
 * Status "occupied" hanya diatur otomatis oleh proses check-in/check-out (BR-02).
 */
class RoomController extends Controller
{
    public function index(): View
    {
        $rooms = Room::with(['roomType', 'rentals' => fn ($q) => $q->where('status', 'active')->with('user')])
            ->withCount('rentals')
            ->orderBy('room_number')
            ->get();

        return view('dashboard.rooms', [
            ...$this->sharedData('rooms'),
            'rooms' => $rooms,
            'counts' => [
                'all' => $rooms->count(),
                'available' => $rooms->where('status', 'available')->count(),
                'occupied' => $rooms->where('status', 'occupied')->count(),
                'maintenance' => $rooms->where('status', 'maintenance')->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('dashboard.forms.room', [
            ...$this->sharedData('rooms'),
            'room' => new Room(['status' => 'available']),
            'roomTypes' => RoomType::orderBy('name')->get(),
            'hasActiveRental' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Room::create($this->validated($request));

        return redirect()->route('rooms')->with('status', 'Kamar berhasil ditambahkan.');
    }

    public function edit(Room $room): View
    {
        return view('dashboard.forms.room', [
            ...$this->sharedData('rooms'),
            'room' => $room,
            'roomTypes' => RoomType::orderBy('name')->get(),
            'hasActiveRental' => $room->rentals()->where('status', 'active')->exists(),
        ]);
    }

    public function update(Request $request, Room $room): RedirectResponse
    {
        $hasActiveRental = $room->rentals()->where('status', 'active')->exists();
        $validated = $this->validated($request, $room, $hasActiveRental);

        // Kamar yang sedang disewa tetap terkunci Occupied sampai check-out.
        if ($hasActiveRental) {
            $validated['status'] = 'occupied';
        }

        $room->update($validated);

        return redirect()->route('rooms')->with('status', "Kamar {$room->room_number} berhasil diperbarui.");
    }

    public function destroy(Room $room): RedirectResponse
    {
        if ($room->rentals()->exists()) {
            return back()->withErrors([
                'room' => "Kamar {$room->room_number} tidak dapat dihapus karena memiliki riwayat penyewaan. Ubah statusnya menjadi Perbaikan bila kamar tidak lagi disewakan.",
            ]);
        }

        $room->delete();

        return redirect()->route('rooms')->with('status', "Kamar {$room->room_number} berhasil dihapus.");
    }

    private function validated(Request $request, ?Room $room = null, bool $hasActiveRental = false): array
    {
        return $request->validate([
            'room_number' => ['required', 'string', 'max:10', Rule::unique('rooms', 'room_number')->ignore($room?->id)],
            'room_type_id' => ['nullable', 'exists:room_types,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'status' => [$hasActiveRental ? 'nullable' : 'required', Rule::in(['available', 'maintenance'])],
        ], [
            'room_number.unique' => 'Nomor kamar sudah terdaftar.',
            'status.in' => 'Status Terisi diatur otomatis saat check-in penghuni.',
        ]);
    }
}

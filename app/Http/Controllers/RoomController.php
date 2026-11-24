<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomPhoto;
use App\Models\RoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * UC-02 Kelola Master Kamar (FR-02): CRUD unit kamar beserta spesifikasi, fasilitas, dan galeri foto.
 * Status "occupied" hanya diatur otomatis oleh proses check-in/check-out (BR-02).
 */
class RoomController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        // Tautan lama ?room=ID (versi popup) diarahkan ke halaman detail.
        if ($request->integer('room')) {
            return redirect()->route('rooms.show', $request->integer('room'));
        }

        $allRooms = Room::with(['roomType', 'photos', 'activeRental.user'])
            ->withCount('rentals')
            ->orderBy('room_number')
            ->get();

        $status = (string) $request->query('status', 'all');
        $validStatuses = ['available', 'occupied', 'maintenance'];
        if (! in_array($status, $validStatuses, true)) {
            $status = 'all';
        }

        $rooms = $status === 'all'
            ? $allRooms
            : $allRooms->where('status', $status)->values();

        return view('dashboard.rooms', [
            ...$this->sharedData('rooms'),
            'rooms' => $rooms,
            'status' => $status,
            'counts' => [
                'all' => $allRooms->count(),
                'available' => $allRooms->where('status', 'available')->count(),
                'occupied' => $allRooms->where('status', 'occupied')->count(),
                'maintenance' => $allRooms->where('status', 'maintenance')->count(),
            ],
        ]);
    }

    /** Halaman detail kamar: spesifikasi, fasilitas, foto, penyewa saat ini, dan riwayat penyewaan. */
    public function show(Room $room): View
    {
        $room->load([
            'roomType',
            'photos',
            'activeRental.user',
            'rentals' => fn ($q) => $q->with('user')->latest('start_date')->latest('id'),
        ]);

        $rental = $room->activeRental;
        $rentalIds = $room->rentals->pluck('id');

        return view('dashboard.room-show', [
            ...$this->sharedData('rooms'),
            'room' => $room,
            'rental' => $rental,
            'paidThrough' => $rental?->paidThrough(),
            'outstanding' => $rental
                ? Invoice::where('rental_id', $rental->id)->outstanding()->orderBy('billing_month')->get()
                : collect(),
            'stats' => [
                'times_rented' => $room->rentals->count(),
                'income' => (float) Payment::whereIn('rental_id', $rentalIds)->where('status', 'approved')->sum('amount'),
                'complaints' => Complaint::whereIn('rental_id', $rentalIds)->count(),
                'open_complaints' => Complaint::whereIn('rental_id', $rentalIds)->where('status', '!=', 'resolved')->count(),
            ],
            'complaints' => Complaint::whereIn('rental_id', $rentalIds)->with('rental.user')->latest()->limit(5)->get(),
        ]);
    }

    public function create(): View
    {
        return view('dashboard.forms.room', $this->formData(new Room(['status' => 'available']), false));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $room = DB::transaction(function () use ($request, $validated): Room {
            $room = Room::create($this->attributes($validated));
            $this->storePhotos($request, $room);

            return $room;
        });

        return redirect()->route('rooms.show', $room)->with('status', "Kamar {$room->room_number} berhasil ditambahkan.");
    }

    public function edit(Room $room): View
    {
        $room->load('photos', 'roomType');

        return view('dashboard.forms.room', $this->formData($room, $room->rentals()->where('status', 'active')->exists()));
    }

    public function update(Request $request, Room $room): RedirectResponse
    {
        $hasActiveRental = $room->rentals()->where('status', 'active')->exists();
        $validated = $this->validated($request, $room, $hasActiveRental);
        $attributes = $this->attributes($validated);

        // Kamar yang sedang disewa tetap terkunci Occupied sampai check-out.
        if ($hasActiveRental) {
            $attributes['status'] = 'occupied';
        }

        DB::transaction(function () use ($request, $room, $attributes, $validated): void {
            $room->update($attributes);

            // Hapus foto yang dicentang.
            $room->photos()->whereIn('id', $validated['delete_photos'] ?? [])->get()->each(function (RoomPhoto $photo): void {
                Storage::disk('public')->delete($photo->path);
                $photo->delete();
            });

            $this->storePhotos($request, $room);

            // Foto sampul = urutan pertama.
            if (! empty($validated['cover_photo']) && $room->photos()->whereKey($validated['cover_photo'])->exists()) {
                $room->photos()->get()
                    ->sortBy(fn (RoomPhoto $p) => [$p->id === (int) $validated['cover_photo'] ? 0 : 1, $p->sort_order, $p->id])
                    ->values()
                    ->each(fn (RoomPhoto $p, int $i) => $p->update(['sort_order' => $i]));
            }
        });

        return redirect()->route('rooms.show', $room)->with('status', "Kamar {$room->room_number} berhasil diperbarui.");
    }

    public function destroy(Room $room): RedirectResponse
    {
        if ($room->rentals()->exists()) {
            return back()->withErrors([
                'room' => "Kamar {$room->room_number} tidak dapat dihapus karena memiliki riwayat penyewaan. Ubah statusnya menjadi Perbaikan bila kamar tidak lagi disewakan.",
            ]);
        }

        $room->photos->each(fn (RoomPhoto $photo) => Storage::disk('public')->delete($photo->path));
        $room->delete();

        return redirect()->route('rooms')->with('status', "Kamar {$room->room_number} berhasil dihapus.");
    }

    /* ------------------------------------------------------------------ */

    private function formData(Room $room, bool $hasActiveRental): array
    {
        return [
            ...$this->sharedData('rooms'),
            'room' => $room,
            'roomTypes' => RoomType::orderBy('name')->get(),
            'hasActiveRental' => $hasActiveRental,
            'facilities' => config('simakos.facilities'),
            'maxPhotos' => (int) config('simakos.room_photos.max_per_room'),
        ];
    }

    private function validated(Request $request, ?Room $room = null, bool $hasActiveRental = false): array
    {
        $maxKb = (int) config('simakos.room_photos.max_kb');

        $validated = $request->validate([
            'room_number' => ['required', 'string', 'max:10', Rule::unique('rooms', 'room_number')->ignore($room?->id)],
            'room_type_id' => ['nullable', 'exists:room_types,id'],
            'floor' => ['nullable', 'integer', 'min:0', 'max:50'],
            'size_m2' => ['nullable', 'numeric', 'min:1', 'max:999'],
            'price' => ['required', 'numeric', 'min:0'],
            'status' => [$hasActiveRental ? 'nullable' : 'required', Rule::in(['available', 'maintenance'])],
            'description' => ['nullable', 'string', 'max:2000'],
            'facilities' => ['nullable', 'array'],
            'facilities.*' => ['string', Rule::in(array_keys(config('simakos.facilities')))],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:'.$maxKb],
            'delete_photos' => ['nullable', 'array'],
            'delete_photos.*' => ['integer'],
            'cover_photo' => ['nullable', 'integer'],
        ], [
            'room_number.unique' => 'Nomor kamar sudah terdaftar.',
            'status.in' => 'Status Terisi diatur otomatis saat check-in penghuni.',
            'photos.*.mimes' => 'Foto kamar harus berformat JPG, PNG, atau WebP.',
            'photos.*.max' => 'Ukuran setiap foto kamar maksimal '.($maxKb / 1024).' MB.',
        ]);

        $existing = $room ? $room->photos()->whereNotIn('id', $validated['delete_photos'] ?? [])->count() : 0;
        $max = (int) config('simakos.room_photos.max_per_room');
        if ($existing + count($request->file('photos', [])) > $max) {
            throw ValidationException::withMessages(['photos' => "Satu kamar maksimal memiliki {$max} foto."]);
        }

        return $validated;
    }

    private function attributes(array $validated): array
    {
        return [
            'room_number' => $validated['room_number'],
            'room_type_id' => $validated['room_type_id'] ?? null,
            'floor' => $validated['floor'] ?? null,
            'size_m2' => $validated['size_m2'] ?? null,
            'price' => $validated['price'],
            'status' => $validated['status'] ?? 'available',
            'description' => $validated['description'] ?? null,
            'facilities' => array_values($validated['facilities'] ?? []),
        ];
    }

    private function storePhotos(Request $request, Room $room): void
    {
        $order = (int) $room->photos()->max('sort_order');

        foreach ($request->file('photos', []) as $file) {
            $room->photos()->create([
                'path' => $file->store('room-photos', 'public'),
                'sort_order' => ++$order,
            ]);
        }
    }
}

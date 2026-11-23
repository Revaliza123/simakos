<?php

namespace App\Http\Controllers;

use App\Models\RoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** UC-02 Kelola Master Kamar (FR-02): CRUD kategori/tipe kamar (room_types). */
class RoomTypeController extends Controller
{
    public function index(): View
    {
        return view('dashboard.room-types', [
            ...$this->sharedData('room-types'),
            'roomTypes' => RoomType::withCount('rooms')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('dashboard.forms.room-type', [
            ...$this->sharedData('room-types'),
            'roomType' => new RoomType,
            'facilities' => config('simakos.facilities'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        RoomType::create($this->attributes($request));

        return redirect()->route('room-types.index')->with('status', 'Tipe kamar berhasil ditambahkan.');
    }

    public function edit(RoomType $roomType): View
    {
        return view('dashboard.forms.room-type', [
            ...$this->sharedData('room-types'),
            'roomType' => $roomType,
            'facilities' => config('simakos.facilities'),
        ]);
    }

    public function update(Request $request, RoomType $roomType): RedirectResponse
    {
        $roomType->update($this->attributes($request, $roomType));

        return redirect()->route('room-types.index')->with('status', 'Tipe kamar berhasil diperbarui.');
    }

    public function destroy(RoomType $roomType): RedirectResponse
    {
        if ($roomType->rooms()->exists()) {
            return back()->withErrors(['room_type' => "Tipe {$roomType->name} masih dipakai oleh unit kamar sehingga tidak dapat dihapus."]);
        }

        $roomType->delete();

        return redirect()->route('room-types.index')->with('status', 'Tipe kamar berhasil dihapus.');
    }

    private function attributes(Request $request, ?RoomType $roomType = null): array
    {
        $validated = $this->validated($request, $roomType);

        return [...$validated, 'facilities' => array_values($validated['facilities'] ?? [])];
    }

    private function validated(Request $request, ?RoomType $roomType = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('room_types', 'name')->ignore($roomType?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'facilities' => ['nullable', 'array'],
            'facilities.*' => ['string', Rule::in(array_keys(config('simakos.facilities')))],
        ], [
            'name.unique' => 'Nama tipe kamar sudah terdaftar.',
        ]);
    }
}

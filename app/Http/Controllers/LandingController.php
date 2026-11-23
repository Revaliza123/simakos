<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\View\View;

/** Landing page publik: katalog kamar kosong untuk calon penghuni (Guest / Visitor). */
class LandingController extends Controller
{
    public function __invoke(): View
    {
        $available = Room::with('roomType')->where('status', 'available')->orderBy('price')->orderBy('room_number')->get();

        return view('welcome', [
            'rooms' => $available,
            'roomTypes' => $available->groupBy(fn (Room $room) => $room->roomType?->name ?? 'Kamar Standar'),
            'totalRooms' => Room::count(),
            'lowestPrice' => $available->min('price'),
        ]);
    }
}

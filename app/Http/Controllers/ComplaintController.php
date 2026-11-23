<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\Rental;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** UC-07 Tindak Lanjuti Keluhan (FR-08) — sisi Admin. */
class ComplaintController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');

        return view('dashboard.complaints', [
            ...$this->sharedData('complaints'),
            'status' => $status,
            'complaints' => Complaint::with('rental.user', 'rental.room')
                ->when(array_key_exists((string) $status, Complaint::STATUS_LABELS), fn ($q) => $q->where('status', $status))
                ->orderByRaw("case status when 'open' then 0 when 'in_progress' then 1 else 2 end")
                ->latest()
                ->get(),
            'counts' => Complaint::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    /** Admin mencatat keluhan atas nama penghuni (mis. dilaporkan langsung ke penjaga kos). */
    public function create(): View
    {
        return view('dashboard.forms.complaint', [
            ...$this->sharedData('complaints'),
            'rentals' => Rental::with(['user', 'room'])->where('status', 'active')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'rental_id' => ['required', Rule::exists('rentals', 'id')->where('status', 'active')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'photo' => ['nullable', 'mimes:jpg,jpeg,png', 'max:2048'],
        ], self::photoMessages());

        $validated['photo'] = $request->hasFile('photo') ? $request->file('photo')->store('complaint-photos', 'public') : null;

        Complaint::create([...$validated, 'status' => 'open']);

        return redirect()->route('complaints')->with('status', 'Tiket keluhan berhasil dibuat.');
    }

    public function update(Request $request, Complaint $complaint): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(Complaint::STATUS_LABELS))],
            'response' => ['nullable', 'string', 'max:2000'],
        ]);

        $complaint->update([
            'status' => $validated['status'],
            'response' => $validated['response'] ?? $complaint->response,
            'responded_at' => filled($validated['response'] ?? null) || $validated['status'] !== $complaint->status ? now() : $complaint->responded_at,
        ]);

        return back()->with('status', "Tiket {$complaint->ticketNumber()} diperbarui menjadi {$complaint->statusLabel()}.");
    }

    public static function photoMessages(): array
    {
        return [
            'photo.mimes' => 'Format foto harus JPG atau PNG.',
            'photo.max' => 'Ukuran foto maksimal 2 MB.',
        ];
    }
}

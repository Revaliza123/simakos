<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\Room;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function overview(BillingService $billing): View
    {
        $billing->generateForMonth();
        $billing->refreshOverdue();

        $rooms = Room::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $totalRooms = (int) $rooms->sum();
        $occupied = (int) ($rooms['occupied'] ?? 0);

        // Tren pemasukan (pembayaran disetujui) 6 bulan terakhir.
        $months = collect(range(5, 0))->map(fn (int $ago) => now()->startOfMonth()->subMonthsNoOverflow($ago));
        $income = $months->map(fn ($m) => (float) Payment::where('status', 'approved')
            ->whereBetween('payment_date', [$m->toDateString(), $m->copy()->endOfMonth()->toDateString()])
            ->sum('amount'));

        $activities = collect()
            ->merge(Payment::with('rental.user', 'rental.room')->latest('id')->limit(5)->get()->map(fn (Payment $p) => [
                'icon' => 'payments', 'tone' => $p->status === 'approved' ? 'green' : ($p->status === 'rejected' ? 'orange' : 'blue'),
                'title' => ['pending' => 'Bukti bayar menunggu validasi', 'approved' => 'Pembayaran disetujui', 'rejected' => 'Pembayaran ditolak'][$p->status] ?? 'Pembayaran',
                'subtitle' => ($p->rental?->user?->name ?? '-').' · Kamar '.($p->rental?->room?->room_number ?? '-'),
                'time' => $p->created_at, 'value' => rupiah($p->amount),
            ]))
            ->merge(Complaint::with('rental.room')->latest('id')->limit(5)->get()->map(fn (Complaint $c) => [
                'icon' => 'report_problem', 'tone' => 'orange',
                'title' => 'Keluhan: '.$c->title,
                'subtitle' => $c->ticketNumber().' · Kamar '.($c->rental?->room?->room_number ?? '-'),
                'time' => $c->created_at, 'value' => $c->statusLabel(),
            ]))
            ->merge(Rental::with('user', 'room')->latest('id')->limit(5)->get()->map(fn (Rental $r) => [
                'icon' => $r->status === 'active' ? 'person_add' : 'logout', 'tone' => 'blue',
                'title' => $r->status === 'active' ? 'Check-in penghuni' : 'Check-out penghuni',
                'subtitle' => ($r->user?->name ?? '-').' · Kamar '.($r->room?->room_number ?? '-'),
                'time' => $r->updated_at, 'value' => $r->status === 'active' ? 'Aktif' : 'Selesai',
            ]))
            ->sortByDesc('time')
            ->take(6)
            ->values();

        return view('dashboard.overview', [
            ...$this->sharedData('overview'),
            'finance' => $billing->summary(),
            'stats' => [
                'tenants' => Rental::where('status', 'active')->distinct('user_id')->count('user_id'),
                'rooms' => $totalRooms,
                'occupied' => $occupied,
                'available' => (int) ($rooms['available'] ?? 0),
                'maintenance' => (int) ($rooms['maintenance'] ?? 0),
                'occupancy' => $totalRooms > 0 ? (int) round($occupied / $totalRooms * 100) : 0,
                'openComplaints' => Complaint::where('status', '!=', 'resolved')->count(),
            ],
            'trend' => [
                'labels' => $months->map(fn ($m) => $m->translatedFormat('M'))->all(),
                'values' => $income->all(),
                'max' => max(1, (float) $income->max()),
            ],
            'activities' => $activities,
        ]);
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
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($request->user()->id)],
        ]);

        $request->user()->update($validated);

        return redirect()->route('settings')->with('status', 'Pengaturan berhasil disimpan.');
    }
}

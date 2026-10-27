<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function overview(): View
    {
        return view('dashboard.overview', $this->sharedData('overview'));
    }

    public function rooms(): View
    {
        return view('dashboard.rooms', $this->sharedData('rooms'));
    }

    public function billing(): View
    {
        return view('dashboard.billing', $this->sharedData('billing'));
    }

    public function complaints(): View
    {
        return view('dashboard.complaints', $this->sharedData('complaints'));
    }

    public function announcements(): View
    {
        return view('dashboard.announcements', $this->sharedData('announcements'));
    }

    public function settings(): View
    {
        return view('dashboard.settings', $this->sharedData('settings'));
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

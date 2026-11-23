<?php

namespace App\Http\Controllers;

abstract class Controller
{
    protected function sharedData(string $activeMenu): array
    {
        return [
            'activeMenu' => $activeMenu,
            'pageTitles' => [
                'overview' => ['Overview', 'Ringkasan operasional dan keuangan kost'],
                'rooms' => ['Room Management', 'Pantau ketersediaan dan kondisi setiap kamar'],
                'room-types' => ['Tipe Kamar', 'Kelola kategori kamar beserta deskripsi fasilitasnya'],
                'rentals' => ['Rental & Check-out', 'Kelola kontrak sewa, check-in, check-out, dan riwayat hunian'],
                'billing' => ['Payment & Billing', 'Kelola tagihan, pembayaran, dan rekonsiliasi sewa'],
                'expenses' => ['Expenses & Reports', 'Rekap pengeluaran operasional dan laporan pemasukan'],
                'complaints' => ['Complaints & Tickets', 'Tindak lanjuti laporan dan permintaan penghuni'],
                'announcements' => ['Announcements', 'Sampaikan informasi penting kepada penghuni'],
                'settings' => ['Settings', 'Atur preferensi properti dan akun pengelola'],
            ],
        ];
    }
}

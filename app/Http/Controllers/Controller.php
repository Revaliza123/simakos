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
                'billing' => ['Payment & Billing', 'Kelola tagihan, pembayaran, dan rekonsiliasi sewa'],
                'expenses' => ['Expenses & Reports', 'Rekap pengeluaran operasional dan laporan pemasukan'],
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

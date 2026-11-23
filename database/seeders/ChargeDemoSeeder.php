<?php

namespace Database\Seeders;

use App\Models\Charge;
use App\Models\Room;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Database\Seeder;

/**
 * Contoh tagihan lain: iuran kebersihan bulanan (per penghuni) dan gas dapur bersama (dibagi rata).
 * Jalankan: php artisan db:seed --class=ChargeDemoSeeder
 */
class ChargeDemoSeeder extends Seeder
{
    public function run(BillingService $billing): void
    {
        $adminId = User::where('role', 'admin')->value('id');
        $month = now()->startOfMonth();

        Charge::firstOrCreate(['title' => 'Iuran kebersihan & sampah'], [
            'category' => 'kebersihan',
            'description' => 'Iuran bulanan petugas kebersihan dan pengangkutan sampah.',
            'amount' => 25000,
            'amount_mode' => 'per_tenant',
            'target' => 'all',
            'is_recurring' => true,
            'start_month' => $month->toDateString(),
            'due_day' => 10,
            'created_by' => $adminId,
        ]);

        Charge::firstOrCreate(['title' => 'Isi ulang gas dapur bersama'], [
            'category' => 'gas',
            'description' => 'Tabung gas 12 kg untuk dapur bersama, dibagi rata ke semua penghuni.',
            'amount' => 240000,
            'amount_mode' => 'split',
            'target' => 'all',
            'is_recurring' => false,
            'start_month' => $month->toDateString(),
            'due_day' => 20,
            'created_by' => $adminId,
        ]);

        $roomId = Room::where('room_number', '201')->value('id');
        if ($roomId) {
            Charge::firstOrCreate(['title' => 'Ganti kunci pintu kamar'], [
                'category' => 'kerusakan',
                'description' => 'Penggantian kunci karena kunci lama hilang.',
                'amount' => 85000,
                'amount_mode' => 'per_tenant',
                'target' => 'rooms',
                'room_ids' => [$roomId],
                'is_recurring' => false,
                'start_month' => $month->toDateString(),
                'due_day' => 25,
                'created_by' => $adminId,
            ]);
        }

        $billing->generateChargesForMonth($month);
    }
}

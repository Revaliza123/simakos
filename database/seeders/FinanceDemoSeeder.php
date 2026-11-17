<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Rental;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Database\Seeder;

/**
 * Data contoh modul keuangan: kamar, penghuni, tagihan 6 bulan terakhir,
 * pembayaran (lunas / terlambat / menunggu validasi) dan pengeluaran operasional.
 */
class FinanceDemoSeeder extends Seeder
{
    public function run(BillingService $billing): void
    {
        User::firstOrCreate(['email' => 'admin@simakos.id'], ['name' => 'Admin SIMAKOS', 'password' => 'password', 'role' => 'admin']);

        $types = collect(['Standard Single' => 1450000, 'Superior Single' => 1850000, 'Deluxe Studio' => 2400000])
            ->map(fn ($price, $name) => [RoomType::firstOrCreate(['name' => $name]), $price]);

        $start = now()->startOfMonth()->subMonths(5);

        $tenants = [
            ['101', 'Standard Single', 'Alya Putri', 'alya@simakos.test', 'good'],
            ['102', 'Superior Single', 'Raka Mahendra', 'raka@simakos.test', 'good'],
            ['201', 'Deluxe Studio', 'Nadia Clarissa', 'nadia@simakos.test', 'late'],
            ['202', 'Standard Single', 'Dimas Arya', 'dimas@simakos.test', 'arrears'],
            ['203', 'Superior Single', 'Salsa Ramadhani', 'salsa@simakos.test', 'pending'],
        ];

        foreach ($tenants as [$number, $type, $name, $email, $profile]) {
            [$roomType, $price] = $types[$type];
            $room = Room::firstOrCreate(['room_number' => $number], ['room_type_id' => $roomType->id, 'price' => $price, 'status' => 'occupied']);
            $user = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => 'password', 'role' => 'tenant']);
            Rental::firstOrCreate(['user_id' => $user->id, 'room_id' => $room->id], ['start_date' => $start, 'status' => 'active']);
        }

        foreach (['301' => 'Deluxe Studio', '302' => 'Standard Single'] as $number => $type) {
            [$roomType, $price] = $types[$type];
            Room::firstOrCreate(['room_number' => $number], ['room_type_id' => $roomType->id, 'price' => $price, 'status' => 'available']);
        }

        // Tagihan otomatis untuk 6 bulan terakhir.
        for ($m = $start->copy(); $m->lte(now()); $m->addMonthNoOverflow()) {
            $billing->generateForMonth($m);
        }

        foreach (Rental::with('user')->get() as $rental) {
            $profile = collect($tenants)->firstWhere(3, $rental->user->email)[4] ?? 'good';
            $invoices = Invoice::where('rental_id', $rental->id)->where('status', 'unpaid')->orderBy('billing_month')->get();

            foreach ($invoices as $index => $invoice) {
                $isCurrent = $invoice->billing_month->isSameMonth(now());
                $isLast = $index === $invoices->count() - 1;

                // Penghuni "arrears" menunggak 2 bulan terakhir, "pending" menunggu validasi bulan ini.
                if ($profile === 'arrears' && $index >= $invoices->count() - 2) {
                    continue;
                }
                if ($isCurrent && $invoice->due_date->isFuture() && $profile !== 'pending') {
                    continue;
                }

                $payDate = match ($profile) {
                    'late' => $invoice->due_date->copy()->addDays(6 + $index),
                    default => $invoice->due_date->copy()->subDays(3 + $index % 3),
                };
                $payDate = $payDate->gt(now()) ? now()->startOfDay() : $payDate;

                $payment = $billing->recordPayment([
                    'rental_id' => $rental->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $invoice->amount + ($profile === 'late' ? $invoice->calculateLateFee($payDate) : 0),
                    'total_months_paid' => 1,
                    'payment_date' => $payDate->toDateString(),
                ]);

                if (! ($profile === 'pending' && $isLast)) {
                    $billing->approvePayment($payment, 'Diverifikasi admin');
                }
            }
        }

        // Pengeluaran operasional bulanan.
        for ($m = $start->copy(); $m->lte(now()); $m->addMonthNoOverflow()) {
            $rows = [
                ['Tagihan listrik PLN', 'listrik', 1850000 + $m->month * 25000, 5],
                ['Tagihan air PDAM', 'air', 420000, 6],
                ['Internet IndiHome', 'internet', 550000, 3],
                ['Gaji petugas kebersihan', 'gaji', 1500000, 1],
                ['Perlengkapan kebersihan', 'kebersihan', 175000, 12],
            ];
            if ($m->month % 2 === 0) {
                $rows[] = ['Servis AC & perbaikan', 'perbaikan', 650000, 18];
            }

            foreach ($rows as [$title, $category, $amount, $day]) {
                $date = $m->copy()->day($day);
                if ($date->isFuture()) {
                    continue;
                }
                Expense::firstOrCreate(['title' => $title, 'expense_date' => $date->toDateString()], ['category' => $category, 'amount' => $amount]);
            }
        }

        $billing->refreshOverdue();
    }
}

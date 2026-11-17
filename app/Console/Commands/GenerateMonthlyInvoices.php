<?php

namespace App\Console\Commands;

use App\Services\BillingService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateMonthlyInvoices extends Command
{
    protected $signature = 'billing:generate {--month= : Bulan tagihan format YYYY-MM (default: bulan ini)}';

    protected $description = 'Generate tagihan sewa bulanan untuk semua kontrak aktif dan perbarui denda keterlambatan';

    public function handle(BillingService $billing): int
    {
        $month = $this->option('month')
            ? Carbon::createFromFormat('!Y-m', $this->option('month'))->startOfMonth()
            : now()->startOfMonth();

        $created = $billing->generateForMonth($month);
        $overdue = $billing->refreshOverdue();

        $this->info("Tagihan {$month->translatedFormat('F Y')}: {$created} tagihan baru dibuat.");
        $this->info("{$overdue} tagihan terlambat diperbarui dendanya.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\BillingService;
use Illuminate\Console\Command;

class RefreshOverdueInvoices extends Command
{
    protected $signature = 'billing:refresh-overdue';

    protected $description = 'Tandai tagihan yang lewat jatuh tempo sebagai terlambat dan hitung ulang dendanya';

    public function handle(BillingService $billing): int
    {
        $this->info($billing->refreshOverdue().' tagihan terlambat diperbarui.');

        return self::SUCCESS;
    }
}

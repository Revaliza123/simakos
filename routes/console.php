<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Tagihan sewa otomatis: dibuat setiap tanggal 1, denda dihitung ulang setiap hari.
// Jalankan `php artisan schedule:work` (development) atau cron `schedule:run` (server).
Schedule::command('billing:generate')->monthlyOn(1, '00:05');
Schedule::command('billing:refresh-overdue')->dailyAt('00:10');

<?php

return [
    /*
    | Tanggal jatuh tempo tagihan setiap bulan (1-28).
    */
    'due_day' => (int) env('BILLING_DUE_DAY', 10),

    /*
    | Denda keterlambatan per hari (Rupiah), dihitung sejak lewat jatuh tempo.
    */
    'late_fee_per_day' => (float) env('BILLING_LATE_FEE_PER_DAY', 10000),

    /*
    | Batas maksimal denda, dalam persen dari nominal sewa bulanan.
    */
    'late_fee_max_percent' => (float) env('BILLING_LATE_FEE_MAX_PERCENT', 20),

    /*
    | Kategori pengeluaran operasional.
    */
    'expense_categories' => [
        'listrik' => 'Listrik',
        'air' => 'Air',
        'internet' => 'Internet',
        'kebersihan' => 'Kebersihan',
        'perbaikan' => 'Perbaikan & Perawatan',
        'gaji' => 'Gaji Karyawan',
        'lainnya' => 'Lainnya',
    ],
];

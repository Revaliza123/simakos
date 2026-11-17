<?php

if (! function_exists('rupiah')) {
    /** Format angka ke Rupiah, contoh: 1500000 -> "Rp 1.500.000". */
    function rupiah(float|int|string|null $value): string
    {
        return 'Rp '.number_format((float) $value, 0, ',', '.');
    }
}

if (! function_exists('rupiah_short')) {
    /** Format ringkas untuk kartu statistik, contoh: 68400000 -> "Rp 68,4 jt". */
    function rupiah_short(float|int|string|null $value): string
    {
        $value = (float) $value;

        return match (true) {
            abs($value) >= 1_000_000_000 => 'Rp '.number_format($value / 1_000_000_000, 1, ',', '.').' M',
            abs($value) >= 1_000_000 => 'Rp '.number_format($value / 1_000_000, 1, ',', '.').' jt',
            abs($value) >= 1_000 => 'Rp '.number_format($value / 1_000, 0, ',', '.').' rb',
            default => rupiah($value),
        };
    }
}

<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Rental extends Model
{
    use HasFactory;

    public const STATUS_LABELS = [
        'active' => 'Aktif',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
    ];

    /** Pilihan siklus bayar sewa (bulan per tagihan). */
    public const BILLING_CYCLES = [
        1 => 'Bulanan',
        3 => 'Per 3 bulan',
        6 => 'Per 6 bulan',
        12 => 'Per 12 bulan (tahunan)',
    ];

    protected $fillable = ['user_id', 'room_id', 'start_date', 'end_date', 'billing_cycle', 'status'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'billing_cycle' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function billingCycleLabel(): string
    {
        return self::BILLING_CYCLES[(int) $this->billing_cycle] ?? 'Per '.$this->billing_cycle.' bulan';
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    /** Contoh: "01 Sep 2026 – 31 Agu 2027" atau "01 Sep 2026 – Tidak ditentukan". */
    public function periodLabel(): string
    {
        return $this->start_date->translatedFormat('d M Y').' – '.$this->endLabel();
    }

    public function endLabel(): string
    {
        if ($this->end_date) {
            return $this->end_date->translatedFormat('d M Y');
        }

        return 'Tidak ditentukan';
    }

    /** Sisa hari kontrak aktif; null bila kontrak bulanan tanpa tanggal akhir, negatif bila sudah lewat. */
    public function remainingDays(?CarbonInterface $at = null): ?int
    {
        if (! $this->end_date || $this->status !== 'active') {
            return null;
        }

        return (int) Carbon::parse($at ?? now())->startOfDay()->diffInDays($this->end_date, false);
    }

    public function remainingLabel(): ?string
    {
        $days = $this->remainingDays();

        return match (true) {
            $days === null => null,
            $days < 0 => 'Lewat '.abs($days).' hari dari tanggal akhir',
            $days === 0 => 'Berakhir hari ini',
            default => 'Sisa '.$days.' hari',
        };
    }

    /** Lama menyewa, contoh "1 thn 2 bln" atau "3 bln 12 hr". */
    public function durationLabel(): string
    {
        $end = $this->status === 'active'
            ? ($this->end_date && $this->end_date->isPast() ? $this->end_date : now())
            : ($this->end_date ?? $this->updated_at ?? now());

        $diff = $this->start_date->copy()->startOfDay()->diff(Carbon::parse($end)->startOfDay());
        $parts = array_filter([
            $diff->y ? $diff->y.' thn' : null,
            $diff->m ? $diff->m.' bln' : null,
            ! $diff->y && $diff->d ? $diff->d.' hr' : null,
        ]);

        return $parts ? implode(' ', $parts) : '0 hr';
    }

    /** Bulan terakhir yang sudah lunas (dari tabel invoices). */
    public function paidThrough(): ?CarbonInterface
    {
        $last = $this->invoices()->rent()->where('status', 'paid')->orderByDesc('billing_month')->first();

        return $last?->coversUntil()->endOfMonth();
    }
}

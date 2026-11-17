<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Invoice extends Model
{
    use HasFactory;

    public const STATUS_LABELS = [
        'unpaid' => 'Belum Dibayar',
        'pending' => 'Menunggu Validasi',
        'paid' => 'Lunas',
        'overdue' => 'Terlambat',
    ];

    protected $fillable = ['invoice_number', 'rental_id', 'billing_month', 'due_date', 'amount', 'late_fee', 'status', 'paid_at'];

    protected function casts(): array
    {
        return [
            'billing_month' => 'date',
            'due_date' => 'date',
            'paid_at' => 'date',
            'amount' => 'decimal:2',
            'late_fee' => 'decimal:2',
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Tagihan yang belum lunas (termasuk yang menunggu validasi pembayaran). */
    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', ['unpaid', 'pending', 'overdue']);
    }

    public function scopeForMonth(Builder $query, CarbonInterface $month): Builder
    {
        return $query->whereDate('billing_month', $month->copy()->startOfMonth()->toDateString());
    }

    public function totalDue(): float
    {
        return (float) $this->amount + (float) $this->late_fee;
    }

    public function daysOverdue(?CarbonInterface $at = null): int
    {
        $at = Carbon::parse($at ?? $this->paid_at ?? now())->startOfDay();

        return $at->greaterThan($this->due_date) ? (int) $this->due_date->diffInDays($at) : 0;
    }

    /**
     * Denda = hari terlambat x tarif harian, dibatasi persentase maksimal dari nominal sewa.
     */
    public function calculateLateFee(?CarbonInterface $at = null): float
    {
        $days = $this->daysOverdue($at);

        if ($days === 0) {
            return 0.0;
        }

        $fee = $days * (float) config('billing.late_fee_per_day');
        $cap = (float) $this->amount * (float) config('billing.late_fee_max_percent') / 100;

        return round(min($fee, $cap), 2);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            'paid' => 'available',
            'overdue' => 'occupied',
            default => 'maintenance',
        };
    }
}

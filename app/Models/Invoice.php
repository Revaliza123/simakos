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

    public const TYPES = [
        'rent' => 'Sewa kamar',
        'charge' => 'Tagihan lain',
    ];

    protected $fillable = ['invoice_number', 'rental_id', 'type', 'charge_id', 'title', 'billing_month', 'period_months', 'due_date', 'amount', 'late_fee', 'status', 'paid_at'];

    protected function casts(): array
    {
        return [
            'billing_month' => 'date',
            'due_date' => 'date',
            'paid_at' => 'date',
            'amount' => 'decimal:2',
            'late_fee' => 'decimal:2',
            'period_months' => 'integer',
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
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

    public function scopeRent(Builder $query): Builder
    {
        return $query->where('type', 'rent');
    }

    public function scopeCharges(Builder $query): Builder
    {
        return $query->where('type', 'charge');
    }

    public function isRent(): bool
    {
        return $this->type !== 'charge';
    }

    /** Bulan terakhir yang dicakup tagihan ini (tagihan sewa bisa mencakup 3/6/12 bulan). */
    public function coversUntil(): Carbon
    {
        return $this->billing_month->copy()->startOfMonth()->addMonthsNoOverflow(max(1, (int) $this->period_months) - 1);
    }

    /** Contoh: "November 2026" atau "Nov 2026 – Apr 2027" untuk tagihan beberapa bulan. */
    public function periodLabel(): string
    {
        if ((int) $this->period_months <= 1) {
            return $this->billing_month->translatedFormat('F Y');
        }

        return $this->billing_month->translatedFormat('M Y').' – '.$this->coversUntil()->translatedFormat('M Y');
    }

    /** Nama tagihan untuk ditampilkan, mis. "Sewa 6 bulan" atau "Isi ulang gas". */
    public function label(): string
    {
        if (! $this->isRent()) {
            return $this->title ?: 'Tagihan lain';
        }

        return (int) $this->period_months > 1 ? 'Sewa '.$this->period_months.' bulan' : 'Sewa bulanan';
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

        // Denda keterlambatan hanya berlaku untuk sewa kamar, bukan tagihan lain.
        if ($days === 0 || ! $this->isRent()) {
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

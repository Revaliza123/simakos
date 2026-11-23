<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Tagihan lain di luar sewa kamar (gas, iuran kebersihan, listrik tambahan, kerusakan, dll.).
 * Setiap charge menerbitkan invoice bertipe "charge" untuk penghuni yang menjadi target.
 */
class Charge extends Model
{
    public const AMOUNT_MODES = [
        'per_tenant' => 'Nominal sama per penghuni',
        'split' => 'Total dibagi rata',
    ];

    public const TARGETS = [
        'all' => 'Semua penghuni aktif',
        'rooms' => 'Kamar tertentu',
    ];

    protected $fillable = [
        'title', 'category', 'description', 'amount', 'amount_mode', 'target', 'room_ids',
        'is_recurring', 'start_month', 'end_month', 'due_day', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'room_ids' => 'array',
            'is_recurring' => 'boolean',
            'is_active' => 'boolean',
            'start_month' => 'date',
            'end_month' => 'date',
            'due_day' => 'integer',
        ];
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Charge yang berlaku (harus diterbitkan) pada bulan tertentu. */
    public function scopeDueInMonth(Builder $query, Carbon $month): Builder
    {
        $month = $month->copy()->startOfMonth()->toDateString();

        return $query->where('is_active', true)
            ->whereDate('start_month', '<=', $month)
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('is_recurring', false)->whereDate('start_month', $month))
                ->orWhere(fn ($q) => $q->where('is_recurring', true)
                    ->where(fn ($q) => $q->whereNull('end_month')->orWhereDate('end_month', '>=', $month))));
    }

    public function categoryLabel(): string
    {
        return config('billing.charge_categories')[$this->category] ?? ucfirst($this->category);
    }

    public function scheduleLabel(): string
    {
        if (! $this->is_recurring) {
            return 'Sekali · '.$this->start_month->translatedFormat('F Y');
        }

        return 'Tiap bulan · mulai '.$this->start_month->translatedFormat('M Y')
            .($this->end_month ? ' s.d. '.$this->end_month->translatedFormat('M Y') : '');
    }

    public function amountLabel(): string
    {
        return $this->amount_mode === 'split'
            ? rupiah($this->amount).' dibagi rata'
            : rupiah($this->amount).' / penghuni';
    }

    public function targetLabel(): string
    {
        if ($this->target === 'all') {
            return self::TARGETS['all'];
        }

        $numbers = Room::whereIn('id', $this->room_ids ?? [])->orderBy('room_number')->pluck('room_number');

        return 'Kamar '.$numbers->join(', ');
    }

    public function statusLabel(): string
    {
        if (! $this->is_active) {
            return 'Dihentikan';
        }

        if ($this->is_recurring && $this->end_month && $this->end_month->lt(now()->startOfMonth())) {
            return 'Selesai';
        }

        return $this->is_recurring ? 'Aktif' : 'Diterbitkan';
    }
}

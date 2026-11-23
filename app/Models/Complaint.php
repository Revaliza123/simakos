<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Complaint extends Model
{
    use HasFactory;

    /** Status berjenjang sesuai FR-08: Menunggu → Sedang Diproses → Selesai. */
    public const STATUS_LABELS = [
        'open' => 'Menunggu',
        'in_progress' => 'Sedang Diproses',
        'resolved' => 'Selesai',
    ];

    protected $fillable = ['rental_id', 'title', 'description', 'photo', 'status', 'response', 'responded_at'];

    protected function casts(): array
    {
        return ['responded_at' => 'datetime'];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    /** Kelas warna badge yang dipakai di CSS (available/occupied/maintenance). */
    public function statusTone(): string
    {
        return match ($this->status) {
            'resolved' => 'available',
            'open' => 'occupied',
            default => 'maintenance',
        };
    }

    public function ticketNumber(): string
    {
        return '#TKT-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }
}

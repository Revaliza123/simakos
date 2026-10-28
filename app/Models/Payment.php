<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = ['rental_id', 'amount', 'total_months_paid', 'payment_date', 'proof_image', 'status'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'total_months_paid' => 'integer', 'payment_date' => 'date'];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }
}

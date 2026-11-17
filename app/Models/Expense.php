<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'category', 'amount', 'expense_date', 'description'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'expense_date' => 'date'];
    }

    public function categoryLabel(): string
    {
        return config('billing.expense_categories')[$this->category] ?? ucfirst((string) $this->category);
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 30)->unique();
            $table->foreignId('rental_id')->constrained('rentals')->cascadeOnDelete();
            $table->date('billing_month');
            $table->date('due_date');
            $table->decimal('amount', 12, 2);
            $table->decimal('late_fee', 12, 2)->default(0);
            $table->enum('status', ['unpaid', 'pending', 'paid', 'overdue'])->default('unpaid');
            $table->date('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['rental_id', 'billing_month']);
            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};

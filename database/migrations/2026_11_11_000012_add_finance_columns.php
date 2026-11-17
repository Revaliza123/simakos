<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('rental_id')->constrained('invoices')->nullOnDelete();
            $table->text('admin_notes')->nullable()->after('status');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->string('category', 30)->default('lainnya')->after('title');
            $table->index('expense_date');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
            $table->dropColumn('admin_notes');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['expense_date']);
            $table->dropColumn('category');
        });
    }
};

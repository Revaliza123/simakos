<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - rentals.billing_cycle: siklus bayar sewa (1/3/6/12 bulan per tagihan).
 * - charges: tagihan lain di luar sewa (gas, iuran kebersihan, dsb.), sekali tagih atau berulang tiap bulan,
 *   nominal per penghuni atau total dibagi rata.
 * - invoices: type (rent/charge), charge_id, title, period_months.
 *   Unique (rental_id, billing_month) dihapus karena satu penghuni bisa punya tagihan sewa + tagihan lain
 *   di bulan yang sama; duplikasi dicegah lewat invoice_number yang unik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->unsignedTinyInteger('billing_cycle')->default(1)->after('end_date');
        });

        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('category', 30);
            $table->text('description')->nullable();
            $table->decimal('amount', 12, 2);
            $table->enum('amount_mode', ['per_tenant', 'split'])->default('per_tenant');
            $table->enum('target', ['all', 'rooms'])->default('all');
            $table->json('room_ids')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->date('start_month');
            $table->date('end_month')->nullable();
            $table->unsignedTinyInteger('due_day')->default(10);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('type', 10)->default('rent')->after('rental_id');
            $table->foreignId('charge_id')->nullable()->after('type')->constrained('charges')->nullOnDelete();
            $table->string('title', 150)->nullable()->after('charge_id');
            $table->unsignedTinyInteger('period_months')->default(1)->after('billing_month');
            // Index biasa untuk FK rental_id sebelum unique komposit dilepas (wajib di MySQL).
            $table->index('rental_id', 'invoices_rental_id_index');
            $table->index(['type', 'billing_month']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['rental_id', 'billing_month']);
        });
    }

    public function down(): void
    {
        DB::table('invoices')->where('type', 'charge')->delete();

        Schema::table('invoices', function (Blueprint $table) {
            $table->unique(['rental_id', 'billing_month']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['type', 'billing_month']);
            $table->dropIndex('invoices_rental_id_index');
            $table->dropConstrainedForeignId('charge_id');
            $table->dropColumn(['type', 'title', 'period_months']);
        });

        Schema::dropIfExists('charges');

        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn('billing_cycle');
        });
    }
};

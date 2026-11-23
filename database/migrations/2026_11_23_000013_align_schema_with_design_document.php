<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menyelaraskan skema dengan ERD & Class Diagram pada dokumen SAD SIMAKOS:
 * - users: phone_number, emergency_contact (atribut Tenant)
 * - complaints: photo (FR-07), response & responded_at (FR-08)
 * - announcements: published_at (FR-09)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_number', 20)->nullable()->after('email');
            $table->string('emergency_contact', 100)->nullable()->after('phone_number');
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->string('photo')->nullable()->after('description');
            $table->text('response')->nullable()->after('status');
            $table->timestamp('responded_at')->nullable()->after('response');
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('created_by');
        });

        // Pengumuman lama dianggap sudah terbit agar tetap tampil di portal penghuni.
        DB::table('announcements')->whereNull('published_at')->update(['published_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('published_at');
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->dropColumn(['photo', 'response', 'responded_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone_number', 'emergency_contact']);
        });
    }
};

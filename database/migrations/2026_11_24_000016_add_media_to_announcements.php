<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lampiran pengumuman: gambar & video yang diunggah, plus tautan video YouTube opsional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->string('video_url', 255)->nullable()->after('content');
        });

        Schema::create('announcement_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['image', 'video']);
            $table->string('path');
            $table->string('original_name', 150)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['announcement_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_media');

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('video_url');
        });
    }
};

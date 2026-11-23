<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Detail kamar untuk Room Management:
 * - room_types.facilities: fasilitas bawaan setiap tipe kamar (kunci dari config simakos.facilities)
 * - rooms: lantai, luas, deskripsi, dan fasilitas tambahan khusus unit
 * - room_photos: galeri foto per kamar (urutan pertama = foto sampul)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_types', function (Blueprint $table) {
            $table->json('facilities')->nullable()->after('description');
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->unsignedTinyInteger('floor')->nullable()->after('room_type_id');
            $table->decimal('size_m2', 5, 1)->nullable()->after('floor');
            $table->text('description')->nullable()->after('status');
            $table->json('facilities')->nullable()->after('description');
        });

        Schema::create('room_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->string('path');
            $table->string('caption', 150)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['room_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_photos');

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn(['floor', 'size_m2', 'description', 'facilities']);
        });

        Schema::table('room_types', function (Blueprint $table) {
            $table->dropColumn('facilities');
        });
    }
};

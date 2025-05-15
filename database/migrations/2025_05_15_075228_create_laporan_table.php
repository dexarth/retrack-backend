<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('laporan', function (Blueprint $table) {
            $table->id();
            $table->string('alamat');
            $table->decimal('alamat_lat', 10, 7);   // Latitude
            $table->decimal('alamat_long', 10, 7);  // Longitude
            $table->text('tujuan');      // Purpose
            // Bukti (evidence)
            $table->string('bukti_audio')->nullable();  // Voice recording file path
            $table->string('bukti_gambar'); // Photo file path
            $table->unsignedBigInteger('mentor_id');
            $table->unsignedBigInteger('mentee_id');
            $table->foreign('mentor_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('mentee_id')->references('id')->on('users')->onDelete('cascade');

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('laporan');
    }
};

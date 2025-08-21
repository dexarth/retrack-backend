<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_monitorings', function (Blueprint $table) {
            $table->id();

            // kategori - individu / kelompok
            $table->enum('kategori', ['individu', 'kelompok']);

            // individu => prospek (user/mentee), kelompok => csi
            $table->foreignId('mentee_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('csi_id')->nullable()->constrained('csi')->restrictOnDelete();

            // Alamat Baru
            $table->text('alamat_baru')->nullable();
            $table->text('huraian_alamat')->nullable();
            $table->decimal('baru_lat', 10, 7)->nullable();
            $table->decimal('baru_long', 10, 7)->nullable();

            // Laporan Pemantauan
            $table->longText('laporan_pemantauan')->nullable();

            // Mentor (user)
            $table->foreignId('mentor_id')->constrained('users')->restrictOnDelete();

            // Gambar (path/filename)
            $table->string('gambar')->nullable();

            $table->timestamps();

            // Optional helpful indexes
            $table->index(['kategori']);
            $table->index(['prospek_id']);
            $table->index(['csi_id']);
            $table->index(['mentor_id']);
        });
    }

    public function down(): void
    {
        Schema::table('staff_monitorings', function (Blueprint $table) {
            $table->dropForeign(['prospek_id']);
            $table->dropForeign(['csi_id']);
            $table->dropForeign(['mentor_id']);
        });
        Schema::dropIfExists('staff_monitorings');
    }
};

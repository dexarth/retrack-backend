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
        Schema::create('mentees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->string('id_prospek')->unique();
            $table->string('daerah')->nullable();
            $table->enum('jantina', ['L', 'P'])->nullable();
            $table->string('no_tel')->nullable();
            $table->string('alamat_rumah')->nullable();
            $table->text('huraian_alamat')->nullable();
            $table->decimal('rumah_lat', 10, 7)->nullable();   // Latitude
            $table->decimal('rumah_long', 10, 7)->nullable();  // Longitude
            $table->date('tarikh_bebas')->nullable();
            $table->foreignId('mentor_id')->constrained('users')->onDelete('restrict');
            $table->enum('kategori_prospek', ['ODB', 'OBB', 'PBL', 'PKW'])->nullable(); // ORANG DIPAROL (ODP), ORANG BEBAS BERLESEN (OBB), PENGHUNI BEBAS LESEN (PBL), PKW (PESALH KEHADIRAN WAJIB)
            $table->enum('jenis_penamatan', ['TAMAT HUKUMAN', 'LANGGAR SYARAT', 'TEKNIKAL'])->nullable();
            $table->string('nama_waris_1')->nullable();
            $table->string('no_tel_waris_1')->nullable();
            $table->string('nama_waris_2')->nullable();
            $table->string('no_tel_waris_2')->nullable();
            
            $table->timestamps();
        });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mentees');
    }
};

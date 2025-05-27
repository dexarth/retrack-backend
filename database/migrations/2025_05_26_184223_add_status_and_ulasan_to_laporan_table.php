<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('laporan', function (Blueprint $table) {
            $table->enum('status', ['BAIK', 'HARUS PANTAU', 'PANTAUAN RAPAT', 'BERISIKO TINGGI', 'PENDING'])->default('PENDING'); // or adjust as needed
            $table->text('ulasan')->nullable();           // review or feedback
        });
    }

    public function down()
    {
        Schema::table('laporan', function (Blueprint $table) {
            $table->dropColumn(['status', 'ulasan']);
        });
    }

};

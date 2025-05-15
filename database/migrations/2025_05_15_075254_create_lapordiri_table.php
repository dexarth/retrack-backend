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
        Schema::create('lapordiri', function (Blueprint $table) {
            $table->id();
            $table->date('tarikh');
            $table->time('masa');
            $table->string('tempat');
            $table->unsignedBigInteger('mentor_id');
            $table->unsignedBigInteger('mentee_id');
            $table->foreign('mentor_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('mentee_id')->references('id')->on('users')->onDelete('cascade');
            // 0 = tidak hadir, 1 = hadir
            $table->boolean('status_kehadiran')->nullable();

            $table->timestamps();
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lapordiri');
    }
};

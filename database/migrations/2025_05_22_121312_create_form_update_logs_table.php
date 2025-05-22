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
        Schema::create('form_update_logs', function (Blueprint $table) {
            $table->id();
            $table->string('form_name');
            $table->string('table_name');
            $table->unsignedBigInteger('record_id');
            $table->json('changes'); // store old & new
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_update_logs');
    }
};

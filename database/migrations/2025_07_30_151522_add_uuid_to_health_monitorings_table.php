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
        Schema::table('health_monitorings', function (Blueprint $table) {
            $table->uuid('uuid')->unique()->after('id');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('health_monitorings', function (Blueprint $table) {
            //
        });
    }
};

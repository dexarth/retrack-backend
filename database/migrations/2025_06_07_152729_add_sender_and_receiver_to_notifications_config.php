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
        Schema::table('notifications_config', function (Blueprint $table) {
            $table->string('sender')->nullable()->after('payload_template');
            $table->string('receiver')->nullable()->after('sender');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications_config', function (Blueprint $table) {
            //
        });
    }
};

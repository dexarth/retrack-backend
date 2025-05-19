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
        Schema::table('allowed_tables', function (Blueprint $table) {
            if (Schema::hasColumn('allowed_tables', 'is_active')) {
                $table->dropColumn('is_active');
            }

            if (!Schema::hasColumn('allowed_tables', 'create')) {
                $table->boolean('create')->default(false);
            }

            if (!Schema::hasColumn('allowed_tables', 'read')) {
                $table->boolean('read')->default(false);
            }

            if (!Schema::hasColumn('allowed_tables', 'update')) {
                $table->boolean('update')->default(false);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('allowed_tables', function (Blueprint $table) {
            if (Schema::hasColumn('allowed_tables', 'create')) {
                $table->dropColumn(['create', 'read', 'update']);
            }

            if (!Schema::hasColumn('allowed_tables', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
        });
    }
};
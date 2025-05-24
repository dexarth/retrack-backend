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
        Schema::create('model_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();      // e.g. 'users', 'mentors'
            $table->string('model_class');        // e.g. 'App\Models\User'
            $table->string('label')->nullable();  // Optional: 'User Management'
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('model_mappings');
    }
};

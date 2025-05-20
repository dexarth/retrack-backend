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
        Schema::create('table_relations', function (Blueprint $table) {
            $table->id();
            $table->string('form_name'); // e.g., "mentor_form"
            $table->string('primary_table'); // e.g., "users"
            $table->string('related_table')->nullable(); // e.g., "mentors"
            $table->string('foreign_key')->nullable(); // e.g., "user_id"
            $table->string('primary_column')->nullable(); // e.g., "id"
            $table->json('field_copy_map')->nullable(); // e.g., {"nama_penuh": "name"}
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_relations');
    }
};

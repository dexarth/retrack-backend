<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('health_questions', function (Blueprint $table) {
            $table->id();
            $table->string('domain');
            $table->text('question_text');
            $table->enum('type', ['likert', 'checkbox', 'text'])->default('likert');
            $table->json('choices')->nullable();
            $table->string('field_key')->nullable();
            $table->tinyInteger('is_active')->default(1);  // 1 = true, 0 = false
            $table->tinyInteger('is_weekly')->default(0); // 1 = true, 0 = false
            $table->unsignedInteger('order')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_questions');
    }
};

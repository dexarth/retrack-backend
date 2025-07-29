<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('health_monitorings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mentee_id')->constrained('users')->onDelete('cascade'); // or use mentees table
            $table->date('date')->default(now());

            // Fields for Likert scores (1–5)
            $table->tinyInteger('mood')->nullable();
            $table->tinyInteger('stress')->nullable();
            $table->tinyInteger('sleep_quality')->nullable();
            $table->tinyInteger('meaningful_activity')->nullable();
            $table->tinyInteger('motivation')->nullable();
            $table->tinyInteger('support_need')->nullable();

            // Substance use (checkbox)
            $table->json('substance_use')->nullable();
            $table->string('substance_use_other')->nullable();

            // Optional craving score
            $table->tinyInteger('craving_score')->nullable();

            // Optional weekly question
            $table->text('weekly_challenge')->nullable();

            // Computed values
            $table->unsignedInteger('total_score')->default(0);
            $table->enum('risk_zone', ['low', 'moderate', 'high'])->default('low');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_monitorings');
    }
};
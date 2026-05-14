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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('course_id')->nullable()->constrained()->onDelete('set null');
            $table->string('name');
            $table->dateTime('deadline');
            $table->tinyInteger('difficulty')->default(3); // 1-5
            $table->decimal('estimated_hours', 4, 1);
            $table->enum('status', ['pending', 'in_progress', 'completed'])->default('pending');

            // Kolom hasil AI
            $table->integer('ai_priority_score')->nullable();
            $table->text('ai_recommendation')->nullable();
            $table->date('ai_suggested_start')->nullable();
            $table->enum('ai_priority_level', ['low', 'medium', 'high', 'urgent'])->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};

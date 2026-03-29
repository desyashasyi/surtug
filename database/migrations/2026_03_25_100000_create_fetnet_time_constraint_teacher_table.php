<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fetnet_time_constraint_teacher', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('fetnet_teacher')->cascadeOnDelete();
            $table->tinyInteger('day');    // 1 = Monday, 2 = Tuesday, ...
            $table->tinyInteger('hour');   // 1-based slot index
            $table->timestamps();

            $table->unique(['teacher_id', 'day', 'hour']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fetnet_time_constraint_teacher');
    }
};

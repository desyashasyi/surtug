<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fetnet_teacher_guest', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('fetnet_teacher')->cascadeOnDelete();
            $table->foreignId('program_id')->constrained('institution_program')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['teacher_id', 'program_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fetnet_teacher_guest');
    }
};

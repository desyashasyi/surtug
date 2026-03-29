<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fetnet_teacher_constraint', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('institution_program')->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('fetnet_teacher')->nullOnDelete();
            $table->string('constraint_type');   // e.g. max_days_per_week
            $table->integer('value');
            $table->timestamps();

            $table->unique(['program_id', 'teacher_id', 'constraint_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fetnet_teacher_constraint');
    }
};

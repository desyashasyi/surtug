<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fetnet_time_constraint_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('fetnet_student')->cascadeOnDelete();
            $table->tinyInteger('day');
            $table->tinyInteger('hour');
            $table->timestamps();

            $table->unique(['student_id', 'day', 'hour']);
        });

        Schema::create('fetnet_student_constraint', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('institution_program')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('fetnet_student')->nullOnDelete();
            $table->string('constraint_type');
            $table->integer('value');
            $table->decimal('weight', 5, 2)->default(100.00);
            $table->foreignId('tag_id')->nullable()->constrained('fetnet_activity_tag')->nullOnDelete();
            $table->foreignId('tag2_id')->nullable()->constrained('fetnet_activity_tag')->nullOnDelete();
            $table->tinyInteger('interval_start')->nullable();
            $table->tinyInteger('interval_end')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fetnet_student_constraint');
        Schema::dropIfExists('fetnet_time_constraint_student');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fetnet_teacher', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('institution_program')->cascadeOnDelete();
            $table->string('code', 10)->nullable();
            $table->string('employee_id', 20)->nullable();
            $table->string('front_title', 15)->nullable();
            $table->string('rear_title', 30)->nullable();
            $table->string('name', 200);
            $table->string('email', 50)->nullable();
            $table->string('phone', 15)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fetnet_teacher');
    }
};

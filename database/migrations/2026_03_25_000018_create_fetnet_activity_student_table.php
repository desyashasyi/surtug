<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fetnet_activity_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('fetnet_activity')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('fetnet_student')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fetnet_activity_student');
    }
};

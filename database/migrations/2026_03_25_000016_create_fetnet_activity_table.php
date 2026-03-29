<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fetnet_activity', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('institution_program')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('fetnet_subject')->cascadeOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained('fetnet_semester')->nullOnDelete();
            $table->foreignId('type_id')->nullable()->constrained('fetnet_activity_type')->nullOnDelete();
            $table->tinyInteger('duration')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fetnet_activity');
    }
};

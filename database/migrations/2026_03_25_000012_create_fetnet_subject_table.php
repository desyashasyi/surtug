<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fetnet_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('institution_program')->cascadeOnDelete();
            $table->foreignId('specialization_id')->nullable()->constrained('fetnet_specialization')->nullOnDelete();
            $table->foreignId('type_id')->nullable()->constrained('fetnet_subject_type')->nullOnDelete();
            $table->string('code', 10);
            $table->string('name', 255);
            $table->tinyInteger('credit')->default(2);
            $table->tinyInteger('semester')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fetnet_subject');
    }
};

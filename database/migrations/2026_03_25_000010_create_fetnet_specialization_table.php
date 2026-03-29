<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fetnet_specialization', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('institution_program')->cascadeOnDelete();
            $table->string('code', 10);
            $table->string('abbrev', 10)->nullable();
            $table->string('name', 100);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fetnet_specialization');
    }
};

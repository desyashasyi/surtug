<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fetnet_activity_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('institution_program')->cascadeOnDelete();
            $table->string('name', 100);
            $table->timestamps();

            $table->unique(['program_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fetnet_activity_tag');
    }
};

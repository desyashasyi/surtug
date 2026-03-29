<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fetnet_space_claim', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained('fetnet_space')->cascadeOnDelete();
            $table->foreignId('program_id')->constrained('institution_program')->cascadeOnDelete();
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->string('note')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['space_id', 'program_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fetnet_space_claim');
    }
};

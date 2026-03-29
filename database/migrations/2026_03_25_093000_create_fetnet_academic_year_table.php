<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fetnet_academic_year', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('fetnet_client')->cascadeOnDelete();
            $table->smallInteger('year_start'); // e.g. 2024 = "2024/2025"
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->unique(['client_id', 'year_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fetnet_academic_year');
    }
};

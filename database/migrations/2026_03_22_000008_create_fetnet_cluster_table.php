<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fetnet_cluster', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->nullable()->constrained('institution_program')->nullOnDelete();
            $table->foreignId('cluster_base_id')->nullable()->constrained('fetnet_cluster_base')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fetnet_cluster');
    }
};

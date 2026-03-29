<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fetnet_sub_activity', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('fetnet_activity')->cascadeOnDelete();
            $table->unsignedTinyInteger('duration')->default(1);
            $table->unsignedTinyInteger('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fetnet_sub_activity');
    }
};

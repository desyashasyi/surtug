<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fetnet_time_constraint_activity', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('fetnet_activity')->cascadeOnDelete();
            $table->tinyInteger('day');
            $table->tinyInteger('hour');
            $table->timestamps();

            $table->unique(['activity_id', 'day', 'hour']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fetnet_time_constraint_activity');
    }
};

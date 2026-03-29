<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fetnet_activity_tag_map', function (Blueprint $table) {
            $table->foreignId('activity_id')->constrained('fetnet_activity')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('fetnet_activity_tag')->cascadeOnDelete();
            $table->primary(['activity_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fetnet_activity_tag_map');
    }
};

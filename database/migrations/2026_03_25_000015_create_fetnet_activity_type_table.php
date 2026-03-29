<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fetnet_activity_type', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->timestamps();
        });

        DB::table('fetnet_activity_type')->insert([
            ['name' => 'Theory',     'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Laboratory', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Studio',     'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('fetnet_activity_type');
    }
};

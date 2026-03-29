<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fetnet_space_type', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->boolean('is_theory')->default(false);
            $table->timestamps();
        });

        // Seed common types (CLS, AUD, SEM = theory)
        DB::table('fetnet_space_type')->insert([
            ['name' => 'Classroom',    'code' => 'CLS', 'is_theory' => true,  'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Laboratory',   'code' => 'LAB', 'is_theory' => false, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Studio',       'code' => 'STD', 'is_theory' => false, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Auditorium',   'code' => 'AUD', 'is_theory' => true,  'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Seminar Room', 'code' => 'SEM', 'is_theory' => true,  'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Workshop',     'code' => 'WRK', 'is_theory' => false, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Office',       'code' => 'OFC', 'is_theory' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('fetnet_space_type');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_program', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained('fetnet_client')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 10)->nullable();
            $table->string('abbrev', 10)->nullable();
            $table->string('name', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_program');
    }
};

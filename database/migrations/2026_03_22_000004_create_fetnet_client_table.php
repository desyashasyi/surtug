<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fetnet_client', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('university_id')->nullable()->constrained('institution_university')->nullOnDelete();
            $table->foreignId('faculty_id')->nullable()->constrained('institution_faculty')->nullOnDelete();
            $table->foreignId('client_level_id')->nullable()->constrained('fetnet_client_level')->nullOnDelete();
            $table->string('description', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fetnet_client');
    }
};

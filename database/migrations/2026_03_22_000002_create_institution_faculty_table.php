<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_faculty', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->nullable();
            $table->string('name', 100)->nullable();
            $table->string('name_eng', 100)->nullable();
            $table->foreignId('university_id')->nullable()->constrained('institution_university')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_faculty');
    }
};

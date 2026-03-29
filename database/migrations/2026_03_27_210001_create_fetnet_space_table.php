<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fetnet_space', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('type_id')->nullable();
            $table->unsignedBigInteger('building_id')->nullable();
            $table->unsignedBigInteger('faculty_id')->nullable();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('floor')->nullable();
            $table->integer('capacity')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('fetnet_client')->onDelete('cascade');
            $table->foreign('type_id')->references('id')->on('fetnet_space_type')->onDelete('set null');
            $table->foreign('building_id')->references('id')->on('fetnet_building')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fetnet_space');
    }
};
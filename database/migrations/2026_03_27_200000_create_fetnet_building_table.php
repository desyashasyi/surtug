<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fetnet_building', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->string('name');
            $table->string('code')->nullable();
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('fetnet_client')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fetnet_building');
    }
};

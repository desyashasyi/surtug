<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fetnet_semester', function (Blueprint $table) {
            $table->unsignedBigInteger('academic_year_id')->nullable()->after('client_id');
            $table->foreign('academic_year_id')->references('id')->on('fetnet_academic_year')->nullOnDelete();

            $table->string('name', 50)->nullable()->after('academic_year_id');
            $table->unsignedTinyInteger('start_month')->nullable()->after('name');
            $table->unsignedTinyInteger('end_month')->nullable()->after('start_month');
            $table->date('lecture_start')->nullable()->after('end_month');
            $table->date('lecture_end')->nullable()->after('lecture_start');
        });
    }

    public function down(): void
    {
        Schema::table('fetnet_semester', function (Blueprint $table) {
            $table->dropForeign(['academic_year_id']);
            $table->dropColumn(['academic_year_id', 'name', 'start_month', 'end_month', 'lecture_start', 'lecture_end']);
        });
    }
};

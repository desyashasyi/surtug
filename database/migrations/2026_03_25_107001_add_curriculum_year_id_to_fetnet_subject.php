<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fetnet_subject', function (Blueprint $table) {
            $table->foreignId('curriculum_year_id')->nullable()->after('program_id')
                ->constrained('fetnet_curriculum_year')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fetnet_subject', function (Blueprint $table) {
            $table->dropForeign(['curriculum_year_id']);
            $table->dropColumn('curriculum_year_id');
        });
    }
};

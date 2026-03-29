<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fetnet_teacher', function (Blueprint $table) {
            $table->string('position',   100)->nullable()->after('employee_id');
            $table->string('civil_grade', 10)->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('fetnet_teacher', function (Blueprint $table) {
            $table->dropColumn(['position', 'civil_grade']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fetnet_teacher', function (Blueprint $table) {
            $table->char('univ_code', 4)->nullable()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('fetnet_teacher', function (Blueprint $table) {
            $table->dropColumn('univ_code');
        });
    }
};

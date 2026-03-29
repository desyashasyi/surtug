<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fetnet_teacher_constraint', function (Blueprint $table) {
            $table->decimal('weight', 5, 2)->default(100.00)->after('value');
        });
    }

    public function down(): void
    {
        Schema::table('fetnet_teacher_constraint', function (Blueprint $table) {
            $table->dropColumn('weight');
        });
    }
};

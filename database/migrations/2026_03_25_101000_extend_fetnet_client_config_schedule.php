<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fetnet_client_config', function (Blueprint $table) {
            $table->time('start_time')->default('07:00')->after('number_of_hours');
            $table->smallInteger('slot_duration')->default(50)->after('start_time');  // minutes
            $table->time('break_start')->default('12:00')->after('slot_duration');
            $table->time('break_end')->default('13:00')->after('break_start');
        });
    }

    public function down(): void
    {
        Schema::table('fetnet_client_config', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'slot_duration', 'break_start', 'break_end']);
        });
    }
};

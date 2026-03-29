<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fetnet_teacher_constraint', function (Blueprint $table) {
            // Drop old unique (program_id, teacher_id, constraint_type)
            $table->dropUnique(['program_id', 'teacher_id', 'constraint_type']);

            $table->foreignId('tag_id')->nullable()->after('value')
                ->constrained('fetnet_activity_tag')->nullOnDelete();
            $table->foreignId('tag2_id')->nullable()->after('tag_id')
                ->constrained('fetnet_activity_tag')->nullOnDelete();
            $table->tinyInteger('interval_start')->nullable()->after('tag2_id');
            $table->tinyInteger('interval_end')->nullable()->after('interval_start');
        });
    }

    public function down(): void
    {
        Schema::table('fetnet_teacher_constraint', function (Blueprint $table) {
            $table->dropForeign(['tag_id']);
            $table->dropForeign(['tag2_id']);
            $table->dropColumn(['tag_id', 'tag2_id', 'interval_start', 'interval_end']);
            $table->unique(['program_id', 'teacher_id', 'constraint_type']);
        });
    }
};

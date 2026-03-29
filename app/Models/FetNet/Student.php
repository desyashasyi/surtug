<?php

namespace App\Models\FetNet;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use SoftDeletes;

    protected $table   = 'fetnet_student';
    protected $guarded = [];

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function parent()
    {
        return $this->belongsTo(Student::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Student::class, 'parent_id')->orderBy('name');
    }

    public function activities()
    {
        return $this->belongsToMany(Activity::class, 'fetnet_activity_student', 'student_id', 'activity_id');
    }

    protected static function booted(): void
    {
        static::deleting(function (Student $student) {
            // Cascade soft-delete children (groups/subgroups)
            $student->children()->get()->each->delete();
            // Detach from activities
            $student->activities()->detach();
        });
    }
}

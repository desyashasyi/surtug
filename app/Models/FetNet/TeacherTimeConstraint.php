<?php

namespace App\Models\FetNet;

use Illuminate\Database\Eloquent\Model;

class TeacherTimeConstraint extends Model
{
    protected $table   = 'fetnet_time_constraint_teacher';
    protected $guarded = [];

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }
}

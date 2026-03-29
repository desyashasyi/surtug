<?php

namespace App\Models\FetNet;

use Illuminate\Database\Eloquent\Model;

class TeacherConstraint extends Model
{
    protected $table   = 'fetnet_teacher_constraint';
    protected $guarded = [];

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }
}

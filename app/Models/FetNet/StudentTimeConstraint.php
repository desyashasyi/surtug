<?php

namespace App\Models\FetNet;

use Illuminate\Database\Eloquent\Model;

class StudentTimeConstraint extends Model
{
    protected $table   = 'fetnet_time_constraint_student';
    protected $guarded = [];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}

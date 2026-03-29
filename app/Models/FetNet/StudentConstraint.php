<?php

namespace App\Models\FetNet;

use Illuminate\Database\Eloquent\Model;

class StudentConstraint extends Model
{
    protected $table   = 'fetnet_student_constraint';
    protected $guarded = [];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}

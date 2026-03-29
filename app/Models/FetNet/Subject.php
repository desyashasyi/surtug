<?php

namespace App\Models\FetNet;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\FetNet\ActivityPlanning;

class Subject extends Model
{
    use SoftDeletes;

    protected $table   = 'fetnet_subject';
    protected $guarded = [];

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function curriculumYear()
    {
        return $this->belongsTo(CurriculumYear::class, 'curriculum_year_id');
    }

    public function specialization()
    {
        return $this->belongsTo(Specialization::class, 'specialization_id');
    }

    public function type()
    {
        return $this->belongsTo(SubjectType::class, 'type_id');
    }

    public function activityPlannings()
    {
        return $this->hasMany(ActivityPlanning::class, 'subject_id');
    }

    public function activities()
    {
        return $this->hasManyThrough(Activity::class, ActivityPlanning::class, 'subject_id', 'planning_id');
    }

    protected static function booted(): void
    {
        static::deleting(function (Subject $subject) {
            $subject->activityPlannings()->get()->each->delete();
        });
    }
}
<?php

namespace App\Models\FetNet;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\FetNet\ActivityTag;
use App\Models\FetNet\ActivityPlanning;

class Activity extends Model
{
    use SoftDeletes;

    protected $table   = 'fetnet_activity';
    protected $guarded = [];
    protected $casts   = ['active' => 'boolean'];

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function planning()
    {
        return $this->belongsTo(ActivityPlanning::class, 'planning_id');
    }

    public function type()
    {
        return $this->belongsTo(ActivityType::class, 'type_id');
    }

    public function subActivities()
    {
        return $this->hasMany(SubActivity::class, 'activity_id')->orderBy('order');
    }

    public function teachers()
    {
        return $this->belongsToMany(Teacher::class, 'fetnet_activity_teacher', 'activity_id', 'teacher_id');
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'fetnet_activity_student', 'activity_id', 'student_id');
    }

    public function tags()
    {
        return $this->belongsToMany(ActivityTag::class, 'fetnet_activity_tag_map', 'activity_id', 'tag_id');
    }

    public function spaces()
    {
        return $this->belongsToMany(Space::class, 'fetnet_activity_space', 'activity_id', 'space_id');
    }

    protected static function booted(): void
    {
        static::deleting(function (Activity $activity) {
            $activity->teachers()->detach();
            $activity->students()->detach();
            $activity->tags()->detach();
            $activity->spaces()->detach();
        });
    }
}

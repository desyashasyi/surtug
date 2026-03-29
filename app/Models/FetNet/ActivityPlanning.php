<?php

namespace App\Models\FetNet;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ActivityPlanning extends Model
{
    use SoftDeletes;

    protected $table   = 'fetnet_activity_planning';
    protected $guarded = [];

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    public function activities()
    {
        return $this->hasMany(Activity::class, 'planning_id');
    }

    protected static function booted(): void
    {
        static::deleting(function (ActivityPlanning $planning) {
            // Soft-delete all activities when planning is soft-deleted
            $planning->activities()->get()->each->delete();
        });

        static::restoring(function (ActivityPlanning $planning) {
            // Restore all activities when planning is restored
            Activity::withTrashed()
                ->where('planning_id', $planning->id)
                ->get()
                ->each->restore();
        });
    }
}

<?php

namespace App\Models\FetNet;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Program extends Model
{
    use SoftDeletes;

    protected $table   = 'institution_program';
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id', 'id');
    }

    public function cluster()
    {
        return $this->hasOne(Cluster::class, 'program_id', 'id');
    }

    public function specializations()
    {
        return $this->hasMany(Specialization::class, 'program_id');
    }

    public function subjectTypes()
    {
        return $this->hasMany(SubjectType::class, 'program_id');
    }

    public function subjects()
    {
        return $this->hasMany(Subject::class, 'program_id');
    }

    public function teachers()
    {
        return $this->hasMany(Teacher::class, 'program_id');
    }

    public function students()
    {
        return $this->hasMany(Student::class, 'program_id');
    }

    public function activities()
    {
        return $this->hasMany(Activity::class, 'program_id');
    }

    /** Teachers from outside the cluster added as guests to this program. */
    public function guestTeachers()
    {
        return $this->belongsToMany(Teacher::class, 'fetnet_teacher_guest', 'program_id', 'teacher_id')
                    ->withTimestamps();
    }

    protected static function booted(): void
    {
        static::deleting(function (Program $program) {
            $program->specializations()->get()->each->delete();
            $program->subjectTypes()->get()->each->delete();
            $program->subjects()->get()->each->delete();
            $program->teachers()->get()->each->delete();
            $program->students()->whereNull('parent_id')->each->delete();
            $program->activities()->get()->each->delete();
        });
    }
}

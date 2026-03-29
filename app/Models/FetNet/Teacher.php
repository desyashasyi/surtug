<?php

namespace App\Models\FetNet;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Teacher extends Model
{
    use SoftDeletes;

    protected $table   = 'fetnet_teacher';
    protected $guarded = [];

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function activities()
    {
        return $this->belongsToMany(Activity::class, 'fetnet_activity_teacher', 'teacher_id', 'activity_id');
    }

    public function timeConstraints()
    {
        return $this->hasMany(TeacherTimeConstraint::class, 'teacher_id');
    }

    /** Programs that added this teacher as a guest (outside their own cluster). */
    public function guestPrograms()
    {
        return $this->belongsToMany(Program::class, 'fetnet_teacher_guest', 'teacher_id', 'program_id')
                    ->withTimestamps();
    }

    public function getFullNameAttribute(): string
    {
        $front = $this->front_title ? $this->front_title . ' ' : '';
        $rear  = $this->rear_title  ? ', ' . $this->rear_title  : '';
        return $front . $this->name . $rear;
    }

    /**
     * Generate a unique 3-char code for a teacher name, given already-used codes.
     * Tries initials first, then substitutes the last character (0-9, A-Z) until unique.
     */
    public static function generateCode(string $name, array $usedCodes): string
    {
        $words    = preg_split('/\s+/', strtoupper(trim($name)));
        $initials = implode('', array_map(fn($w) => $w[0] ?? '', array_filter($words)));
        $base     = strtoupper(substr(str_pad($initials, 3, 'X'), 0, 3));

        if (! in_array($base, $usedCodes)) return $base;

        $prefix = substr($base, 0, 2);
        foreach ([...range('0', '9'), ...range('A', 'Z')] as $c) {
            $candidate = $prefix . $c;
            if (! in_array($candidate, $usedCodes)) return $candidate;
        }

        // Last resort: random suffix
        return $prefix . chr(random_int(65, 90));
    }

    protected static function booted(): void
    {
        static::deleting(function (Teacher $teacher) {
            $teacher->activities()->detach();
        });
    }
}

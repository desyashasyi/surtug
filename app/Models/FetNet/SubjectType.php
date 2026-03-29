<?php

namespace App\Models\FetNet;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubjectType extends Model
{
    use SoftDeletes;

    protected $table   = 'fetnet_subject_type';
    protected $guarded = [];

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function subjects()
    {
        return $this->hasMany(Subject::class, 'type_id');
    }

    protected static function booted(): void
    {
        static::deleting(function (SubjectType $type) {
            $type->subjects()->update(['type_id' => null]);
        });
    }
}

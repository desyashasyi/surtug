<?php

namespace App\Models\FetNet;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Specialization extends Model
{
    use SoftDeletes;

    protected $table   = 'fetnet_specialization';
    protected $guarded = [];

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function subjects()
    {
        return $this->hasMany(Subject::class, 'specialization_id');
    }

    protected static function booted(): void
    {
        static::deleting(function (Specialization $specialization) {
            // Nullify subject specialization_id on soft-delete (match nullOnDelete DB constraint)
            $specialization->subjects()->update(['specialization_id' => null]);
        });
    }
}

<?php

namespace App\Models\FetNet;

use Illuminate\Database\Eloquent\Model;

class CurriculumYear extends Model
{
    protected $table   = 'fetnet_curriculum_year';
    protected $guarded = [];

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function subjects()
    {
        return $this->hasMany(Subject::class, 'curriculum_year_id');
    }
}

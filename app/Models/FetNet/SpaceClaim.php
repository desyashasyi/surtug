<?php

namespace App\Models\FetNet;

use Illuminate\Database\Eloquent\Model;

class SpaceClaim extends Model
{
    protected $table   = 'fetnet_space_claim';
    protected $guarded = [];

    public function space()
    {
        return $this->belongsTo(Space::class, 'space_id');
    }

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }
}

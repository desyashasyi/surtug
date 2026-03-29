<?php

namespace App\Models\FetNet;

use Illuminate\Database\Eloquent\Model;

class ActivityTag extends Model
{
    protected $table   = 'fetnet_activity_tag';
    protected $guarded = [];

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }
}

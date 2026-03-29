<?php

namespace App\Models\FetNet;

use Illuminate\Database\Eloquent\Model;

class ActivityTimeConstraint extends Model
{
    protected $table   = 'fetnet_time_constraint_activity';
    protected $guarded = [];

    public function activity()
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }
}

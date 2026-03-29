<?php

namespace App\Models\FetNet;

use Illuminate\Database\Eloquent\Model;

class SubActivity extends Model
{
    protected $table   = 'fetnet_sub_activity';
    protected $guarded = [];

    public function activity()
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }
}
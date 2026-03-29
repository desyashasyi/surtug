<?php

namespace App\Models\FetNet;

use Illuminate\Database\Eloquent\Model;

class ActivityType extends Model
{
    protected $table   = 'fetnet_activity_type';
    protected $guarded = [];

    public function activities()
    {
        return $this->hasMany(Activity::class, 'type_id');
    }
}

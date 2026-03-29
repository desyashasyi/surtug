<?php

namespace App\Models\FetNet;

use Illuminate\Database\Eloquent\Model;

class Building extends Model
{
    protected $table   = 'fetnet_building';
    protected $guarded = [];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function spaces()
    {
        return $this->hasMany(Space::class, 'building_id');
    }
}

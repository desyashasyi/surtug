<?php

namespace App\Models\FetNet;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Space extends Model
{
    use SoftDeletes;

    protected $table   = 'fetnet_space';
    protected $guarded = [];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function type()
    {
        return $this->belongsTo(SpaceType::class, 'type_id');
    }

    public function building()
    {
        return $this->belongsTo(Building::class, 'building_id');
    }

    public function faculty()
    {
        return $this->belongsTo(Faculty::class, 'faculty_id');
    }

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function claims()
    {
        return $this->hasMany(SpaceClaim::class, 'space_id');
    }

    public function activities()
    {
        return $this->belongsToMany(Activity::class, 'fetnet_activity_space', 'space_id', 'activity_id');
    }
}

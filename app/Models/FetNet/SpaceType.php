<?php

namespace App\Models\FetNet;

use Illuminate\Database\Eloquent\Model;

class SpaceType extends Model
{
    protected $table   = 'fetnet_space_type';
    protected $guarded = [];
    protected $casts   = ['is_theory' => 'boolean'];

    public function spaces()
    {
        return $this->hasMany(Space::class, 'type_id');
    }
}

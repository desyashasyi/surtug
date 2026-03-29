<?php

namespace App\Models\FetNet;

use Illuminate\Database\Eloquent\Model;

class Cluster extends Model
{
    protected $table   = 'fetnet_cluster';
    protected $guarded = [];

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id', 'id');
    }

    public function base()
    {
        return $this->belongsTo(ClusterBase::class, 'cluster_base_id', 'id');
    }
}

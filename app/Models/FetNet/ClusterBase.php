<?php

namespace App\Models\FetNet;

use Illuminate\Database\Eloquent\Model;

class ClusterBase extends Model
{
    protected $table   = 'fetnet_cluster_base';
    protected $guarded = [];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id', 'id');
    }
}

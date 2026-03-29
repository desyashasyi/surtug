<?php

namespace App\Models\FetNet;

use Illuminate\Database\Eloquent\Model;

class Faculty extends Model
{
    protected $table   = 'institution_faculty';
    protected $guarded = [];

    public function university()
    {
        return $this->belongsTo(University::class, 'university_id', 'id');
    }
}

<?php

namespace App\Models\FetNet;

use Illuminate\Database\Eloquent\Model;

class AcademicYear extends Model
{
    protected $table   = 'fetnet_academic_year';
    protected $guarded = [];
    protected $casts   = ['is_active' => 'boolean'];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function semesters()
    {
        return $this->hasMany(Semester::class, 'academic_year_id')->orderBy('start_month');
    }

    /** Label: "2024/2025" */
    public function getLabelAttribute(): string
    {
        return $this->year_start . '/' . ($this->year_start + 1);
    }
}

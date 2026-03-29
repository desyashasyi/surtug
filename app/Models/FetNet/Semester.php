<?php

namespace App\Models\FetNet;

use Illuminate\Database\Eloquent\Model;

class Semester extends Model
{
    protected $table   = 'fetnet_semester';
    protected $guarded = [];
    protected $casts   = [
        'lecture_start' => 'date',
        'lecture_end'   => 'date',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id', 'id');
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function getLabelAttribute(): string
    {
        $names = [
            1  => 'January',   2  => 'February', 3  => 'March',
            4  => 'April',     5  => 'May',       6  => 'June',
            7  => 'July',      8  => 'August',    9  => 'September',
            10 => 'October',   11 => 'November',  12 => 'December',
        ];

        $name  = $this->name ?? ($this->semester == 1 ? 'Odd' : 'Even');
        $start = $names[$this->start_month] ?? '';
        $end   = $names[$this->end_month]   ?? '';

        return $name . ($start && $end ? " ({$start}–{$end})" : '');
    }
}

<?php

namespace App\Imports;

use App\Models\ST\Teacher;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation; //

class TeacherImport implements ToCollection, WithHeadingRow, WithValidation
{
    public $programId;

    public function __construct(int $programId)
    {
        $this->programId = $programId;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            Teacher::updateOrCreate(
                ['code' => $row['kode_dosen']],
                [
                    'univ_code'   => $row['kode_univ'] ?? null,
                    'employee_id' => $row['employee_id'] ?? null,
                    'name'        => $row['nama_dosen'],
                    'front_title' => $row['title_depan'] ?? null,
                    'rear_title'  => $row['title_belakang'] ?? null,
                    'email'       => $row['email'] ?? null,
                    'program_id'  => $this->programId,
                ]
            );
        }
    }

    public function rules(): array
    {
        return [
            'kode_dosen' => 'required',
            'nama_dosen' => 'required',
        ];
    }
}

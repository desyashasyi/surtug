<?php

namespace App\Imports;

use App\Models\ST\Subject;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class SubjectImport implements ToCollection, WithHeadingRow, WithValidation
{
    public $programId;

    // Menerima Program ID dari Job
    public function __construct(int $programId)
    {
        $this->programId = $programId;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            // Skip jika data kosong
            if (!isset($row['code']) || !isset($row['name'])) {
                continue;
            }

            // Update atau Create data
            // Kunci unik kombinasi Code + Program ID (agar kode sama di prodi lain tidak tertimpa)
            Subject::updateOrCreate(
                [
                    'code'       => $row['code'],
                    'program_id' => $this->programId
                ],
                [
                    'name'       => $row['name'],
                    'credit'     => $row['credit'] ?? 0,
                    'semester'   => $row['semester'] ?? 1,
                    'curriculum' => $row['curriculum'] ?? date('Y'),
                    // Pastikan program_id tetap terisi/terupdate
                    'program_id' => $this->programId,
                ]
            );
        }
    }

    public function rules(): array
    {
        return [
            'code' => 'required',
            'name' => 'required',
        ];
    }
}

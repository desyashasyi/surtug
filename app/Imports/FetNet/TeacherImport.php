<?php

namespace App\Imports\FetNet;

use App\Models\FetNet\Teacher;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class TeacherImport implements ToCollection, WithHeadingRow
{
    public int   $imported    = 0;
    public int   $skipped     = 0;
    public array $codeAutoGen = [];  // names whose code was auto-generated

    /**
     * @param array<string,int> $programMap        lowercase abbrev => program_id
     * @param int               $defaultProgramId  fallback when study_program is empty or not found
     */
    public function __construct(
        public array $programMap,
        public int   $defaultProgramId,
    ) {}

    public function collection(Collection $rows): void
    {
        // Pre-load all existing codes in the cluster programs to detect duplicates
        $usedCodes = Teacher::whereIn('program_id', array_values($this->programMap))
            ->whereNotNull('code')
            ->pluck('code')
            ->map(fn($c) => strtoupper($c))
            ->toArray();

        foreach ($rows as $row) {
            $name         = trim($row['name']          ?? '');
            $studyProgram = strtolower(trim($row['study_program'] ?? ''));

            if ($name === '') {
                $this->skipped++;
                continue;
            }

            // Use matched program, or fall back to the default program
            $programId = $this->programMap[$studyProgram] ?? $this->defaultProgramId;

            // Resolve code: must be exactly 3 chars; auto-generate if blank, invalid, or duplicate
            $rawCode = strtoupper(trim($row['code'] ?? ''));
            if (strlen($rawCode) === 3 && ! in_array($rawCode, $usedCodes)) {
                $code = $rawCode;
            } else {
                // Check if this teacher already exists and already has a valid code
                $existing = Teacher::withTrashed()
                    ->where('name', $name)
                    ->where('program_id', $programId)
                    ->first();

                if ($existing && $existing->code && strlen($existing->code) === 3) {
                    $code = strtoupper($existing->code);
                } else {
                    $code = Teacher::generateCode($name, $usedCodes);
                    $this->codeAutoGen[] = "{$name} → {$code}";
                }
            }

            $usedCodes[] = $code; // reserve for subsequent rows

            Teacher::withTrashed()->updateOrCreate(
                ['name' => $name, 'program_id' => $programId],
                [
                    'code'        => $code,
                    'univ_code'   => strtoupper(trim($row['univ_code'] ?? '')) ?: null,
                    'employee_id' => $row['employee_id'] ?? null,
                    'position'    => trim($row['position']    ?? '') ?: null,
                    'civil_grade' => trim($row['civil_grade'] ?? '') ?: null,
                    'front_title' => $row['front_title'] ?? null,
                    'rear_title'  => $row['rear_title']  ?? null,
                    'email'       => $row['email']       ?? null,
                    'phone'       => $row['phone']       ?? null,
                    'deleted_at'  => null,
                ]
            );

            $this->imported++;
        }
    }
}

<?php

namespace App\Imports;

use App\Models\ST\Schedule;
use App\Models\ST\Teacher;
use App\Models\ST\Subject;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Illuminate\Support\Facades\DB;

class ScheduleImport_backup implements ToCollection, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    public $programId;

    public function __construct(int $programId)
    {
        $this->programId = $programId;
    }

    public function collection(Collection $rows)
    {
        echo "\n--- DEBUG: Koleksi Dimulai (" . $rows->count() . " baris) ---\n";

        foreach ($rows as $index => $row) {
            $nomorBaris = $index + 2;
            $rawMatkul = $row['mata_kuliah']; // String Excel: "EE103 - NAMA - SKS"

            echo "Row {$nomorBaris}: [{$row['kelas']}] - {$rawMatkul}\n";

            try {
                DB::transaction(function () use ($row, $rawMatkul, $nomorBaris) {

                    // --- 1. LOGIKA MENCARI SUBJECT ID ---
                    $subjectId = null; // Default KOSONG (NULL)

                    // Pecah string: "EE103 - ALGORITMA - 3 sks"
                    $parts = explode('-', $rawMatkul);

                    // Minimal harus ada Kode (index 0)
                    if (count($parts) >= 1) {
                        $code = trim($parts[0]); // Ambil "EE103"

                        // -> CUMA CARI (JANGAN BUAT BARU) <-
                        $subject = Subject::where('code', $code)
                            ->where('program_id', $this->programId)
                            ->first();

                        if ($subject) {
                            $subjectId = $subject->id;
                            echo "   -> Matkul Ditemukan ID: {$subjectId}\n";
                        } else {
                            // Jika tidak ditemukan, $subjectId tetap null
                            echo "   -> Matkul dengan kode [{$code}] TIDAK DITEMUKAN. Subject ID dikosongkan.\n";
                        }
                    }

                    // --- 2. SIMPAN SCHEDULE ---
                    $schedule = Schedule::create([
                        'program_id' => $this->programId,
                        'subject_id' => $subjectId, // Bisa NULL jika tidak ketemu
                        'student'    => $row['kelas'],
                        'year'       => $row['angk'],
                        'day'        => $row['hari'],
                        'start'      => $row['jam_1'],
                        'end'        => $row['jam_2'],
                        'room'       => $row['ruang'],
                    ]);

                    // --- 3. PROSES DOSEN (PIVOT) ---
                    if (!empty($row['dosen'])) {
                        $partsDosen = explode(',', $row['dosen']);
                        foreach ($partsDosen as $p) {
                            // Asumsi format: "KO001 - Nama Dosen"
                            $dosenCode = trim(explode('-', $p)[0]);

                            $teacher = Teacher::where('univ_code', $dosenCode)->first();

                            if ($teacher) {
                                $schedule->teachers()->attach($teacher->id);
                            } else {
                                echo "   !! Warning: Dosen kode [{$dosenCode}] tidak ditemukan.\n";
                            }
                        }
                    }
                });

            } catch (\Exception $e) {
                echo "   !! ERROR IMPORT Row {$nomorBaris}: " . $e->getMessage() . "\n";
            }
        }
        echo "--- DEBUG: Koleksi Selesai ---\n";
    }

    public function rules(): array
    {
        return [
            'kelas'       => 'required',
            'angk'        => 'required',
            'hari'        => 'required',
            'jam_1'       => 'required',
            'jam_2'       => 'required',
            'mata_kuliah' => 'required',
            'dosen'       => 'required',
        ];
    }
}

<?php

namespace App\Imports;

use App\Models\ST\Schedule;
use App\Models\ST\Teacher;
use App\Models\ST\Subject; // Pastikan Model Subject di-import
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Shared\Date; // Import the Date helper

class ScheduleImport implements ToCollection, WithHeadingRow, WithValidation, SkipsEmptyRows
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

            // Ambil data Mata Kuliah dari Excel
            $mkCode = trim($row['kode_mata_kuliah']);
            $mkName = trim($row['mata_kuliah']);

            echo "Row {$nomorBaris}: [{$mkCode}] {$mkName}\n";

            try {
                DB::transaction(function () use ($row, $mkCode, $mkName) {

                    // --- 1. PROSES SUBJECT (MATCHING / CREATE) ---
                    // Cari Subject berdasarkan Kode. Jika tidak ada, buat baru.
                    // Ini mencegah error jika mata kuliah belum ada di master data.
                    $subject = Subject::firstOrCreate(
                        ['code' => $mkCode], // Kunci pencarian
                        [
                            'name' => $mkName,
                            'program_id' => $this->programId, // Asumsi subject ikut prodi yang sama
                            'credit' => 0 // Default 0 karena tidak ada info SKS di Excel ini
                        ]
                    );

                    // --- 2. SIMPAN SCHEDULE DENGAN SUBJECT_ID ---
                    $schedule = Schedule::create([
                        'program_id' => $this->programId,
                        'subject_id' => $subject->id, // Menggunakan ID dari relasi Subject
                        'student'    => $row['kelas'],
                        //'year'       => $row['angk'],
                        'day'        => $row['hari'],
                        // FIX: Convert Excel time to a database-friendly format.
                        'start'      => Date::excelToDateTimeObject($row['jam_1'])->format('H:i:s'),
                        'end'        => Date::excelToDateTimeObject($row['jam_2'])->format('H:i:s'),
                        'room'       => $row['ruang'],
                    ]);

                    // --- 3. PROSES DOSEN (LOGIKA LAMA) ---
                    $rawTeachers = [];
                    if (!empty($row['dosen_1'])) $rawTeachers[] = $row['dosen_1'];
                    if (!empty($row['dosen_2'])) $rawTeachers[] = $row['dosen_2'];

                    foreach ($rawTeachers as $rawTeacherString) {
                        // Format Excel: "2745-Tommi Hariyadi..."
                        $parts = explode('-', $rawTeacherString);
                        $dosenCode = trim($parts[0]); // Ambil angka depan (univ_code)

                        if (!empty($dosenCode)) {
                            // Cari dosen di DB
                            $teacher = Teacher::where('univ_code', $dosenCode)->first();

                            if ($teacher) {
                                $schedule->teachers()->attach($teacher->id);
                                echo "   -> Dosen terhubung: {$teacher->name}\n";
                            } else {
                                echo "   !! Warning: Dosen kode [{$dosenCode}] tidak ditemukan.\n";
                            }
                        }
                    }
                });
            } catch (\Exception $e) {
                echo "   !! ERROR di baris {$nomorBaris}: " . $e->getMessage() . "\n";
            }
        }
        echo "--- DEBUG: Koleksi Selesai ---\n";
    }

    public function rules(): array
    {
        return [
            'kelas'            => 'required',
            'hari'             => 'required',
            'jam_1'            => 'required',
            'jam_2'            => 'required',
            'kode_mata_kuliah' => 'required',
            'mata_kuliah'      => 'required',
            'dosen_1'          => 'nullable',
        ];
    }
}

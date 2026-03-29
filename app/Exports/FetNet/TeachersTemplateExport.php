<?php

namespace App\Exports\FetNet;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TeachersTemplateExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    public function __construct(public string $programAbbrev = '') {}

    public function title(): string
    {
        return 'Teachers';
    }

    public function headings(): array
    {
        return ['study_program', 'name', 'code', 'univ_code', 'employee_id', 'position', 'civil_grade', 'front_title', 'rear_title', 'email', 'phone'];
    }

    public function array(): array
    {
        $abbrev = $this->programAbbrev ?: 'PRODI';

        return [
            [$abbrev, 'Ahmad Fauzan', 'AFK', 'A001', '197001011990031001', 'Lektor Kepala', 'IV/a', 'Dr.',  'M.T., Ph.D.', 'afauzan@univ.ac.id',  '081234567890'],
            [$abbrev, 'Siti Rahayu',  'SRH', 'A002', '197505152000122001', 'Lektor',        'III/c', 'Ir.',  'M.Sc.',      'srahayu@univ.ac.id',  '082345678901'],
            [$abbrev, 'Budi Santoso', 'BSN', null,   '198010202005011002', 'Asisten Ahli',  'III/b', null,   'S.T., M.T.', 'bsantoso@univ.ac.id', '083456789012'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:K1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7C3AED']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->getStyle('A1:K4')->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']],
            ],
        ]);

        $sheet->setCellValue('A6', '* study_program and name are required. study_program must match the program abbreviation. code = exactly 3 characters (auto-generated if blank or duplicate). position = Jabatan (e.g. Lektor Kepala). civil_grade = Golongan (e.g. IV/a).');
        $sheet->getStyle('A6')->applyFromArray([
            'font'      => ['italic' => true, 'color' => ['rgb' => '9CA3AF'], 'size' => 10],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->mergeCells('A6:K6');

        return [];
    }
}

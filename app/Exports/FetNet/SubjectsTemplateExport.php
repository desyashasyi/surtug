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

class SubjectsTemplateExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    public function title(): string
    {
        return 'Subjects';
    }

    public function headings(): array
    {
        return ['code', 'name', 'credit', 'semester', 'curriculum_year', 'specialization', 'type'];
    }

    public function array(): array
    {
        return [
            ['ELC301', 'Electrical Machines I',  3, 3, '2020', 'POWER', 'MK'],
            ['ELC302', 'Electrical Machines II', 3, 4, '2020', 'POWER', 'MK'],
            ['ELC401', 'Power Systems Analysis', 3, 5, '2020', null,    null],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7C3AED']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->getStyle('A1:G4')->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']],
            ],
        ]);

        $sheet->setCellValue('A6', '* code and name are required. credit default 2. semester optional (1–8). curriculum_year: year string (e.g. 2020) — auto-created if not found. specialization: specialization code (auto-created if not found). type: subject type code (auto-created if not found).');
        $sheet->getStyle('A6')->applyFromArray([
            'font'      => ['italic' => true, 'color' => ['rgb' => '9CA3AF'], 'size' => 10],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->mergeCells('A6:G6');

        return [];
    }
}

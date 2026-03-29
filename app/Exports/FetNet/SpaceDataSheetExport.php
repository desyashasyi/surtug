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

class SpaceDataSheetExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    public function title(): string
    {
        return 'Spaces';
    }

    public function headings(): array
    {
        return ['name', 'code', 'type_code', 'building_code', 'floor', 'capacity'];
    }

    public function array(): array
    {
        return [
            ['Lab Komputer A',   'LAB-A',  'LAB', 'GDA', '2', 40],
            ['Ruang Kuliah 101', 'RK-101', 'CLS', 'GDB', '1', 60],
            ['Studio Desain',    'STD-1',  'STD', null,  '3', 30],
            ['Auditorium',       'AUD',    'AUD', null,  '1', 300],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:F1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7C3AED']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->getStyle('A1:F5')->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']],
            ],
        ]);

        $sheet->setCellValue('A7', '* name is required. type_code: see "Space Types" sheet. building_code must match an existing building code. capacity = integer.');
        $sheet->getStyle('A7')->applyFromArray([
            'font'      => ['italic' => true, 'color' => ['rgb' => '9CA3AF'], 'size' => 10],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'wrapText' => true],
        ]);
        $sheet->mergeCells('A7:F7');
        $sheet->getRowDimension(7)->setRowHeight(30);

        return [];
    }
}

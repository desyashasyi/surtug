<?php

namespace App\Exports\FetNet;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SpaceTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new SpaceDataSheetExport(),
            new SpaceTypeSheetExport(),
        ];
    }
}

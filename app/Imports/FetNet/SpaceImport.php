<?php

namespace App\Imports\FetNet;

use App\Models\FetNet\Building;
use App\Models\FetNet\Space;
use App\Models\FetNet\SpaceType;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class SpaceImport implements ToCollection, WithHeadingRow
{
    public int   $imported = 0;
    public int   $skipped  = 0;

    public function __construct(
        public int $clientId,
    ) {}

    public function collection(Collection $rows): void
    {
        // Build mutable lookup maps (updated when new records are created)
        $buildingMap = Building::where('client_id', $this->clientId)
            ->get(['id', 'code'])
            ->mapWithKeys(fn($b) => [strtolower(trim($b->code ?? '')) => $b->id])
            ->toArray();

        $typeMap = SpaceType::all(['id', 'code'])
            ->mapWithKeys(fn($t) => [strtolower(trim($t->code ?? '')) => $t->id])
            ->toArray();

        foreach ($rows as $row) {
            $name = trim($row['name'] ?? '');
            if ($name === '') {
                $this->skipped++;
                continue;
            }

            $buildingCode = strtolower(trim($row['building_code'] ?? ''));
            $typeCode     = strtolower(trim($row['type_code'] ?? ''));
            $capacity     = isset($row['capacity']) && is_numeric($row['capacity'])
                ? (int) $row['capacity']
                : null;

            // Auto-register building if code given but not found
            if ($buildingCode !== '' && ! isset($buildingMap[$buildingCode])) {
                $rawCode = trim($row['building_code']);
                $building = Building::firstOrCreate(
                    ['code' => $rawCode, 'client_id' => $this->clientId],
                    ['name' => $rawCode],
                );
                $buildingMap[$buildingCode] = $building->id;
            }

            // Auto-register space type if code given but not found
            if ($typeCode !== '' && ! isset($typeMap[$typeCode])) {
                $rawCode = trim($row['type_code']);
                $type = SpaceType::firstOrCreate(
                    ['code' => $rawCode],
                    ['name' => Str::title($rawCode)],
                );
                $typeMap[$typeCode] = $type->id;
            }

            Space::withTrashed()->updateOrCreate(
                ['name' => $name, 'client_id' => $this->clientId],
                [
                    'code'        => trim($row['code'] ?? '') ?: null,
                    'type_id'     => $typeCode ? ($typeMap[$typeCode] ?? null) : null,
                    'building_id' => $buildingCode ? ($buildingMap[$buildingCode] ?? null) : null,
                    'floor'       => trim($row['floor'] ?? '') ?: null,
                    'capacity'    => $capacity,
                    'deleted_at'  => null,
                ]
            );

            $this->imported++;
        }
    }
}

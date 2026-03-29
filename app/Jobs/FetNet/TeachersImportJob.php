<?php

namespace App\Jobs\FetNet;

use App\Events\FetNet\TeachersImportEvent;
use App\Imports\FetNet\TeacherImport;
use App\Models\FetNet\Cluster;
use App\Models\FetNet\Program;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class TeachersImportJob implements ShouldQueue
{
    use Queueable;

    public int $tries   = 1;
    public int $timeout = 300;

    public function __construct(
        public string $filePath,
        public int    $programId,
    ) {}

    public function handle(): void
    {
        // Build cluster-aware program map: lowercase abbrev => program_id
        $clusterEntry = Cluster::where('program_id', $this->programId)->first();

        $programIds = $clusterEntry
            ? Cluster::where('cluster_base_id', $clusterEntry->cluster_base_id)
                ->pluck('program_id')
                ->toArray()
            : [$this->programId];

        $programMap = Program::whereIn('id', $programIds)->get(['id', 'abbrev'])
            ->mapWithKeys(fn($p) => [strtolower($p->abbrev) => $p->id])
            ->toArray();

        $importer = new TeacherImport($programMap, $this->programId);
        Excel::import($importer, $this->filePath);

        $message = "Import done: {$importer->imported} imported, {$importer->skipped} skipped.";

        if (count($importer->codeAutoGen) > 0) {
            $codes    = implode(', ', $importer->codeAutoGen);
            $message .= " Auto-generated codes: {$codes}.";
        }

        TeachersImportEvent::dispatch('success', $message);
    }

    public function failed(Throwable $e): void
    {
        TeachersImportEvent::dispatch('error', 'Import gagal: ' . $e->getMessage());
    }
}

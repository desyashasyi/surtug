<?php

namespace App\Jobs\FetNet;

use App\Events\FetNet\SpaceImportEvent;
use App\Imports\FetNet\SpaceImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class SpaceImportJob implements ShouldQueue
{
    use Queueable;

    public int $tries   = 1;
    public int $timeout = 300;

    public function __construct(
        public string $filePath,
        public int    $clientId,
    ) {}

    public function handle(): void
    {
        $importer = new SpaceImport($this->clientId);
        Excel::import($importer, $this->filePath);

        $message = "Import done: {$importer->imported} imported, {$importer->skipped} skipped.";
        SpaceImportEvent::dispatch('success', $message);
    }

    public function failed(Throwable $e): void
    {
        SpaceImportEvent::dispatch('error', 'Import gagal: ' . $e->getMessage());
    }
}

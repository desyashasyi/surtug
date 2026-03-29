<?php

namespace App\Jobs\FetNet;

use App\Models\FetNet\Activity;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AssignSpacesToActivityJob implements ShouldQueue
{
    use Queueable;

    public int $tries   = 1;
    public int $timeout = 60;

    public function __construct(
        public int   $activityId,
        public array $spaceIds,
    ) {}

    public function handle(): void
    {
        Activity::find($this->activityId)?->spaces()->syncWithoutDetaching($this->spaceIds);
    }
}
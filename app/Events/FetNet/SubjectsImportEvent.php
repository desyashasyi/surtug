<?php

namespace App\Events\FetNet;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubjectsImportEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $status,   // 'success' | 'error'
        public string $message,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('subjects-import')];
    }

    public function broadcastAs(): string
    {
        return 'SubjectsImportEvent';
    }
}

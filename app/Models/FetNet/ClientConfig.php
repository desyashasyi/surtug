<?php

namespace App\Models\FetNet;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ClientConfig extends Model
{
    protected $table   = 'fetnet_client_config';
    protected $guarded = [];

    /**
     * Generate an ordered list of entries for the schedule grid.
     * Each entry: ['idx' => int|null, 'time' => string, 'break' => bool]
     * Break entries have idx=null and break=true.
     */
    public function generateSlots(): array
    {
        $entries    = [];
        $current    = Carbon::createFromTimeString($this->start_time  ?? '07:00');
        $duration   = (int) ($this->slot_duration  ?? 50);
        $total      = (int) ($this->number_of_hours ?? 0);
        $useBreak   = ! $this->no_break;
        $breakStart = $useBreak ? Carbon::createFromTimeString($this->break_start ?? '12:00') : null;
        $breakEnd   = $useBreak ? Carbon::createFromTimeString($this->break_end   ?? '13:00') : null;
        $breakAdded = false;

        for ($i = 1; $i <= $total; $i++) {
            if ($useBreak && ! $breakAdded && $current >= $breakStart) {
                $entries[] = [
                    'idx'   => 0,
                    'time'  => $breakStart->format('H:i') . '–' . $breakEnd->format('H:i'),
                    'break' => true,
                ];
                $breakAdded = true;
                $current    = $breakEnd->copy();
            }

            $end = $current->copy()->addMinutes($duration);
            $entries[] = [
                'idx'   => $i,
                'time'  => $current->format('H:i') . '–' . $end->format('H:i'),
                'break' => false,
            ];
            $current = $end;
        }

        return $entries;
    }

    /**
     * Day labels starting from Monday, limited to number_of_days.
     */
    public function dayLabels(): array
    {
        $all = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        return array_slice($all, 0, (int) ($this->number_of_days ?? 0));
    }
}

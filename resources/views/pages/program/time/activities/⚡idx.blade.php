<?php

use App\Livewire\Concerns\HasProgramSemester;
use App\Models\FetNet\Activity;
use App\Models\FetNet\ActivityTimeConstraint;
use App\Models\FetNet\Client;
use App\Models\FetNet\Program;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;

new #[Layout('layouts.program')] class extends Component
{
    use Toast, HasProgramSemester;

    public bool   $modal            = false;
    public ?int   $activityId       = null;
    public float  $constraintWeight = 100.0;
    public array  $blocked          = [];
    public array  $activityOptions  = [];

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function program(): ?Program
    {
        return Program::where('user_id', auth()->id())->first();
    }

    private function config()
    {
        $program = $this->program();
        if (! $program) return null;
        return Client::with('config')->find($program->client_id)?->config;
    }

    // ── Lifecycle ──────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $program = $this->program();
        if ($program) $this->mountSemesterContext($program->client_id);
        $this->searchActivities();
    }

    public function searchActivities(string $value = ''): void
    {
        $program = $this->program();
        if (! $program || ! $this->semesterId) { $this->activityOptions = []; return; }

        $this->activityOptions = Activity::where('program_id', $program->id)
            ->whereHas('planning', fn($q) => $q->where('semester_id', $this->semesterId))
            ->with(['planning.subject', 'teachers', 'students', 'type'])
            ->when($value, fn($q) => $q->whereHas('planning', fn($p) => $p->whereHas('subject',
                fn($s) => $s->where('name', 'ilike', "%{$value}%")->orWhere('code', 'ilike', "%{$value}%")
            )))
            ->orderBy('id')
            ->limit(30)
            ->get()
            ->map(function ($a) {
                $subject  = $a->planning?->subject;
                $teachers = $a->teachers->pluck('code')->filter()->implode('|');
                $groups   = $a->students->pluck('name')->implode('|');
                $label    = ($subject ? "[{$subject->code}] {$subject->name}" : '?');
                $detail   = collect([$a->type?->name, $teachers ?: '?', $groups])->filter()->implode(' · ');
                return ['id' => $a->id, 'name' => $label . ' — ' . $detail];
            })->values()->toArray();
    }

    public function updatedActivityId(): void
    {
        $this->loadBlocked();
        $this->loadWeight();
    }

    public function updatedSemesterId(): void
    {
        $this->persistSemester();
        $this->activityId = null;
        $this->blocked    = [];
        $this->searchActivities();
    }

    public function updatedAcademicYearId(): void
    {
        $this->semesterId = null;
        $this->loadProgramSemesters();
        $this->persistSemester();
        $this->activityId = null;
        $this->blocked    = [];
        $this->searchActivities();
    }

    // ── Open modal ─────────────────────────────────────────────────────────────

    public function openAdd(): void
    {
        $this->reset(['activityId', 'blocked']);
        $this->constraintWeight = 100.0;
        $this->searchActivities();
        $this->modal = true;
    }

    public function openEdit(int $activityId): void
    {
        $this->activityId = $activityId;
        $this->searchActivities();
        $this->loadBlocked();
        $this->loadWeight();
        $this->modal = true;
    }

    // ── Grid ───────────────────────────────────────────────────────────────────

    private function loadBlocked(): void
    {
        if (! $this->activityId) { $this->blocked = []; return; }

        $this->blocked = ActivityTimeConstraint::where('activity_id', $this->activityId)
            ->get()
            ->mapWithKeys(fn($c) => ["{$c->day}-{$c->hour}" => true])
            ->toArray();
    }

    private function loadWeight(): void
    {
        $this->constraintWeight = 100.0;
    }

    public function toggle(int $day, int $hour): void
    {
        if (! $this->activityId) return;
        $key = "{$day}-{$hour}";

        if (isset($this->blocked[$key])) {
            ActivityTimeConstraint::where('activity_id', $this->activityId)
                ->where('day', $day)->where('hour', $hour)->delete();
            unset($this->blocked[$key]);
        } else {
            ActivityTimeConstraint::firstOrCreate([
                'activity_id' => $this->activityId, 'day' => $day, 'hour' => $hour,
            ]);
            $this->blocked[$key] = true;
        }
    }

    public function toggleDay(int $day): void
    {
        if (! $this->activityId) return;
        $hours = range(1, $this->config()?->number_of_hours ?? 0);
        $allBlocked = collect($hours)->every(fn($h) => isset($this->blocked["{$day}-{$h}"]));

        if ($allBlocked) {
            ActivityTimeConstraint::where('activity_id', $this->activityId)->where('day', $day)->delete();
            foreach ($hours as $h) unset($this->blocked["{$day}-{$h}"]);
        } else {
            foreach ($hours as $h) {
                $key = "{$day}-{$h}";
                if (! isset($this->blocked[$key])) {
                    ActivityTimeConstraint::firstOrCreate([
                        'activity_id' => $this->activityId, 'day' => $day, 'hour' => $h,
                    ]);
                    $this->blocked[$key] = true;
                }
            }
        }
    }

    public function toggleSlot(int $hour): void
    {
        if (! $this->activityId) return;
        $total      = $this->config()?->number_of_days ?? 0;
        $allBlocked = collect(range(1, $total))->every(fn($d) => isset($this->blocked["{$d}-{$hour}"]));

        if ($allBlocked) {
            ActivityTimeConstraint::where('activity_id', $this->activityId)->where('hour', $hour)->delete();
            for ($d = 1; $d <= $total; $d++) unset($this->blocked["{$d}-{$hour}"]);
        } else {
            for ($d = 1; $d <= $total; $d++) {
                $key = "{$d}-{$hour}";
                if (! isset($this->blocked[$key])) {
                    ActivityTimeConstraint::firstOrCreate([
                        'activity_id' => $this->activityId, 'day' => $d, 'hour' => $hour,
                    ]);
                    $this->blocked[$key] = true;
                }
            }
        }
    }

    public function clearBlocked(int $activityId): void
    {
        ActivityTimeConstraint::where('activity_id', $activityId)->delete();
        if ($this->activityId === $activityId) $this->blocked = [];
        $this->warning('Not available periods cleared.', position: 'toast-top toast-center');
    }

    // ── with() ─────────────────────────────────────────────────────────────────

    public function with(): array
    {
        $config  = $this->config();
        $program = $this->program();

        // Summary: only activities with at least one blocked slot
        $summaryRows = collect();
        if ($program && $this->semesterId) {
            $actIds = Activity::where('program_id', $program->id)
                ->whereHas('planning', fn($q) => $q->where('semester_id', $this->semesterId))
                ->pluck('id');

            $blockedAll = ActivityTimeConstraint::whereIn('activity_id', $actIds)
                ->orderBy('day')->orderBy('hour')
                ->get()->groupBy('activity_id');

            if ($blockedAll->isNotEmpty()) {
                $activities = Activity::whereIn('id', $blockedAll->keys())
                    ->with(['planning.subject', 'teachers', 'students', 'type'])
                    ->get()->keyBy('id');

                $summaryRows = $blockedAll->map(function ($slots, $actId) use ($activities) {
                    $a        = $activities->get($actId);
                    $subject  = $a?->planning?->subject;
                    $teachers = $a?->teachers->pluck('code')->filter()->implode('|') ?? '';
                    $groups   = $a?->students->pluck('name')->implode('|') ?? '';
                    return (object) [
                        'id'         => $actId,
                        'subject'    => $subject ? "[{$subject->code}] {$subject->name}" : '?',
                        'detail'     => collect([$a?->type?->name, $teachers ?: '?', $groups])->filter()->implode(' · '),
                        'slots'      => $slots->count(),
                        'blockedMap' => $slots->mapWithKeys(fn($c) => ["{$c->day}-{$c->hour}" => true])->toArray(),
                    ];
                })->values();
            }
        }

        return [
            'config'        => $config,
            'numberOfDays'  => $config?->number_of_days  ?? 0,
            'numberOfHours' => $config?->number_of_hours ?? 0,
            'dayLabels'     => $config ? $config->dayLabels()     : [],
            'slotLabels'    => $config ? $config->generateSlots() : [],
            'summaryRows'   => $summaryRows,
        ];
    }
}; ?>

<div>
    <x-header title="Activity Time Constraints" separator>
        <x-slot:actions>
            @if(count($academicYearOptions))
            <x-select wire:model.live="academicYearId" :options="$academicYearOptions"
                      placeholder="Academic Year" class="w-36" />
            @endif
            @if(count($semesterOptions))
            <x-select wire:model.live="semesterId" :options="$semesterOptions"
                      placeholder="Semester" class="w-48" />
            @endif
            @if($semesterId)
                <div class="w-px h-6 bg-base-content/20 self-center"></div>
                <x-button label="Add" icon="o-plus" class="btn-primary" wire:click="openAdd" />
            @endif
        </x-slot:actions>
    </x-header>

    @if($numberOfDays === 0 || $numberOfHours === 0)
        <x-alert icon="o-exclamation-triangle" class="alert-warning">
            Days per week and slots per day have not been configured.
            Go to <strong>Admin → Data → Basic</strong> first.
        </x-alert>
    @elseif(! $semesterId)
        <x-card>
            <p class="text-center text-base-content/40 py-8 text-sm">Select a semester to continue.</p>
        </x-card>
    @else

        <x-card>
            @if($summaryRows->isEmpty())
                <p class="text-center text-base-content/40 py-8 text-sm italic">
                    No activity time constraints set for this semester.
                    Click <strong>Add</strong> to define not-available slots.
                </p>
            @else
                <table class="table table-zebra table-sm w-full">
                    <thead>
                        <tr class="border-b border-base-200">
                            <th class="text-left py-2 font-medium text-base-content/60 w-8/12">Activity</th>
                            <th class="text-center py-2 font-medium text-base-content/60 w-2/12">Blocked Slots</th>
                            <th class="w-2/12"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($summaryRows as $row)
                            <tr class="border-b border-base-100 hover:bg-base-300/40">
                                <td class="py-2">
                                    <div class="font-medium text-xs">{{ $row->subject }}</div>
                                    <div class="text-base-content/50 text-xs">{{ $row->detail }}</div>
                                </td>
                                <td class="py-2 text-center">
                                    <div x-data="{ above: false }"
                                         @mouseenter="above = $el.getBoundingClientRect().top < window.innerHeight / 2"
                                         :class="above ? 'dropdown-bottom' : 'dropdown-top'"
                                         class="dropdown dropdown-hover dropdown-center inline-block">
                                        <div tabindex="0" role="button">
                                            <x-badge value="{{ $row->slots }}" class="badge-error badge-sm cursor-pointer" />
                                        </div>
                                        <div tabindex="0" class="dropdown-content z-50 shadow-lg bg-base-100 border border-base-300 rounded-box p-2 mb-1">
                                            <table class="border-collapse text-xs">
                                                <thead>
                                                    <tr>
                                                        <th class="pr-1 pb-0.5"></th>
                                                        @foreach($dayLabels as $dayLabel)
                                                            <th class="px-0.5 pb-0.5 font-semibold text-center text-base-content/60 w-6">
                                                                {{ substr($dayLabel, 0, 1) }}
                                                            </th>
                                                        @endforeach
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($slotLabels as $entry)
                                                        @php $h = $entry['idx']; @endphp
                                                            @if($entry['break'])
                                                                <tr>
                                                                    <td class="pr-1 py-0.5 text-right font-mono text-base-content/40 whitespace-nowrap">
                                                                        {{ explode('–', $entry['time'])[0] }}
                                                                    </td>
                                                                    @for($d = 1; $d <= $numberOfDays; $d++)
                                                                        <td class="px-0.5 py-0.5">
                                                                            <div class="w-5 h-4 rounded-sm {{ isset($row->blockedMap["{$d}-{$h}"]) ? 'bg-success/60' : 'bg-error/30' }}"></div>
                                                                        </td>
                                                                    @endfor
                                                                </tr>
                                                            @else
                                                            <tr>
                                                                <td class="pr-1 py-0.5 text-right font-mono text-base-content/40 whitespace-nowrap">
                                                                    {{ explode('–', $entry['time'])[0] }}
                                                                </td>
                                                                @for($d = 1; $d <= $numberOfDays; $d++)
                                                                    <td class="px-0.5 py-0.5">
                                                                        <div class="w-5 h-4 rounded-sm {{ isset($row->blockedMap["{$d}-{$h}"]) ? 'bg-error/70' : 'bg-base-200' }}"></div>
                                                                    </td>
                                                                @endfor
                                                            </tr>
                                                        @endif
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-2 text-right">
                                    <div class="flex justify-end gap-1">
                                        <x-button icon="o-pencil" class="btn-ghost btn-xs btn-square"
                                                  wire:click="openEdit({{ $row->id }})" tooltip="Edit" />
                                        <x-button icon="o-trash" class="btn-ghost btn-xs btn-square text-error"
                                                  wire:click="clearBlocked({{ $row->id }})"
                                                  wire:confirm="Clear all blocked slots for this activity?"
                                                  tooltip="Clear" />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-card>

    @endif

    {{-- ── Bottom-slide modal ── --}}
    <x-modal wire:model="modal"
             :title="$activityId ? 'Edit Not-Available Slots' : 'Add Not-Available Slots'"
             separator class="modal-bottom"
             box-class="!max-w-2xl mx-auto !rounded-t-2xl !mb-14">

        <input type="text" class="w-0 h-0 opacity-0 absolute pointer-events-none" autofocus />

        <div class="space-y-4">

            <x-choices label="Activity" wire:model.live="activityId" single
                       searchable :search-function="'searchActivities'"
                       :options="$activityOptions" placeholder="Select activity" />

            @if($activityId)
                {{-- Legend --}}
                <div class="flex items-center gap-4 text-sm">
                    <span class="flex items-center gap-1.5">
                        <span class="inline-block w-3 h-3 rounded bg-success/20 border border-success/40"></span>
                        Available
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="inline-block w-3 h-3 rounded bg-error/30 border border-error/50"></span>
                        Not available
                    </span>
                    <span class="text-xs text-base-content/40">Click header to toggle all</span>
                </div>

                {{-- Grid --}}
                <div class="overflow-x-auto">
                    <table class="text-sm border-collapse w-full table-fixed">
                        <colgroup>
                            <col class="w-24">
                            @for($d = 1; $d <= $numberOfDays; $d++)<col>@endfor
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="pb-1 pr-1"></th>
                                @foreach($dayLabels as $i => $dayLabel)
                                    @php $d = $i + 1; $allDayBlocked = true;
                                    for ($h = 1; $h <= $numberOfHours; $h++) {
                                        if (! isset($blocked["{$d}-{$h}"])) { $allDayBlocked = false; break; }
                                    } @endphp
                                    <th class="pb-1 px-0.5">
                                        <button wire:click="toggleDay({{ $d }})"
                                                class="w-full py-1 rounded text-xs font-semibold transition-all
                                                       {{ $allDayBlocked
                                                           ? 'bg-error/40 text-error-content hover:bg-error/60'
                                                           : 'bg-base-200 text-base-content/70 hover:bg-base-300' }}">
                                            {{ $dayLabel }}
                                        </button>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($slotLabels as $entry)
                                @php
                                        $h = $entry['idx'];
                                        $allSlotBlocked = true;
                                        for ($d = 1; $d <= $numberOfDays; $d++) {
                                            if (! isset($blocked["{$d}-{$h}"])) { $allSlotBlocked = false; break; }
                                        }
                                    @endphp
                                    @if($entry['break'])
                                        @php
                                            $allBreakAvail = collect(range(1, $numberOfDays))->every(fn($d) => isset($blocked["{$d}-0"]));
                                        @endphp
                                        <tr>
                                            <td class="pr-0.5 py-0.5">
                                                <button wire:click="toggleSlot(0)"
                                                        class="w-full py-1 rounded text-xs font-mono transition-all
                                                               {{ $allBreakAvail
                                                                   ? 'bg-success/40 text-success-content hover:bg-success/60'
                                                                   : 'bg-error/30 text-error hover:bg-error/50' }}">
                                                    {{ $entry['time'] }}
                                                </button>
                                            </td>
                                            @for($d = 1; $d <= $numberOfDays; $d++)
                                                @php $breakAvail = isset($blocked["{$d}-0"]); @endphp
                                                <td class="px-0.5 py-0.5">
                                                    <button wire:click="toggle({{ $d }}, 0)"
                                                            wire:loading.attr="disabled"
                                                            class="w-full h-7 rounded transition-all
                                                                   {{ $breakAvail
                                                                       ? 'bg-success/20 border border-success/30 hover:bg-success/40'
                                                                       : 'bg-error/30 border border-error/40 hover:bg-error/50' }}">
                                                    </button>
                                                </td>
                                            @endfor
                                        </tr>
                                    @else
                                    @php
                                        $allSlotBlocked = true;
                                        for ($d = 1; $d <= $numberOfDays; $d++) {
                                            if (! isset($blocked["{$d}-{$h}"])) { $allSlotBlocked = false; break; }
                                        }
                                    @endphp
                                    <tr>
                                        <td class="pr-0.5 py-0.5">
                                            <button wire:click="toggleSlot({{ $h }})"
                                                    class="w-full py-1 rounded text-xs font-mono transition-all
                                                           {{ $allSlotBlocked
                                                               ? 'bg-error/40 text-error-content hover:bg-error/60'
                                                               : 'bg-base-200 text-base-content/60 hover:bg-base-300' }}">
                                                {{ $entry['time'] }}
                                            </button>
                                        </td>
                                        @for($d = 1; $d <= $numberOfDays; $d++)
                                            @php $isBlocked = isset($blocked["{$d}-{$h}"]); @endphp
                                            <td class="px-0.5 py-0.5">
                                                <button wire:click="toggle({{ $d }}, {{ $h }})"
                                                        wire:loading.attr="disabled"
                                                        class="w-full h-7 rounded transition-all
                                                               {{ $isBlocked
                                                                   ? 'bg-error/30 border border-error/40 hover:bg-error/50'
                                                                   : 'bg-success/20 border border-success/30 hover:bg-success/40' }}">
                                                </button>
                                            </td>
                                        @endfor
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-between items-center pt-1">
                    <x-button label="Clear All" icon="o-x-mark" class="btn-ghost btn-sm text-error"
                              wire:click="clearBlocked({{ $activityId }})"
                              wire:confirm="Clear all blocked slots for this activity?" />
                </div>
            @endif

        </div>

    </x-modal>

    <x-toast />
</div>

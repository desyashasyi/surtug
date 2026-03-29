<?php

use App\Livewire\Concerns\HasProgramSemester;
use App\Models\FetNet\ActivityTag;
use App\Models\FetNet\Client;
use App\Models\FetNet\Program;
use App\Models\FetNet\Student;
use App\Models\FetNet\StudentConstraint;
use App\Models\FetNet\StudentTimeConstraint;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;

new #[Layout('layouts.program')] class extends Component
{
    use Toast, HasProgramSemester;

    public ?string $constraintType = null;

    public bool    $modal            = false;
    public ?int    $editConstraintId = null;

    public string  $target           = 'student';  // 'student' | 'all'
    public ?int    $studentId        = null;
    public ?int    $constraintValue  = null;
    public float   $constraintWeight = 100.0;

    public array   $blocked          = [];
    public array   $studentOptions   = [];

    // ── Constraint labels ──────────────────────────────────────────────────────

    private static function constraintLabels(): array
    {
        return [
            'not_available'          => 'Not Available Times',
            'max_days_per_week'      => 'Max Days per Week',
            'min_days_per_week'      => 'Min Days per Week',
            'max_hours_daily'        => 'Max Hours Daily',
            'min_hours_daily'        => 'Min Hours Daily',
            'max_span_per_day'       => 'Max Span per Day',
            'max_hours_continuously' => 'Max Hours Continuously',
            'max_gaps_per_week'      => 'Max Gaps per Week',
            'max_gaps_per_day'       => 'Max Gaps per Day',
            'min_resting_hours'      => 'Min Resting Hours',
        ];
    }

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

    private function allStudents(): \Illuminate\Support\Collection
    {
        $program = $this->program();
        if (! $program) return collect();

        return Student::where('program_id', $program->id)
            ->with(['parent.parent'])
            ->orderBy('name')
            ->get();
    }

    // ── Lifecycle ──────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $program = $this->program();
        if ($program) $this->mountSemesterContext($program->client_id);
        $this->searchStudents();
    }

    public function searchStudents(string $value = ''): void
    {
        $program = $this->program();
        if (! $program) { $this->studentOptions = []; return; }

        $this->studentOptions = Student::where('program_id', $program->id)
            ->where(fn($q) => $q
                ->where('name', 'ilike', "%{$value}%")
                ->orWhere('id', $this->studentId))
            ->orderBy('name')->limit(20)->get()
            ->map(function ($s) {
                $label = $s->name;
                if ($s->parent) {
                    $label = ($s->parent->parent ? $s->parent->parent->name . ' / ' : '')
                           . $s->parent->name . ' / ' . $s->name;
                }
                return ['id' => $s->id, 'name' => $label];
            })->values()->toArray();
    }

    public function updatedTarget(): void
    {
        if ($this->target === 'all') {
            $this->studentId = null;
            if ($this->constraintType === 'not_available') {
                $this->constraintType = null;
            }
        }
    }

    public function updatedStudentId(): void
    {
        if ($this->constraintType === 'not_available') {
            $this->loadBlocked();
            $this->loadNotAvailableWeight();
        }
    }

    // ── Open modal ─────────────────────────────────────────────────────────────

    public function openAdd(): void
    {
        $this->reset(['editConstraintId', 'constraintValue', 'blocked']);
        $this->constraintWeight = 100.0;

        if ($this->target === 'student') {
            $first = $this->allStudents()->first();
            $this->studentId = $first?->id;
            $this->searchStudents();

            if ($this->constraintType === 'not_available' && $this->studentId) {
                $this->loadBlocked();
                $this->loadNotAvailableWeight();
            }
        } else {
            $this->studentId = null;
        }

        $this->modal = true;
    }

    public function openEdit(int $id): void
    {
        $row = StudentConstraint::findOrFail($id);

        $this->editConstraintId = $id;
        $this->target           = $row->student_id ? 'student' : 'all';
        $this->studentId        = $row->student_id;
        $this->constraintValue  = $row->value;
        $this->constraintWeight = (float) $row->weight;
        $this->searchStudents();
        $this->modal = true;
    }

    public function openEditNotAvailable(int $studentId): void
    {
        $this->reset(['editConstraintId', 'blocked']);
        $this->studentId = $studentId;
        $this->searchStudents();
        $this->loadBlocked();
        $this->loadNotAvailableWeight();
        $this->modal = true;
    }

    // ── Not-available grid ─────────────────────────────────────────────────────

    private function loadBlocked(): void
    {
        if (! $this->studentId) { $this->blocked = []; return; }

        $this->blocked = StudentTimeConstraint::where('student_id', $this->studentId)
            ->get()
            ->mapWithKeys(fn($c) => ["{$c->day}-{$c->hour}" => true])
            ->toArray();
    }

    private function loadNotAvailableWeight(): void
    {
        $program = $this->program();
        if (! $program || ! $this->studentId) { $this->constraintWeight = 100.0; return; }

        $row = StudentConstraint::where('program_id', $program->id)
            ->where('student_id', $this->studentId)
            ->where('constraint_type', 'not_available')
            ->first();

        $this->constraintWeight = $row ? (float) $row->weight : 100.0;
    }

    public function toggle(int $day, int $hour): void
    {
        if (! $this->studentId) return;
        $key = "{$day}-{$hour}";

        if (isset($this->blocked[$key])) {
            StudentTimeConstraint::where('student_id', $this->studentId)
                ->where('day', $day)->where('hour', $hour)->delete();
            unset($this->blocked[$key]);
        } else {
            StudentTimeConstraint::firstOrCreate([
                'student_id' => $this->studentId, 'day' => $day, 'hour' => $hour,
            ]);
            $this->blocked[$key] = true;
        }
    }

    public function toggleDay(int $day): void
    {
        if (! $this->studentId) return;
        $hours = range(1, $this->config()?->number_of_hours ?? 0);
        $allBlocked = collect($hours)->every(fn($h) => isset($this->blocked["{$day}-{$h}"]));

        if ($allBlocked) {
            StudentTimeConstraint::where('student_id', $this->studentId)->where('day', $day)->delete();
            foreach ($hours as $h) unset($this->blocked["{$day}-{$h}"]);
        } else {
            foreach ($hours as $h) {
                $key = "{$day}-{$h}";
                if (! isset($this->blocked[$key])) {
                    StudentTimeConstraint::firstOrCreate([
                        'student_id' => $this->studentId, 'day' => $day, 'hour' => $h,
                    ]);
                    $this->blocked[$key] = true;
                }
            }
        }
    }

    public function toggleSlot(int $hour): void
    {
        if (! $this->studentId) return;
        $total      = $this->config()?->number_of_days ?? 0;
        $allBlocked = collect(range(1, $total))->every(fn($d) => isset($this->blocked["{$d}-{$hour}"]));

        if ($allBlocked) {
            StudentTimeConstraint::where('student_id', $this->studentId)->where('hour', $hour)->delete();
            for ($d = 1; $d <= $total; $d++) unset($this->blocked["{$d}-{$hour}"]);
        } else {
            for ($d = 1; $d <= $total; $d++) {
                $key = "{$d}-{$hour}";
                if (! isset($this->blocked[$key])) {
                    StudentTimeConstraint::firstOrCreate([
                        'student_id' => $this->studentId, 'day' => $d, 'hour' => $hour,
                    ]);
                    $this->blocked[$key] = true;
                }
            }
        }
    }

    public function saveNotAvailableWeight(): void
    {
        $this->validate(['constraintWeight' => 'required|numeric|min:0|max:100']);
        $program = $this->program();
        if (! $program || ! $this->studentId) return;

        StudentConstraint::updateOrCreate(
            ['program_id' => $program->id, 'student_id' => $this->studentId, 'constraint_type' => 'not_available'],
            ['weight' => $this->constraintWeight, 'value' => 0]
        );
        $this->success('Weight saved.', position: 'toast-top toast-center');
    }

    // ── Numeric constraint ─────────────────────────────────────────────────────

    public function saveConstraint(): void
    {
        $this->validate([
            'constraintValue'  => 'required|integer|min:0|max:999',
            'constraintWeight' => 'required|numeric|min:0|max:100',
        ]);

        $program   = $this->program();
        $studentId = $this->target === 'student' ? $this->studentId : null;

        if ($this->editConstraintId) {
            StudentConstraint::findOrFail($this->editConstraintId)->update([
                'student_id' => $studentId,
                'value'      => $this->constraintValue,
                'weight'     => $this->constraintWeight,
            ]);
        } else {
            StudentConstraint::updateOrCreate(
                ['program_id' => $program->id, 'student_id' => $studentId, 'constraint_type' => $this->constraintType],
                ['value' => $this->constraintValue, 'weight' => $this->constraintWeight]
            );
        }

        $this->modal = false;
        $this->success('Constraint saved.', position: 'toast-top toast-center');
    }

    public function deleteConstraintById(int $id): void
    {
        StudentConstraint::find($id)?->delete();
        $this->warning('Constraint removed.', position: 'toast-top toast-center');
    }

    public function clearNotAvailable(int $studentId): void
    {
        StudentTimeConstraint::where('student_id', $studentId)->delete();
        StudentConstraint::where('student_id', $studentId)
            ->where('constraint_type', 'not_available')->delete();
        $this->warning('Not available periods cleared.', position: 'toast-top toast-center');
    }

    // ── with() ─────────────────────────────────────────────────────────────────

    public function with(): array
    {
        $config  = $this->config();
        $program = $this->program();

        $allStudents = $this->allStudents()->keyBy('id');

        // Build display label map
        $labelMap = $allStudents->map(function ($s) {
            $label = $s->name;
            if ($s->parent) {
                $label = ($s->parent->parent ? $s->parent->parent->name . ' / ' : '')
                       . $s->parent->name . ' / ' . $s->name;
            }
            return $label;
        });

        $constraintRows   = collect();
        $notAvailableRows = collect();

        if ($program && $this->constraintType && $this->constraintType !== 'not_available') {
            $rows = StudentConstraint::where('program_id', $program->id)
                ->where('constraint_type', $this->constraintType)
                ->get();

            $constraintRows = $rows->map(fn($row) => (object) [
                'student' => $row->student_id ? ($labelMap->get($row->student_id) ?? '?') : '(All groups)',
                'value'   => $row->value,
                'weight'  => $row->weight,
                'id'      => $row->id,
            ]);
        }

        if ($program && $this->constraintType === 'not_available') {
            $dayLabels  = $config ? $config->dayLabels()     : [];
            $slotLabels = $config ? $config->generateSlots() : [];
            $slotMap    = collect($slotLabels)->filter(fn($s) => ! $s['break'])->keyBy('idx');

            $blockedAll = StudentTimeConstraint::whereIn('student_id', $allStudents->keys())
                ->orderBy('day')->orderBy('hour')
                ->get()->groupBy('student_id');

            $weights = StudentConstraint::where('program_id', $program->id)
                ->where('constraint_type', 'not_available')
                ->whereNotNull('student_id')
                ->get()->keyBy('student_id');

            $notAvailableRows = $allStudents->map(function ($s) use ($labelMap, $blockedAll, $weights) {
                $blocked = $blockedAll->get($s->id, collect());
                return (object) [
                    'student'    => $labelMap->get($s->id) ?? $s->name,
                    'slots'      => $blocked->count(),
                    'blockedMap' => $blocked->mapWithKeys(fn($c) => ["{$c->day}-{$c->hour}" => true])->toArray(),
                    'weight'     => isset($weights[$s->id]) ? (float) $weights[$s->id]->weight : 100.0,
                    'id'         => $s->id,
                ];
            })->values();
        }

        return [
            'config'           => $config,
            'numberOfDays'     => $config?->number_of_days  ?? 0,
            'numberOfHours'    => $config?->number_of_hours ?? 0,
            'constraintLabels' => self::constraintLabels(),
            'dayLabels'        => $config ? $config->dayLabels()     : [],
            'slotLabels'       => $config ? $config->generateSlots() : [],
            'constraintRows'   => $constraintRows,
            'notAvailableRows' => $notAvailableRows,
        ];
    }
}; ?>

<div>
    <x-header title="Student Time Constraints" separator>
        <x-slot:actions>
            @if(count($academicYearOptions))
            <x-select wire:model.live="academicYearId" :options="$academicYearOptions"
                      placeholder="Academic Year" class="w-36" />
            @endif
            @if(count($semesterOptions))
            <x-select wire:model.live="semesterId" :options="$semesterOptions"
                      placeholder="Semester" class="w-48" />
            @endif
            <div class="w-px h-6 bg-base-content/20 self-center"></div>
            <x-choices wire:model.live="target" single
                       :options="[['id'=>'student','name'=>'A group'],['id'=>'all','name'=>'All groups']]"
                       class="w-36" />
            <select wire:model.live="constraintType" class="select select-bordered text-sm w-72">
                <option value="">Select constraint</option>

                @if($target === 'student')
                <optgroup label="─── Availability ───">
                    <option value="not_available">Not Available Times</option>
                </optgroup>
                @endif

                <optgroup label="─── Days ───">
                    <option value="max_days_per_week">Max Days per Week</option>
                    <option value="min_days_per_week">Min Days per Week</option>
                </optgroup>

                <optgroup label="─── Daily Hours ───">
                    <option value="max_hours_daily">Max Hours Daily</option>
                    <option value="min_hours_daily">Min Hours Daily</option>
                    <option value="max_span_per_day">Max Span per Day</option>
                </optgroup>

                <optgroup label="─── Continuous ───">
                    <option value="max_hours_continuously">Max Hours Continuously</option>
                </optgroup>

                <optgroup label="─── Gaps & Rest ───">
                    <option value="max_gaps_per_week">Max Gaps per Week</option>
                    <option value="max_gaps_per_day">Max Gaps per Day</option>
                    <option value="min_resting_hours">Min Resting Hours</option>
                </optgroup>
            </select>
            @if($constraintType && $constraintType !== 'not_available')
                <x-button label="Add" icon="o-plus" class="btn-primary" wire:click="openAdd" />
            @endif
        </x-slot:actions>
    </x-header>

    @if($numberOfDays === 0 || $numberOfHours === 0)
        <x-alert icon="o-exclamation-triangle" class="alert-warning">
            Days per week and slots per day have not been configured.
            Go to <strong>Admin → Data → Basic</strong> first.
        </x-alert>
    @elseif($constraintType)

        {{-- ── Summary table: numeric constraints ── --}}
        @if($constraintType !== 'not_available')
            <x-card>
                @if($constraintRows->isEmpty())
                    <p class="text-center text-base-content/40 py-6 text-sm italic">
                        No constraints set yet. Click <strong>Add</strong> to create one.
                    </p>
                @else
                    <table class="table table-zebra table-sm w-full">
                        <thead>
                            <tr class="border-b border-base-200">
                                <th class="text-left py-2 font-medium text-base-content/60 w-6/12">Group</th>
                                <th class="text-center py-2 font-medium text-base-content/60 w-2/12">Value</th>
                                <th class="text-center py-2 font-medium text-base-content/60 w-2/12">Weight %</th>
                                <th class="w-2/12"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($constraintRows as $row)
                                <tr class="border-b border-base-100 hover:bg-base-300/40">
                                    <td class="py-2">{{ $row->student }}</td>
                                    <td class="py-2 text-center">
                                        <x-badge value="{{ $row->value }}" class="badge-neutral badge-sm" />
                                    </td>
                                    <td class="py-2 text-center text-xs
                                               {{ $row->weight < 100 ? 'text-warning font-semibold' : 'text-base-content/40' }}">
                                        {{ $row->weight }}%
                                    </td>
                                    <td class="py-2">
                                        <div class="flex justify-end gap-1">
                                            <x-button icon="o-pencil" class="btn-ghost btn-xs btn-square"
                                                      wire:click="openEdit({{ $row->id }})" tooltip="Edit" />
                                            <x-button icon="o-trash" class="btn-ghost btn-xs btn-square text-error"
                                                      wire:click="deleteConstraintById({{ $row->id }})"
                                                      wire:confirm="Remove this constraint?" tooltip="Delete" />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-card>
        @endif

        {{-- ── Summary table: not available ── --}}
        @if($constraintType === 'not_available')
            <x-card>
                @if($notAvailableRows->isEmpty())
                    <p class="text-center text-base-content/40 py-6 text-sm italic">
                        No not-available constraints set yet.
                    </p>
                @else
                    <table class="table table-zebra table-sm w-full">
                        <thead>
                            <tr class="border-b border-base-200">
                                <th class="text-left py-2 font-medium text-base-content/60 w-7/12">Group</th>
                                <th class="text-center py-2 font-medium text-base-content/60 w-2/12">Blocked Slots</th>
                                <th class="text-center py-2 font-medium text-base-content/60 w-2/12">Weight %</th>
                                <th class="w-auto"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($notAvailableRows as $row)
                                <tr class="border-b border-base-100 hover:bg-base-300/40">
                                    <td class="py-2">{{ $row->student }}</td>
                                    <td class="py-2 text-center">
                                        @if($row->slots > 0)
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
                                        @else
                                            <span class="text-base-content/30 text-xs">—</span>
                                        @endif
                                    </td>
                                    <td class="py-2 text-center text-xs
                                               {{ $row->slots > 0 && $row->weight < 100 ? 'text-warning font-semibold' : 'text-base-content/30' }}">
                                        {{ $row->slots > 0 ? $row->weight.'%' : '—' }}
                                    </td>
                                    <td class="py-2 text-right">
                                        <x-button icon="o-pencil" class="btn-ghost btn-xs btn-square"
                                                  wire:click="openEditNotAvailable({{ $row->id }})" tooltip="Edit" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-card>
        @endif

    @endif

    {{-- ── Bottom slide modal ── --}}
    <x-modal wire:model="modal"
             :title="($editConstraintId || ($constraintType === 'not_available' && $studentId)) ? 'Edit Constraint' : 'Add Constraint'"
             separator class="modal-bottom"
             box-class="!max-w-2xl mx-auto !rounded-t-2xl !mb-14">

        <input type="text" class="w-0 h-0 opacity-0 absolute pointer-events-none" autofocus />

        @if($constraintType === 'not_available')

            <div class="space-y-4">
                @if($target === 'student')
                    <x-choices label="Student Group" wire:model.live="studentId" single
                               searchable :search-function="'searchStudents'"
                               :options="$studentOptions" placeholder="Select group"
                               class="w-72" />
                @endif

                @if($studentId && $target === 'student')
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

                    <div class="pt-1 space-y-1">
                        <p class="text-sm font-semibold">Weight (%) <span class="font-normal text-xs text-base-content/40">— 100 = hard constraint</span></p>
                        <div class="flex items-center gap-2">
                            <input wire:model="constraintWeight" type="number" min="0" max="100" step="0.01"
                                   class="input input-bordered input-sm w-28" />
                            <x-button label="Save Weight" icon="o-check-circle"
                                      wire:click="saveNotAvailableWeight"
                                      class="btn-sm btn-primary" spinner="saveNotAvailableWeight" />
                        </div>
                    </div>
                @endif
            </div>

        @else

            @php
                $label = $constraintLabels[$constraintType] ?? $constraintType;

                $hint = match($constraintType) {
                    'max_days_per_week'      => 'Max days per week (0–' . $numberOfDays . ')',
                    'min_days_per_week'      => 'Min days per week (0–' . $numberOfDays . ')',
                    'max_hours_daily'        => 'Max slots per day (0–' . $numberOfHours . ')',
                    'min_hours_daily'        => 'Min slots per day (0–' . $numberOfHours . ')',
                    'max_span_per_day'       => 'Max span (first–last activity) per day',
                    'max_hours_continuously' => 'Max consecutive slots without a break',
                    'max_gaps_per_week'      => 'Max unused slots between lessons per week',
                    'max_gaps_per_day'       => 'Max unused slots between lessons per day',
                    'min_resting_hours'      => 'Min slots between last lesson of one day and first of next',
                    default                  => '',
                };
            @endphp

            <x-form wire:submit="saveConstraint" class="space-y-4">

                @if($target === 'student')
                    <x-choices label="Student Group" wire:model.live="studentId" single
                               searchable :search-function="'searchStudents'"
                               :options="$studentOptions" placeholder="Select group"
                               class="w-72" />
                @endif

                <div class="flex gap-3 items-start">
                    <div class="flex-1">
                        <x-input label="{{ $label }}" wire:model="constraintValue"
                                 type="number" min="0" max="999"
                                 hint="{{ $hint }}" placeholder="Enter value" />
                    </div>
                    <x-input label="Weight (%)" wire:model="constraintWeight"
                             type="number" min="0" max="100" step="0.01" class="w-28"
                             hint="100 = hard" />
                </div>

                <x-slot:actions>
                    <x-button label="Cancel" icon="o-x-circle" wire:click="$set('modal', false)" />
                    <x-button label="Save" icon="o-check-circle" type="submit"
                              class="btn-primary" spinner="saveConstraint" />
                </x-slot:actions>

            </x-form>

        @endif

    </x-modal>

    <x-toast />
</div>

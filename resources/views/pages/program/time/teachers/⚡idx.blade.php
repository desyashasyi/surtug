<?php

use App\Livewire\Concerns\HasProgramSemester;
use App\Models\FetNet\ActivityTag;
use App\Models\FetNet\Client;
use App\Models\FetNet\Cluster;
use App\Models\FetNet\Program;
use App\Models\FetNet\Teacher;
use App\Models\FetNet\TeacherConstraint;
use App\Models\FetNet\TeacherTimeConstraint;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;

new #[Layout('layouts.program')] class extends Component
{
    use Toast, HasProgramSemester;

    // Page: selected constraint type
    public ?string $constraintType = null;

    // Modal state
    public bool    $modal             = false;
    public ?int    $editConstraintId  = null;  // null = add new

    // Modal form fields
    public string  $target           = 'teacher';   // 'teacher' | 'all'
    public ?int    $teacherId        = null;
    public ?int    $constraintValue  = null;
    public float   $constraintWeight = 100.0;
    public ?int    $tagId            = null;
    public ?int    $tag2Id           = null;
    public ?int    $intervalStart    = null;
    public ?int    $intervalEnd      = null;

    // Not-available grid state (used inside modal)
    public array   $blocked          = [];

    public array   $teacherOptions   = [];

    // ── Constraint type groups ─────────────────────────────────────────────────

    private static function tagConstraints(): array
    {
        return ['max_hours_daily_tag', 'min_hours_daily_tag', 'max_hours_continuously_tag'];
    }

    private static function dualTagConstraints(): array
    {
        return ['min_gaps_between_activity_tags'];
    }

    private static function constraintLabels(): array
    {
        return [
            'not_available'                  => 'Not Available Times',
            'max_days_per_week'              => 'Max Days per Week',
            'min_days_per_week'              => 'Min Days per Week',
            'hourly_interval_max_days'       => 'Working in Hourly Interval Max Days per Week',
            'max_hours_daily'                => 'Max Hours Daily',
            'min_hours_daily'                => 'Min Hours Daily',
            'max_hours_daily_tag'            => 'Max Hours Daily with Activity Tag',
            'min_hours_daily_tag'            => 'Min Hours Daily with Activity Tag',
            'max_span_per_day'               => 'Max Span per Day',
            'max_hours_continuously'         => 'Max Hours Continuously',
            'max_hours_continuously_tag'     => 'Max Hours Continuously with Activity Tag',
            'max_gaps_per_week'              => 'Max Gaps per Week',
            'max_gaps_per_day'               => 'Max Gaps per Day',
            'min_gaps_between_activity_tags' => 'Min Gaps Between Activity Tags',
            'min_resting_hours'              => 'Min Resting Hours',
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

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

    private function clusterTeachers(): \Illuminate\Support\Collection
    {
        $program = $this->program();
        if (! $program) return collect();

        $cluster = Cluster::where('program_id', $program->id)->first();
        if (! $cluster) {
            return Teacher::where('program_id', $program->id)->orderBy('name')->get();
        }

        $ids = Cluster::where('cluster_base_id', $cluster->cluster_base_id)->pluck('program_id');
        return Teacher::whereIn('program_id', $ids)->orderBy('name')->get();
    }

    private function guestTeachers(): \Illuminate\Support\Collection
    {
        $program = $this->program();
        if (! $program) return collect();
        return $program->guestTeachers()->orderBy('name')->get();
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $program = $this->program();
        if ($program) $this->mountSemesterContext($program->client_id);
        $this->searchTeachers();
    }

    public function searchTeachers(string $value = ''): void
    {
        $program = $this->program();
        if (! $program) { $this->teacherOptions = []; return; }

        $cluster    = Cluster::where('program_id', $program->id)->first();
        $clusterIds = $cluster
            ? Cluster::where('cluster_base_id', $cluster->cluster_base_id)->pluck('program_id')->toArray()
            : [$program->id];
        $guestIds   = $program->guestTeachers()->pluck('teacher_id')->toArray();

        $options = Teacher::whereIn('program_id', $clusterIds)
            ->where(fn($q) => $q
                ->where('name', 'ilike', "%{$value}%")
                ->orWhere('code', 'ilike', "%{$value}%")
                ->orWhere('id', $this->teacherId))
            ->orderBy('name')->limit(15)->get()
            ->map(fn($t) => ['id' => $t->id, 'name' => "[{$t->code}] {$t->name}"])
            ->values()->toArray();

        if (! empty($guestIds)) {
            $guests = Teacher::whereIn('id', $guestIds)
                ->where(fn($q) => $q
                    ->where('name', 'ilike', "%{$value}%")
                    ->orWhere('code', 'ilike', "%{$value}%")
                    ->orWhere('id', $this->teacherId))
                ->orderBy('name')->limit(15)->get()
                ->map(fn($t) => ['id' => $t->id, 'name' => "[{$t->code}] {$t->name} (guest)"])
                ->toArray();
            $options = array_merge($options, $guests);
        }

        $this->teacherOptions = $options;
    }

    public function updatedConstraintType(): void
    {
        // nothing — table updates reactively via with()
    }

    public function updatedTarget(): void
    {
        if ($this->target === 'all') {
            $this->teacherId = null;
            // not_available only applies to individual teachers
            if ($this->constraintType === 'not_available') {
                $this->constraintType = null;
            }
        }
    }

    // When teacher changes inside not_available modal, reload grid
    public function updatedTeacherId(): void
    {
        if ($this->constraintType === 'not_available') {
            $this->loadBlocked();
            $this->loadNotAvailableWeight();
        }
    }

    // ── Open modal ────────────────────────────────────────────────────────────

    public function openAdd(): void
    {
        $this->reset(['editConstraintId', 'constraintValue', 'tagId', 'tag2Id',
                      'intervalStart', 'intervalEnd', 'blocked']);
        $this->constraintWeight = 100.0;
        // Preserve current target (teacher/all) from the page selection

        if ($this->target === 'teacher') {
            $teachers = $this->clusterTeachers();
            $this->teacherId = $teachers->first()?->id;
            $this->searchTeachers();

            if ($this->constraintType === 'not_available' && $this->teacherId) {
                $this->loadBlocked();
                $this->loadNotAvailableWeight();
            }
        } else {
            $this->teacherId = null;
        }

        $this->modal = true;
    }

    public function openEdit(int $id): void
    {
        $row = TeacherConstraint::findOrFail($id);

        $this->editConstraintId  = $id;
        $this->target            = $row->teacher_id ? 'teacher' : 'all';
        $this->teacherId         = $row->teacher_id;
        $this->constraintValue   = $row->value;
        $this->constraintWeight  = (float) $row->weight;
        $this->tagId             = $row->tag_id;
        $this->tag2Id            = $row->tag2_id;
        $this->intervalStart     = $row->interval_start;
        $this->intervalEnd       = $row->interval_end;
        $this->searchTeachers();
        $this->modal             = true;
    }

    public function openEditNotAvailable(int $teacherId): void
    {
        $this->reset(['editConstraintId', 'blocked']);
        $this->teacherId = $teacherId;
        $this->searchTeachers();
        $this->loadBlocked();
        $this->loadNotAvailableWeight();
        $this->modal = true;
    }

    // ── Not-available grid ────────────────────────────────────────────────────

    private function loadBlocked(): void
    {
        if (! $this->teacherId) { $this->blocked = []; return; }

        $this->blocked = TeacherTimeConstraint::where('teacher_id', $this->teacherId)
            ->get()
            ->mapWithKeys(fn($c) => ["{$c->day}-{$c->hour}" => true])
            ->toArray();
    }

    private function loadNotAvailableWeight(): void
    {
        $program = $this->program();
        if (! $program || ! $this->teacherId) { $this->constraintWeight = 100.0; return; }

        $row = TeacherConstraint::where('program_id', $program->id)
            ->where('teacher_id', $this->teacherId)
            ->where('constraint_type', 'not_available')
            ->first();

        $this->constraintWeight = $row ? (float) $row->weight : 100.0;
    }

    public function toggle(int $day, int $hour): void
    {
        if (! $this->teacherId) return;
        $key = "{$day}-{$hour}";

        if (isset($this->blocked[$key])) {
            TeacherTimeConstraint::where('teacher_id', $this->teacherId)
                ->where('day', $day)->where('hour', $hour)->delete();
            unset($this->blocked[$key]);
        } else {
            TeacherTimeConstraint::firstOrCreate([
                'teacher_id' => $this->teacherId, 'day' => $day, 'hour' => $hour,
            ]);
            $this->blocked[$key] = true;
        }
    }

    public function toggleDay(int $day): void
    {
        if (! $this->teacherId) return;
        $config = $this->config();
        $hours = range(1, $config?->number_of_hours ?? 0);

        $allBlocked = collect($hours)->every(fn($h) => isset($this->blocked["{$day}-{$h}"]));

        if ($allBlocked) {
            TeacherTimeConstraint::where('teacher_id', $this->teacherId)->where('day', $day)->delete();
            foreach ($hours as $h) unset($this->blocked["{$day}-{$h}"]);
        } else {
            foreach ($hours as $h) {
                $key = "{$day}-{$h}";
                if (! isset($this->blocked[$key])) {
                    TeacherTimeConstraint::firstOrCreate([
                        'teacher_id' => $this->teacherId, 'day' => $day, 'hour' => $h,
                    ]);
                    $this->blocked[$key] = true;
                }
            }
        }
    }

    public function toggleSlot(int $hour): void
    {
        if (! $this->teacherId) return;
        $total      = $this->config()?->number_of_days ?? 0;
        $allBlocked = collect(range(1, $total))->every(fn($d) => isset($this->blocked["{$d}-{$hour}"]));

        if ($allBlocked) {
            TeacherTimeConstraint::where('teacher_id', $this->teacherId)->where('hour', $hour)->delete();
            for ($d = 1; $d <= $total; $d++) unset($this->blocked["{$d}-{$hour}"]);
        } else {
            for ($d = 1; $d <= $total; $d++) {
                $key = "{$d}-{$hour}";
                if (! isset($this->blocked[$key])) {
                    TeacherTimeConstraint::firstOrCreate([
                        'teacher_id' => $this->teacherId, 'day' => $d, 'hour' => $hour,
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
        if (! $program || ! $this->teacherId) return;

        TeacherConstraint::updateOrCreate(
            ['program_id' => $program->id, 'teacher_id' => $this->teacherId, 'constraint_type' => 'not_available'],
            ['weight' => $this->constraintWeight, 'value' => 0]
        );
        $this->success('Weight saved.', position: 'toast-top toast-center');
    }

    // ── Numeric constraint ────────────────────────────────────────────────────

    public function saveConstraint(): void
    {
        $this->validate([
            'constraintValue'  => 'required|integer|min:0|max:999',
            'constraintWeight' => 'required|numeric|min:0|max:100',
        ]);

        $program   = $this->program();
        $teacherId = $this->target === 'teacher' ? $this->teacherId : null;

        $isTag     = in_array($this->constraintType, self::tagConstraints());
        $isDualTag = in_array($this->constraintType, self::dualTagConstraints());
        $isInterval = $this->constraintType === 'hourly_interval_max_days';

        if ($this->editConstraintId) {
            TeacherConstraint::findOrFail($this->editConstraintId)->update([
                'teacher_id'     => $teacherId,
                'value'          => $this->constraintValue,
                'weight'         => $this->constraintWeight,
                'tag_id'         => ($isTag || $isDualTag) ? $this->tagId  : null,
                'tag2_id'        => $isDualTag             ? $this->tag2Id : null,
                'interval_start' => $isInterval            ? $this->intervalStart : null,
                'interval_end'   => $isInterval            ? $this->intervalEnd   : null,
            ]);
        } else {
            $key = [
                'program_id'      => $program->id,
                'teacher_id'      => $teacherId,
                'constraint_type' => $this->constraintType,
            ];
            if ($isTag || $isDualTag) $key['tag_id']  = $this->tagId;
            if ($isDualTag)           $key['tag2_id'] = $this->tag2Id;
            if ($isInterval) {
                $key['interval_start'] = $this->intervalStart;
                $key['interval_end']   = $this->intervalEnd;
            }
            TeacherConstraint::updateOrCreate($key, [
                'value'  => $this->constraintValue,
                'weight' => $this->constraintWeight,
            ]);
        }

        $this->modal = false;
        $this->success('Constraint saved.', position: 'toast-top toast-center');
    }

    public function deleteConstraintById(int $id): void
    {
        TeacherConstraint::find($id)?->delete();
        $this->warning('Constraint removed.', position: 'toast-top toast-center');
    }

    public function clearNotAvailable(int $teacherId): void
    {
        TeacherTimeConstraint::where('teacher_id', $teacherId)->delete();
        TeacherConstraint::where('teacher_id', $teacherId)
            ->where('constraint_type', 'not_available')->delete();
        $this->warning('Not available periods cleared.', position: 'toast-top toast-center');
    }

    // ── with() ────────────────────────────────────────────────────────────────

    public function with(): array
    {
        $config   = $this->config();
        $cluster  = $this->clusterTeachers();
        $guests   = $this->guestTeachers();
        $program  = $this->program();

        $allTeachers = $cluster->merge($guests)->keyBy('id');

        $tagOptions = $program
            ? ActivityTag::where('program_id', $program->id)->orderBy('name')->get()
                ->map(fn($t) => ['id' => $t->id, 'name' => $t->name])
                ->values()->toArray()
            : [];

        $tagMap = collect($tagOptions)->keyBy('id');

        // Summary: numeric constraints for selected type
        $constraintRows = collect();
        if ($program && $this->constraintType && $this->constraintType !== 'not_available') {
            $rows = TeacherConstraint::where('program_id', $program->id)
                ->where('constraint_type', $this->constraintType)
                ->get();

            $constraintRows = $rows->map(function ($row) use ($allTeachers, $tagMap) {
                $teacher = $row->teacher_id ? $allTeachers->get($row->teacher_id) : null;
                $tag     = $row->tag_id  ? ($tagMap->get($row->tag_id)['name']  ?? '?') : null;
                $tag2    = $row->tag2_id ? ($tagMap->get($row->tag2_id)['name'] ?? '?') : null;
                $interval = ($row->interval_start && $row->interval_end)
                    ? "slot {$row->interval_start}–{$row->interval_end}" : null;

                return (object) [
                    'teacher' => $teacher ? "[{$teacher->code}] {$teacher->name}" : '(All teachers)',
                    'params'  => collect([$tag, $tag2, $interval])->filter()->implode(', '),
                    'value'   => $row->value,
                    'weight'  => $row->weight,
                    'id'      => $row->id,
                ];
            });
        }

        // Summary: not_available — teachers with blocked slots
        $notAvailableRows = collect();
        if ($program && $this->constraintType === 'not_available') {
            $blockedAll = TeacherTimeConstraint::whereIn('teacher_id', $allTeachers->keys())
                ->get()->groupBy('teacher_id');

            $weights = TeacherConstraint::where('program_id', $program->id)
                ->where('constraint_type', 'not_available')
                ->whereNotNull('teacher_id')
                ->get()->keyBy('teacher_id');

            $notAvailableRows = $allTeachers
                ->map(fn($t) => (object) [
                    'teacher'    => "[{$t->code}] {$t->name}",
                    'slots'      => $blockedAll->get($t->id, collect())->count(),
                    'blockedMap' => $blockedAll->get($t->id, collect())
                        ->mapWithKeys(fn($c) => ["{$c->day}-{$c->hour}" => true])->toArray(),
                    'weight'     => isset($weights[$t->id]) ? (float) $weights[$t->id]->weight : 100.0,
                    'id'         => $t->id,
                ])->values();
        }

        return [
            'config'           => $config,
            'numberOfDays'     => $config?->number_of_days  ?? 0,
            'numberOfHours'    => $config?->number_of_hours ?? 0,
            'constraintLabels' => self::constraintLabels(),
            'dayLabels'        => $config ? $config->dayLabels()     : [],
            'slotLabels'       => $config ? $config->generateSlots() : [],
            'tagOptions'       => $tagOptions,
            'constraintRows'   => $constraintRows,
            'notAvailableRows' => $notAvailableRows,
        ];
    }
}; ?>

<div>
    <x-header title="Teacher Time Constraints" separator>
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
                       :options="[['id'=>'teacher','name'=>'A teacher'],['id'=>'all','name'=>'All teachers']]"
                       class="w-36" />
            <select wire:model.live="constraintType" class="select select-bordered text-sm w-72">
                <option value="">Select constraint</option>

                @if($target === 'teacher')
                <optgroup label="─── Availability ───">
                    <option value="not_available">Not Available Times</option>
                </optgroup>
                @endif

                <optgroup label="─── Days ───">
                    <option value="max_days_per_week">Max Days per Week</option>
                    <option value="min_days_per_week">Min Days per Week</option>
                    <option value="hourly_interval_max_days">Working in Hourly Interval Max Days per Week</option>
                </optgroup>

                <optgroup label="─── Daily Hours ───">
                    <option value="max_hours_daily">Max Hours Daily</option>
                    <option value="min_hours_daily">Min Hours Daily</option>
                    <option value="max_hours_daily_tag">Max Hours Daily with Activity Tag</option>
                    <option value="min_hours_daily_tag">Min Hours Daily with Activity Tag</option>
                    <option value="max_span_per_day">Max Span per Day</option>
                </optgroup>

                <optgroup label="─── Continuous ───">
                    <option value="max_hours_continuously">Max Hours Continuously</option>
                    <option value="max_hours_continuously_tag">Max Hours Continuously with Activity Tag</option>
                </optgroup>

                <optgroup label="─── Gaps & Rest ───">
                    <option value="max_gaps_per_week">Max Gaps per Week</option>
                    <option value="max_gaps_per_day">Max Gaps per Day</option>
                    <option value="min_gaps_between_activity_tags">Min Gaps Between Activity Tags</option>
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
                                <th class="text-left py-2 font-medium text-base-content/60 w-5/12">Teacher</th>
                                <th class="text-left py-2 font-medium text-base-content/60 w-3/12">Params</th>
                                <th class="text-center py-2 font-medium text-base-content/60 w-1/12">Value</th>
                                <th class="text-center py-2 font-medium text-base-content/60 w-1/12">Weight %</th>
                                <th class="w-1/12"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($constraintRows as $row)
                                <tr class="border-b border-base-100 hover:bg-base-300/40">
                                    <td class="py-2">{{ $row->teacher }}</td>
                                    <td class="py-2 text-base-content/60 text-xs">{{ $row->params ?: '—' }}</td>
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
                <table class="table table-zebra table-sm w-full">
                    <thead>
                        <tr class="border-b border-base-200">
                            <th class="text-left py-2 font-medium text-base-content/60 w-7/12">Teacher</th>
                            <th class="text-center py-2 font-medium text-base-content/60 w-2/12">Blocked slots</th>
                            <th class="text-center py-2 font-medium text-base-content/60 w-2/12">Weight %</th>
                            <th class="w-auto"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($notAvailableRows as $row)
                            <tr class="border-b border-base-100 hover:bg-base-300/40">
                                <td class="py-2">{{ $row->teacher }}</td>
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
            </x-card>
        @endif

    @endif

    {{-- ── Bottom slide modal ── --}}
    <x-modal wire:model="modal"
             :title="($editConstraintId || ($constraintType === 'not_available' && $teacherId)) ? 'Edit Constraint' : 'Add Constraint'"
             separator class="modal-bottom"
             box-class="!max-w-2xl mx-auto !rounded-t-2xl !mb-14">

        <input type="text" class="w-0 h-0 opacity-0 absolute pointer-events-none" autofocus />

        @if($constraintType === 'not_available')

            {{-- Not Available: teacher selector + grid + weight --}}
            <div class="space-y-4">
                @if($target === 'teacher')
                    <x-choices label="Teacher" wire:model.live="teacherId" single
                               searchable :search-function="'searchTeachers'"
                               :options="$teacherOptions" placeholder="Select teacher"
                               class="w-72" />
                @endif

                @if($teacherId && $target === 'teacher')
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

                    {{-- Weight --}}
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

            {{-- Numeric constraint form --}}
            @php
                $label      = $constraintLabels[$constraintType] ?? $constraintType;
                $isTag      = in_array($constraintType, ['max_hours_daily_tag', 'min_hours_daily_tag', 'max_hours_continuously_tag']);
                $isDualTag  = $constraintType === 'min_gaps_between_activity_tags';
                $isInterval = $constraintType === 'hourly_interval_max_days';

                $slotOptions = collect($slotLabels)
                    ->filter(fn($s) => ! $s['break'])
                    ->map(fn($s) => ['id' => $s['idx'], 'name' => $s['time']])
                    ->values()->toArray();

                $hint = match($constraintType) {
                    'max_days_per_week'              => 'Max days per week (0–' . $numberOfDays . ')',
                    'min_days_per_week'              => 'Min days per week (0–' . $numberOfDays . ')',
                    'hourly_interval_max_days'       => 'Max days per week working within the selected slot interval',
                    'max_hours_daily'                => 'Max teaching slots per day (0–' . $numberOfHours . ')',
                    'min_hours_daily'                => 'Min teaching slots per day (0–' . $numberOfHours . ')',
                    'max_hours_daily_tag'            => 'Max slots per day for activities with the selected tag',
                    'min_hours_daily_tag'            => 'Min slots per day for activities with the selected tag',
                    'max_span_per_day'               => 'Max span (first–last activity) per day',
                    'max_hours_continuously'         => 'Max consecutive slots without a break',
                    'max_hours_continuously_tag'     => 'Max consecutive slots for activities with the selected tag',
                    'max_gaps_per_week'              => 'Max unused slots between lessons per week',
                    'max_gaps_per_day'               => 'Max unused slots between lessons per day',
                    'min_gaps_between_activity_tags' => 'Min gap slots between the two activity tags',
                    'min_resting_hours'              => 'Min slots between last lesson of one day and first of next',
                    default                          => '',
                };
            @endphp

            <x-form wire:submit="saveConstraint" class="space-y-4">

                {{-- Teacher selector (only for A teacher mode) --}}
                @if($target === 'teacher')
                    <x-choices label="Teacher" wire:model.live="teacherId" single
                               searchable :search-function="'searchTeachers'"
                               :options="$teacherOptions" placeholder="Select teacher"
                               class="w-72" />
                @endif

                {{-- Tag selector(s) --}}
                @if($isTag || $isDualTag)
                    <div class="{{ $isDualTag ? 'grid grid-cols-2 gap-3' : '' }}">
                        <x-choices label="{{ $isDualTag ? 'Activity Tag 1' : 'Activity Tag' }}"
                                   wire:model.live="tagId" single
                                   :options="$tagOptions" placeholder="Select tag" />
                        @if($isDualTag)
                            <x-choices label="Activity Tag 2"
                                       wire:model.live="tag2Id" single
                                       :options="$tagOptions" placeholder="Select tag" />
                        @endif
                    </div>
                @endif

                {{-- Interval slot selectors --}}
                @if($isInterval)
                    <div class="grid grid-cols-2 gap-3">
                        <x-choices label="From slot" wire:model.live="intervalStart" single
                                   :options="$slotOptions" placeholder="Start slot" />
                        <x-choices label="To slot" wire:model.live="intervalEnd" single
                                   :options="$slotOptions" placeholder="End slot" />
                    </div>
                @endif

                {{-- Value + Weight --}}
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

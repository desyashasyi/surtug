<?php

use App\Models\FetNet\AcademicYear;
use App\Models\FetNet\Activity;
use App\Models\FetNet\Building;
use App\Models\FetNet\Client;
use App\Models\FetNet\Program;
use App\Models\FetNet\Semester;
use App\Models\FetNet\Space;
use App\Models\FetNet\SpaceType;
use App\Models\FetNet\Subject;
use App\Jobs\FetNet\AssignSpacesToActivityJob;
use App\Jobs\FetNet\RemoveAllSpacesFromActivityJob;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.admin')] class extends Component
{
    use WithPagination, Toast;

    public string $search = '';
    public string $view   = 'subject';

    // Academic year / semester / program filter
    public ?int   $academicYearId      = null;
    public ?int   $semesterId          = null;
    public ?int   $filterProgramId     = null;
    public array  $academicYearOptions = [];
    public array  $semesterOptions     = [];
    public array  $programOptions      = [];

    // Assign Space modal
    public bool   $assignModal            = false;
    public ?int   $assignActivityId       = null;
    public array  $assignSpaceIds         = [];
    public ?int   $assignBuildingFilter   = null;
    public ?int   $assignTypeFilter       = null;
    public string $assignCapacityFilter   = '';
    public int    $assignPage             = 1;
    public int    $assignedPage           = 1;
    public array  $assignBuildingOptions  = [];
    public array  $assignTypeOptions      = [];
    public string $assignSubjectName      = '';
    public string $assignSubjectCode      = '';
    public int    $assignStudentCount     = 0;

    public array $assignCapacityOptions = [
        ['id' => '1-20',   'name' => '1 – 20'],
        ['id' => '20-40',  'name' => '20 – 40'],
        ['id' => '40-50',  'name' => '40 – 50'],
        ['id' => '50-999', 'name' => '50+'],
    ];

    public array $headers = [
        ['key' => 'semester', 'label' => 'Sem',     'class' => 'w-1/12 text-center align-top'],
        ['key' => 'code',     'label' => 'Code',    'class' => 'w-1/12 align-top'],
        ['key' => 'name',     'label' => 'Subject', 'class' => 'w-4/12 align-top'],
        ['key' => 'classes',  'label' => 'Classes', 'class' => 'w-6/12 align-top text-right'],
    ];

    // Activity detail modal
    public bool  $detailModal = false;
    public array $detailData  = [];

    private function client(): ?Client
    {
        return Client::where('user_id', auth()->id())->first();
    }

    private function clientProgramIds(): array
    {
        $client = $this->client();
        if (! $client) return [];
        return Program::where('client_id', $client->id)->pluck('id')->toArray();
    }

    public function mount(): void
    {
        $client = $this->client();
        if ($client) {
            $this->loadAcademicYearOptions($client->id);
            $storedAy = session('admin.academic_year_id');
            if ($storedAy && collect($this->academicYearOptions)->contains('id', $storedAy)) {
                $this->academicYearId = $storedAy;
            }
            $this->loadSemesterOptions();
            $storedSem = session('admin.semester_id');
            if ($storedSem && collect($this->semesterOptions)->contains('id', $storedSem)) {
                $this->semesterId = $storedSem;
            }

            $this->programOptions = Program::where('client_id', $client->id)
                ->orderBy('abbrev')
                ->get(['id', 'abbrev', 'name'])
                ->map(fn($p) => ['id' => $p->id, 'name' => "{$p->abbrev} — {$p->name}"])
                ->toArray();
        }

        session(['admin.academic_year_id' => $this->academicYearId, 'admin.semester_id' => $this->semesterId]);
    }

    private function loadAcademicYearOptions(int $clientId): void
    {
        $lastSemester = Semester::whereHas('academicYear', fn($q) => $q->where('client_id', $clientId))
            ->orderByDesc('created_at')->first();

        $ays = AcademicYear::where('client_id', $clientId)->orderByDesc('year_start')->get();

        $this->academicYearOptions = $ays
            ->map(fn($ay) => ['id' => $ay->id, 'name' => $ay->label])
            ->toArray();

        $this->academicYearId = $lastSemester?->academic_year_id
            ?? $ays->firstWhere('is_active', true)?->id
            ?? $ays->first()?->id;
    }

    private function loadSemesterOptions(): void
    {
        if (! $this->academicYearId) {
            $this->semesterOptions = [];
            return;
        }

        $mn = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',
               7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec'];

        $semesters = Semester::where('academic_year_id', $this->academicYearId)
            ->orderByDesc('created_at')->get();

        $this->semesterOptions = $semesters->map(function ($s) use ($mn) {
            $period   = ($s->start_month && $s->end_month)
                ? ' (' . ($mn[$s->start_month] ?? '?') . '–' . ($mn[$s->end_month] ?? '?') . ')'
                : '';
            $semLabel = ($s->name ?? ($s->semester == 1 ? 'Odd' : 'Even')) . $period;
            return ['id' => $s->id, 'name' => $semLabel];
        })->toArray();

        if ($semesters->isNotEmpty()) {
            $this->semesterId = $semesters->first()->id;
        }
    }

    public function updatedAcademicYearId(): void
    {
        $this->semesterId = null;
        $this->loadSemesterOptions();
        session(['admin.academic_year_id' => $this->academicYearId, 'admin.semester_id' => $this->semesterId]);
        $this->resetPage();
    }

    public function updatedSemesterId(): void
    {
        session(['admin.semester_id' => $this->semesterId]);
        $this->resetPage();
    }

    public function updatedFilterProgramId(): void { $this->resetPage(); }
    public function updatedSearch(): void          { $this->resetPage(); }
    public function updatedView(): void            { $this->resetPage(); }

    // ── Assign Space ──────────────────────────────────────────────────────────

    public function openAssignSpace(int $activityId): void
    {
        $client   = $this->client();
        $activity = Activity::with(['planning.subject', 'students'])->findOrFail($activityId);

        $this->assignActivityId     = $activityId;
        $this->assignSpaceIds       = $activity->spaces()->pluck('fetnet_space.id')->toArray();
        $this->assignBuildingFilter = null;
        $this->assignTypeFilter     = null;
        $this->assignCapacityFilter = '';
        $this->assignPage           = 1;
        $this->assignedPage         = 1;
        $this->assignSubjectName    = $activity->planning?->subject?->name ?? '—';
        $this->assignSubjectCode    = $activity->planning?->subject?->code ?? '';
        $this->assignStudentCount   = $activity->students->sum('number_of_student');

        $this->assignBuildingOptions = Building::where('client_id', $client->id)
            ->orderBy('name')->get(['id', 'name', 'code'])
            ->map(fn($b) => ['id' => $b->id, 'name' => $b->code ? "[{$b->code}] {$b->name}" : $b->name])
            ->toArray();

        $this->assignTypeOptions = SpaceType::where('is_theory', false)->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn($t) => ['id' => $t->id, 'name' => $t->code ? "[{$t->code}] {$t->name}" : $t->name])
            ->toArray();

        $this->assignModal = true;
    }

    public function updatedAssignBuildingFilter(): void   { $this->assignPage = 1; }
    public function updatedAssignTypeFilter(): void       { $this->assignPage = 1; }
    public function updatedAssignCapacityFilter(): void   { $this->assignPage = 1; }

    public function assignPrev(): void { if ($this->assignPage > 1) $this->assignPage--; }
    public function assignNext(int $lastPage): void { if ($this->assignPage < $lastPage) $this->assignPage++; }

    public function assignedPrev(): void { if ($this->assignedPage > 1) $this->assignedPage--; }
    public function assignedNext(int $lastPage): void { if ($this->assignedPage < $lastPage) $this->assignedPage++; }

    public function selectSpace(int $spaceId): void
    {
        Activity::findOrFail($this->assignActivityId)->spaces()->attach($spaceId);
        $this->assignSpaceIds[] = $spaceId;
        $this->success('Space assigned.', position: 'toast-top toast-center');
    }

    public function removeSpace(int $spaceId): void
    {
        Activity::findOrFail($this->assignActivityId)->spaces()->detach($spaceId);
        $this->assignSpaceIds = array_values(array_filter($this->assignSpaceIds, fn($id) => $id !== $spaceId));
        $assignedPerPage = 10;
        $assignedTotal   = count($this->assignSpaceIds);
        $assignedLast    = $assignedTotal > 0 ? (int) ceil($assignedTotal / $assignedPerPage) : 1;
        if ($this->assignedPage > $assignedLast) $this->assignedPage = $assignedLast;
        $this->warning('Space removed.', position: 'toast-top toast-center');
    }

    public function removeAll(): void
    {
        RemoveAllSpacesFromActivityJob::dispatch($this->assignActivityId);
        $this->assignSpaceIds = [];
        $this->assignedPage   = 1;
        $this->warning('All spaces queued for removal.', position: 'toast-top toast-center');
    }

    public function selectAll(): void
    {
        $client = $this->client();

        $newIds = Space::where('client_id', $client?->id)
            ->where(fn($q) => $q->whereNull('type_id')
                ->orWhereHas('type', fn($q2) => $q2->where('is_theory', false)))
            ->when($this->assignBuildingFilter,   fn($q) => $q->where('building_id', $this->assignBuildingFilter))
            ->when($this->assignTypeFilter,       fn($q) => $q->where('type_id', $this->assignTypeFilter))
            ->when($this->assignCapacityFilter,   function ($q) {
                [$min, $max] = explode('-', $this->assignCapacityFilter);
                $q->where('capacity', '>=', (int)$min)->where('capacity', '<=', (int)$max);
            })
            ->whereNotIn('id', $this->assignSpaceIds)
            ->pluck('id')->toArray();

        if (empty($newIds)) {
            $this->info('No new spaces to assign.', position: 'toast-top toast-center');
            return;
        }

        AssignSpacesToActivityJob::dispatch($this->assignActivityId, $newIds);
        $this->assignSpaceIds = array_merge($this->assignSpaceIds, $newIds);
        $this->success(count($newIds) . ' spaces queued for assignment.', position: 'toast-top toast-center');
    }

    // ── Activity Detail ───────────────────────────────────────────────────────

    public function openDetail(int $activityId): void
    {
        $activity = Activity::with(['planning.subject', 'type', 'teachers', 'students', 'spaces.building', 'tags'])
            ->findOrFail($activityId);

        $this->detailData = [
            'subject'  => trim(($activity->planning?->subject?->code ?? '') . ' — ' . ($activity->planning?->subject?->name ?? ''), ' — '),
            'type'     => $activity->type?->name ?? '—',
            'duration' => $activity->duration,
            'active'   => $activity->active,
            'teachers' => $activity->teachers->map(fn($t) => $t->code . ($t->name ? ' — ' . $t->name : ''))->toArray(),
            'groups'   => $activity->students->pluck('name')->toArray(),
            'spaces'   => $activity->spaces->map(fn($s) => [
                'name'     => $s->name,
                'building' => $s->building?->name ?? '—',
                'capacity' => $s->capacity,
            ])->toArray(),
            'tags'     => $activity->tags->pluck('name')->toArray(),
            'note'     => $activity->note ?? '',
        ];

        $this->detailModal = true;
    }

    // ─────────────────────────────────────────────────────────────────────────

    public function with(): array
    {
        $client     = $this->client();
        $programIds = $this->clientProgramIds();
        $filterIds  = $this->filterProgramId ? [$this->filterProgramId] : $programIds;

        // Program abbrev map for display
        $programMap = Program::whereIn('id', $programIds)->get(['id', 'abbrev'])
            ->mapWithKeys(fn($p) => [$p->id => $p->abbrev])
            ->toArray();

        $subjects = (count($filterIds) && $this->semesterId)
            ? Subject::with(['activities' => fn($q) => $q
                ->when($this->semesterId, fn($q) => $q->whereHas('planning', fn($p) =>
                    $p->where('semester_id', $this->semesterId)))
                ->with(['teachers', 'type', 'students', 'subActivities'])
              ])
                ->whereIn('program_id', $filterIds)
                ->when($this->semesterId, fn($q) => $q->whereHas('activityPlannings', fn($p) =>
                    $p->where('semester_id', $this->semesterId)->whereIn('program_id', $filterIds)))
                ->when($this->search, fn($q) => $q
                    ->where('name', 'ilike', "%{$this->search}%")
                    ->orWhere('code', 'ilike', "%{$this->search}%"))
                ->orderBy('semester')->orderBy('code')
                ->paginate(15)
                ->through(fn($s) => tap($s, fn($item) => [
                    $item->program_abbrev = $programMap[$s->program_id] ?? '?',
                ]))
            : collect();

        $activities = (count($filterIds) && $this->view === 'all')
            ? Activity::with(['planning.subject', 'type', 'teachers', 'students', 'spaces'])
                ->whereIn('program_id', $filterIds)
                ->when($this->semesterId, fn($q) => $q->whereHas('planning', fn($p) => $p->where('semester_id', $this->semesterId)))
                ->when($this->search, fn($q) => $q->whereHas('planning', fn($p) => $p->whereHas('subject',
                    fn($s) => $s->where('name', 'ilike', "%{$this->search}%")
                                ->orWhere('code', 'ilike', "%{$this->search}%"))))
                ->paginate(15)
                ->through(fn($a) => tap($a, fn($item) => [
                    $item->subject_nm     = $a->planning?->subject?->code . ' — ' . $a->planning?->subject?->name,
                    $item->type_nm        = $a->type?->name ?? '-',
                    $item->teachers_nm    = $a->teachers->pluck('code')->filter()->implode(', ') ?: '-',
                    $item->students_nm    = $a->students->pluck('name')->implode(', ') ?: '-',
                    $item->program_abbrev = $programMap[$a->program_id] ?? '?',
                    $item->spaces_count   = $a->spaces->count(),
                ]))
            : collect();

        // Spaces for the assign modal
        $assignPerPage = 10;
        $assignQuery   = $this->assignModal
            ? Space::with(['building:id,name,code'])->withCount('activities')
                ->where('client_id', $client?->id)
                ->where(fn($q) => $q->whereNull('type_id')
                    ->orWhereHas('type', fn($q2) => $q2->where('is_theory', false)))
                ->when($this->assignBuildingFilter,   fn($q) => $q->where('building_id', $this->assignBuildingFilter))
                ->when($this->assignTypeFilter,       fn($q) => $q->where('type_id', $this->assignTypeFilter))
                ->when($this->assignCapacityFilter,   function ($q) {
                    [$min, $max] = explode('-', $this->assignCapacityFilter);
                    $q->where('capacity', '>=', (int)$min)->where('capacity', '<=', (int)$max);
                })
                ->when($this->assignSpaceIds,         fn($q) => $q->whereNotIn('id', $this->assignSpaceIds))
                ->orderBy('name')
            : null;

        $assignTotal  = $assignQuery?->count() ?? 0;
        $assignSpaces = $assignQuery
            ? $assignQuery->offset(($this->assignPage - 1) * $assignPerPage)->limit($assignPerPage)->get()
            : collect();
        $assignLastPage = $assignTotal > 0 ? (int) ceil($assignTotal / $assignPerPage) : 1;

        $assignedPerPage  = 10;
        $assignedTotal    = count($this->assignSpaceIds);
        $assignedLastPage = $assignedTotal > 0 ? (int) ceil($assignedTotal / $assignedPerPage) : 1;
        $assignedSpaces   = ($this->assignModal && $assignedTotal)
            ? Space::with('building:id,name')->withCount('activities')
                ->whereIn('id', $this->assignSpaceIds)->orderBy('name')
                ->offset(($this->assignedPage - 1) * $assignedPerPage)->limit($assignedPerPage)->get()
            : collect();

        return compact('subjects', 'activities', 'programMap', 'assignSpaces', 'assignTotal', 'assignLastPage', 'assignedSpaces', 'assignedTotal', 'assignedLastPage');
    }
}; ?>

<div>
    <x-header title="Activities" subtitle="Manage course sessions across all programs" separator>
        <x-slot:actions>
            @if(count($academicYearOptions))
            <x-select wire:model.live="academicYearId" :options="$academicYearOptions"
                      placeholder="Academic Year" class="w-36" />
            @endif
            @if(count($semesterOptions))
            <x-select wire:model.live="semesterId" :options="$semesterOptions"
                      placeholder="Semester" class="w-48" />
            @endif
            <div class="w-64">
                <x-choices single searchable
                           wire:model.live="filterProgramId"
                           :options="$programOptions"
                           placeholder="— All Programs —"
                           clearable />
            </div>
            <x-input placeholder="Search subject..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
            <div class="join">
                <x-button label="By Subject" class="btn-sm join-item {{ $view === 'subject' ? 'btn-primary' : 'btn-ghost' }}"
                          wire:click="$set('view','subject')" />
                <x-button label="All"        class="btn-sm join-item {{ $view === 'all'     ? 'btn-primary' : 'btn-ghost' }}"
                          wire:click="$set('view','all')" />
            </div>
        </x-slot:actions>
    </x-header>

    @if($view === 'subject')
    <x-card>
        <x-table :striped="true" :headers="$headers" :rows="$subjects" with-pagination container-class="overflow-hidden" class="table-fixed">

            @scope('cell_semester', $row)
                <div class="text-center">{{ $row->semester ?? '-' }}</div>
            @endscope

            @scope('cell_code', $row)
                {{ $row->code }}
            @endscope

            @scope('cell_classes', $row)
                <div class="flex flex-wrap justify-end gap-y-1 gap-x-2">
                    @forelse($row->activities as $activity)
                        @php
                            $teachers    = $activity->teachers->pluck('code')->filter()->implode('|');
                            $groups      = $activity->students->pluck('name')->implode('|');
                            $tooltip     = $activity->type?->name ?? '';
                            $active      = $activity->active;
                            $subs        = $activity->subActivities;
                            $durationStr = $subs->count() > 1
                                ? $subs->pluck('duration')->implode('+')
                                : (string) $activity->duration;
                        @endphp
                        <div class="flex items-center gap-1">
                            <div class="w-2 h-2 rounded-full shrink-0 {{ $active ? 'bg-primary' : 'bg-base-content/20' }}"></div>
                            <div class="tooltip tooltip-top" data-tip="{{ $tooltip }}">
                                <button wire:click="openDetail({{ $activity->id }})" class="cursor-pointer">
                                    <x-badge value="{{ ($teachers ?: '?') . ($groups ? ' ('.$groups.')' : ' (no student)') . ' ' . $durationStr }}"
                                             class="{{ $active ? 'badge-primary badge-dash' : 'badge-dash !bg-base-200 !text-base-content/40 !border-base-content/20' }} {{ !$groups ? 'border-warning text-warning' : '' }} hover:opacity-75" />
                                </button>
                            </div>
                        </div>
                    @empty
                        <span class="text-base-content/30 text-xs italic">no activities</span>
                    @endforelse
                </div>
            @endscope

        </x-table>
    </x-card>
    @else
    <x-card>
        @php
        $allHeaders = [
            ['key' => 'program_abbrev', 'label' => 'Program',  'class' => 'w-1/12'],
            ['key' => 'subject_nm',     'label' => 'Subject',  'class' => 'w-5/12'],
            ['key' => 'type_nm',        'label' => 'Type',     'class' => 'w-1/12'],
            ['key' => 'duration',       'label' => 'Dur.',     'class' => 'w-1/12 text-center'],
            ['key' => 'teachers_nm',    'label' => 'Teachers', 'class' => 'w-1/12'],
            ['key' => 'students_nm',    'label' => 'Groups',   'class' => 'w-1/12'],
            ['key' => 'action',         'label' => '',         'class' => 'w-2/12 text-right'],
        ];
        @endphp
        <x-table :striped="true" :headers="$allHeaders" :rows="$activities" with-pagination container-class="overflow-hidden" class="table-fixed">
            @scope('cell_duration', $row)
                <div class="text-center">{{ $row->duration }}</div>
            @endscope

            @scope('cell_action', $row)
                <x-button icon="o-building-office" class="btn-ghost btn-xs"
                          wire:click="openAssignSpace({{ $row->id }})">
                    Space @if($row->spaces_count > 0)<span class="ml-1 font-bold text-primary">{{ $row->spaces_count }}</span>@endif
                </x-button>
            @endscope
        </x-table>
    </x-card>
    @endif

    {{-- Assign Space Modal --}}
    <x-modal wire:model="assignModal" title="Assign Space"
             separator class="modal-bottom" box-class="!max-w-[96rem] mx-auto !rounded-t-2xl !mb-14">
        <div class="grid grid-cols-2 gap-6">

            {{-- LEFT: Available Spaces --}}
            <div class="space-y-3">
                <div class="flex gap-2 flex-wrap">
                    <div class="flex-1 min-w-28">
                        <x-choices label="Building" single wire:model.live="assignBuildingFilter"
                                   :options="$assignBuildingOptions" placeholder="— All —" clearable />
                    </div>
                    <div class="flex-1 min-w-28">
                        <x-choices label="Type" single wire:model.live="assignTypeFilter"
                                   :options="$assignTypeOptions" placeholder="— All —" clearable />
                    </div>
                    <div class="flex-1 min-w-28">
                        <x-choices label="Capacity" single wire:model.live="assignCapacityFilter"
                                   :options="$assignCapacityOptions" placeholder="— All —" clearable />
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl border border-base-200">
                    <table class="table table-sm table-zebra w-full">
                        <thead>
                            <tr class="text-base-content/60 text-xs bg-base-200/50">
                                <th class="w-6/12">Name</th>
                                <th class="w-3/12">Building</th>
                                <th class="w-1/12 text-right">Cap.</th>
                                <th class="w-1/12 text-right">Used</th>
                                <th class="w-8 text-right">
                                    <x-button icon="o-check-circle" class="btn-primary btn-xs btn-square"
                                              wire:click="selectAll" tooltip="Assign all filtered" />
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($assignSpaces as $space)
                            <tr>
                                <td class="font-medium text-sm">{{ $space->name }}</td>
                                <td class="text-sm text-base-content/70">{{ $space->building?->name ?? '—' }}</td>
                                <td class="text-right text-sm">{{ $space->capacity ?? '—' }}</td>
                                <td class="text-right text-sm {{ $space->activities_count > 0 ? 'text-warning font-medium' : 'text-base-content/30' }}">
                                    {{ $space->activities_count }}
                                </td>
                                <td class="text-right">
                                    <x-button icon="o-check-circle" class="btn-primary btn-xs btn-square"
                                              wire:click="selectSpace({{ $space->id }})" tooltip="Assign" />
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center text-sm text-base-content/40 py-6 italic">
                                    No spaces found.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($assignTotal > 10)
                <div class="flex items-center justify-between text-sm">
                    <span class="text-base-content/40 text-xs">{{ $assignTotal }} spaces</span>
                    <div class="join">
                        <x-button class="btn-xs join-item" icon="o-chevron-left"
                                  wire:click="assignPrev" :disabled="$assignPage <= 1" />
                        <span class="join-item btn btn-xs btn-ghost pointer-events-none">
                            {{ $assignPage }} / {{ $assignLastPage }}
                        </span>
                        <x-button class="btn-xs join-item" icon="o-chevron-right"
                                  wire:click="assignNext({{ $assignLastPage }})" :disabled="$assignPage >= $assignLastPage" />
                    </div>
                </div>
                @endif
            </div>

            {{-- RIGHT: Assigned Space --}}
            <div class="space-y-3">
                <div class="bg-base-200/50 rounded-xl px-4 py-3 flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-xs text-base-content/50 mb-0.5">Subject</p>
                        <p class="font-semibold text-sm truncate">
                            @if($assignSubjectCode)<span class="text-base-content/50 font-normal mr-1">{{ $assignSubjectCode }}</span>@endif{{ $assignSubjectName }}
                        </p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-xs text-base-content/50 mb-0.5">Total Students</p>
                        <p class="font-semibold text-sm">{{ $assignStudentCount }}</p>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl border border-base-200">
                    <table class="table table-sm table-zebra w-full">
                        <thead>
                            <tr class="text-base-content/60 text-xs bg-base-200/50">
                                <th class="w-6/12">Name</th>
                                <th class="w-3/12">Building</th>
                                <th class="w-1/12 text-right">Cap.</th>
                                <th class="w-1/12 text-right">Used</th>
                                <th class="w-8 text-right">
                                    @if(count($assignSpaceIds))
                                    <x-button icon="o-x-circle" class="btn-error btn-xs btn-square btn-outline"
                                              wire:click="removeAll" tooltip="Remove all" />
                                    @endif
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($assignedSpaces as $space)
                            <tr>
                                <td class="font-medium text-sm">{{ $space->name }}</td>
                                <td class="text-sm text-base-content/70">{{ $space->building?->name ?? '—' }}</td>
                                <td class="text-right text-sm">{{ $space->capacity ?? '—' }}</td>
                                <td class="text-right text-sm {{ $space->activities_count > 0 ? 'text-warning font-medium' : 'text-base-content/30' }}">
                                    {{ $space->activities_count }}
                                </td>
                                <td class="text-right">
                                    <x-button icon="o-x-circle" class="btn-error btn-xs btn-square btn-outline"
                                              wire:click="removeSpace({{ $space->id }})" tooltip="Remove" />
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center text-sm text-base-content/40 py-6 italic">
                                    No space assigned.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($assignedTotal > 10)
                <div class="flex items-center justify-between text-sm">
                    <span class="text-base-content/40 text-xs">{{ $assignedTotal }} assigned</span>
                    <div class="join">
                        <x-button class="btn-xs join-item" icon="o-chevron-left"
                                  wire:click="assignedPrev" :disabled="$assignedPage <= 1" />
                        <span class="join-item btn btn-xs btn-ghost pointer-events-none">
                            {{ $assignedPage }} / {{ $assignedLastPage }}
                        </span>
                        <x-button class="btn-xs join-item" icon="o-chevron-right"
                                  wire:click="assignedNext({{ $assignedLastPage }})" :disabled="$assignedPage >= $assignedLastPage" />
                    </div>
                </div>
                @endif
            </div>

        </div>
    </x-modal>

    {{-- Activity Detail Modal --}}
    <x-modal wire:model="detailModal" title="Activity Detail"
             separator class="modal-bottom" box-class="!max-w-xl mx-auto !rounded-t-2xl !mb-14">
        @if($detailData)
        <div class="space-y-3 text-sm">
            <div>
                <p class="text-xs text-base-content/50 mb-0.5">Subject</p>
                <p class="font-semibold">{{ $detailData['subject'] ?? '—' }}</p>
            </div>
            <div class="flex gap-6 flex-wrap">
                <div>
                    <p class="text-xs text-base-content/50 mb-0.5">Type</p>
                    <p>{{ $detailData['type'] }}</p>
                </div>
                <div>
                    <p class="text-xs text-base-content/50 mb-0.5">Duration</p>
                    <p>{{ $detailData['duration'] }} min</p>
                </div>
                <div>
                    <p class="text-xs text-base-content/50 mb-0.5">Status</p>
                    @if($detailData['active'])
                        <x-badge value="Active" class="badge-success badge-sm" />
                    @else
                        <x-badge value="Inactive" class="badge-ghost badge-sm" />
                    @endif
                </div>
            </div>
            <div>
                <p class="text-xs text-base-content/50 mb-0.5">Teachers</p>
                @if(count($detailData['teachers']))
                    <div class="flex flex-wrap gap-1">
                        @foreach($detailData['teachers'] as $t)
                            <x-badge value="{{ $t }}" class="badge-neutral badge-sm" />
                        @endforeach
                    </div>
                @else
                    <span class="text-base-content/30 italic">No teachers assigned</span>
                @endif
            </div>
            <div>
                <p class="text-xs text-base-content/50 mb-0.5">Student Groups</p>
                @if(count($detailData['groups']))
                    <div class="flex flex-wrap gap-1">
                        @foreach($detailData['groups'] as $g)
                            <x-badge value="{{ $g }}" class="badge-info badge-sm" />
                        @endforeach
                    </div>
                @else
                    <span class="text-base-content/30 italic">No groups assigned</span>
                @endif
            </div>
            <div>
                <p class="text-xs text-base-content/50 mb-0.5">Spaces</p>
                @if(count($detailData['spaces']))
                    <div class="space-y-1.5">
                        @foreach($detailData['spaces'] as $s)
                        <div class="bg-base-200/50 rounded-lg p-2.5 space-y-0.5">
                            <p class="font-medium text-sm">{{ $s['name'] }}</p>
                            <div class="flex gap-4 text-xs text-base-content/60">
                                <span>Building: <span class="text-base-content">{{ $s['building'] }}</span></span>
                                @if($s['capacity'])
                                <span>Cap.: <span class="text-base-content">{{ $s['capacity'] }}</span></span>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <span class="text-base-content/30 italic">No spaces assigned</span>
                @endif
            </div>
            @if(count($detailData['tags']))
            <div>
                <p class="text-xs text-base-content/50 mb-0.5">Tags</p>
                <div class="flex flex-wrap gap-1">
                    @foreach($detailData['tags'] as $tag)
                        <x-badge value="{{ $tag }}" class="badge-ghost badge-sm" />
                    @endforeach
                </div>
            </div>
            @endif
            @if($detailData['note'])
            <div>
                <p class="text-xs text-base-content/50 mb-0.5">Note</p>
                <p class="text-base-content/70">{{ $detailData['note'] }}</p>
            </div>
            @endif
        </div>
        @endif
    </x-modal>

    <x-toast />
</div>

<?php

use App\Models\FetNet\AcademicYear;
use App\Models\FetNet\Activity;
use App\Models\FetNet\ActivityPlanning;
use App\Models\FetNet\ActivityTag;
use App\Models\FetNet\ActivityType;
use App\Models\FetNet\Client;
use App\Models\FetNet\CurriculumYear;
use App\Models\FetNet\Program;
use App\Models\FetNet\Semester;
use App\Models\FetNet\Space;
use App\Models\FetNet\SpaceClaim;
use App\Models\FetNet\Student;
use App\Models\FetNet\SubActivity;
use App\Models\FetNet\Subject;
use App\Models\FetNet\Teacher;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.program')] class extends Component
{
    use WithPagination, Toast;

    public string $search    = '';
    public string $view      = 'subject'; // 'subject' | 'all'
    public bool   $modal     = false;
    public bool   $delModal  = false;
    public ?int   $editId    = null;
    public ?int   $deleteId  = null;

    // Pre-filled when clicking + on a subject row
    public ?int   $subject_id     = null;
    public ?int   $type_id        = null;
    public int    $extraDuration  = 0;
    public int    $subjectCredit  = 0;
    public bool   $active         = true;
    public array  $teacherIds     = [];
    public array  $studentIds     = [];
    public array  $tagIds         = [];

    // Space
    public ?int   $space_id      = null;
    public array  $spaceOptions  = [];

    // Space manager modal
    public bool   $spaceModal       = false;
    public string $spaceQuery       = '';
    public array  $availableSpaces  = [];

    // Tag manager modal
    public bool   $tagModal   = false;
    public string $newTagName = '';

    // Split modal
    public bool   $splitModal      = false;
    public ?int   $splitActivityId = null;
    public int    $splitTotal      = 0;
    public array  $splits          = [];   // [{duration: int}]

    // Academic year / semester filter
    public ?int   $academicYearId      = null;
    public ?int   $semesterId          = null;
    public array  $academicYearOptions = [];
    public array  $semesterOptions     = [];

    // Planning modal
    public bool  $planningModal      = false;
    public ?int  $planFilterYear     = null;
    public ?int  $planFilterSemester = null;
    public array $planSubjects       = [];
    public int   $planPage           = 1;
    public int   $planTotal          = 0;
    public int   $planPerPage        = 10;
    public array $curriculumYearOptions = [];

    public array $subjectSemesterOptions = [
        ['id' => 1, 'name' => '1'],
        ['id' => 2, 'name' => '2'],
        ['id' => 3, 'name' => '3'],
        ['id' => 4, 'name' => '4'],
        ['id' => 5, 'name' => '5'],
        ['id' => 6, 'name' => '6'],
        ['id' => 7, 'name' => '7'],
        ['id' => 8, 'name' => '8'],
    ];

    public array $subjectOptions  = [];
    public array $typeOptions     = [];
    public array $teacherOptions  = [];
    public array $studentOptions  = [];
    public array $tagOptions      = [];

    public array $headers = [
        ['key' => 'semester', 'label' => 'Sem',      'class' => 'w-1/12 text-center align-top'],
        ['key' => 'code',     'label' => 'Code',     'class' => 'w-1/12 align-top'],
        ['key' => 'name',     'label' => 'Subject',  'class' => 'w-4/12 align-top'],
        ['key' => 'classes',  'label' => 'Classes',  'class' => 'w-5/12 align-top'],
        ['key' => 'action',   'label' => '',         'class' => 'w-1/12 align-top text-right'],
    ];

    private function program(): ?Program
    {
        return Program::where('user_id', auth()->id())->first();
    }

    public function mount(): void
    {
        $program = $this->program();
        if ($program) {
            $this->loadAcademicYearOptions($program->client_id);
            // Restore AY from session if still valid
            $storedAy = session('program.academic_year_id');
            if ($storedAy && collect($this->academicYearOptions)->contains('id', $storedAy)) {
                $this->academicYearId = $storedAy;
            }
            $this->loadSemesterOptions();
            // Restore semester from session if valid for this AY
            $storedSem = session('program.semester_id');
            if ($storedSem && collect($this->semesterOptions)->contains('id', $storedSem)) {
                $this->semesterId = $storedSem;
            }
        }
        session(['program.academic_year_id' => $this->academicYearId, 'program.semester_id' => $this->semesterId]);
        $this->loadOptions();
        $this->searchSubjects();
        $this->searchTeachers();
        $this->searchStudents();
    }

    private function loadAcademicYearOptions(?int $clientId): void
    {
        if (! $clientId) {
            $this->academicYearOptions = [];
            return;
        }

        // Default to the AY of the last created semester
        $lastSemester = Semester::whereHas('academicYear', fn($q) => $q->where('client_id', $clientId))
            ->orderByDesc('created_at')
            ->first();

        $ays = AcademicYear::where('client_id', $clientId)
            ->orderByDesc('year_start')
            ->get();

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
            ->orderByDesc('created_at')
            ->get();

        $this->semesterOptions = $semesters->map(function ($s) use ($mn) {
            $period   = ($s->start_month && $s->end_month)
                ? ' (' . ($mn[$s->start_month] ?? '?') . '–' . ($mn[$s->end_month] ?? '?') . ')'
                : '';
            $semLabel = ($s->name ?? ($s->semester == 1 ? 'Odd' : 'Even')) . $period;
            return ['id' => $s->id, 'name' => $semLabel];
        })->toArray();

        // Default to last created semester in this AY
        if ($semesters->isNotEmpty()) {
            $this->semesterId = $semesters->first()->id;
        }
    }

    public function updatedAcademicYearId(): void
    {
        $this->semesterId = null;
        $this->loadSemesterOptions();
        session(['program.academic_year_id' => $this->academicYearId, 'program.semester_id' => $this->semesterId]);
        $this->resetPage();
    }

    public function updatedSemesterId(): void
    {
        session(['program.semester_id' => $this->semesterId]);
        $this->resetPage();
        if ($this->planningModal) $this->loadPlanSubjects();
    }

    private function loadOptions(): void
    {
        $program = $this->program();
        if (! $program) return;

        $this->typeOptions = ActivityType::orderBy('name')->get()
            ->map(fn($t) => ['id' => $t->id, 'name' => $t->name])
            ->toArray();

        $this->tagOptions = ActivityTag::where('program_id', $program->id)
            ->orderBy('name')->get()
            ->map(fn($t) => ['id' => $t->id, 'name' => $t->name])
            ->toArray();

        $this->curriculumYearOptions = CurriculumYear::where('program_id', $program->id)
            ->orderByDesc('year')->get()
            ->map(fn($y) => ['id' => $y->id, 'name' => $y->year])
            ->toArray();

        $this->loadSpaceOptions();
    }

    private function loadSpaceOptions(): void
    {
        $program = $this->program();
        if (! $program) { $this->spaceOptions = []; return; }

        $this->spaceOptions = Space::whereHas('claims', fn($q) =>
                $q->where('program_id', $program->id)->where('status', 'accepted'))
            ->orderBy('name')->get()
            ->map(fn($s) => ['id' => $s->id, 'name' => ($s->code ? "[{$s->code}] " : '') . $s->name])
            ->toArray();
    }

    public function searchSubjects(string $value = ''): void
    {
        $program = $this->program();
        if (! $program) { $this->subjectOptions = []; return; }
        $this->subjectOptions = Subject::where('program_id', $program->id)
            ->when($this->semesterId, fn($q) => $q->whereHas('activityPlannings', fn($p) =>
                $p->where('semester_id', $this->semesterId)->where('program_id', $program->id)))
            ->where(fn($q) => $q
                ->where('name', 'ilike', "%{$value}%")
                ->orWhere('code', 'ilike', "%{$value}%")
                ->orWhere('id', $this->subject_id))
            ->orderBy('code')->limit(15)->get()
            ->map(fn($s) => ['id' => $s->id, 'name' => "{$s->code} | {$s->name}"])
            ->toArray();
    }

    public function searchTeachers(string $value = ''): void
    {
        $program = $this->program();
        if (! $program) { $this->teacherOptions = []; return; }
        $this->teacherOptions = Teacher::where('program_id', $program->id)
            ->where(fn($q) => $q
                ->where('name', 'ilike', "%{$value}%")
                ->orWhere('code', 'ilike', "%{$value}%")
                ->orWhereIn('id', $this->teacherIds))
            ->orderBy('name')->limit(15)->get()
            ->map(fn($t) => ['id' => $t->id, 'name' => ($t->code ? "{$t->code} | " : '') . $t->name])
            ->toArray();
    }

    public function searchStudents(string $value = ''): void
    {
        $program = $this->program();
        if (! $program) { $this->studentOptions = []; return; }
        $this->studentOptions = Student::where('program_id', $program->id)
            ->whereNotNull('parent_id')
            ->where(fn($q) => $q
                ->where('name', 'ilike', "%{$value}%")
                ->orWhereIn('id', $this->studentIds))
            ->orderBy('name')->limit(15)->get()
            ->map(fn($s) => ['id' => $s->id, 'name' => $s->name])
            ->toArray();
    }

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedView(): void  { $this->resetPage(); }

    public function updatedSubjectId(): void
    {
        $this->subjectCredit = $this->subject_id
            ? (Subject::find($this->subject_id)?->credit ?? 0)
            : 0;
    }

    public function openCreate(?int $subjectId = null): void
    {
        $this->reset(['subject_id', 'type_id', 'space_id', 'extraDuration', 'subjectCredit', 'teacherIds', 'studentIds', 'tagIds', 'editId']);
        $this->active = true;
        $this->subject_id = $subjectId;
        if ($subjectId) {
            $this->subjectCredit = Subject::find($subjectId)?->credit ?? 0;
        }
        $this->loadOptions();
        $this->searchSubjects();
        $this->searchTeachers();
        $this->searchStudents();
        $this->modal = true;
    }

    public function openEdit(int $id): void
    {
        $a                   = Activity::with(['planning', 'teachers', 'students', 'tags'])->findOrFail($id);
        $this->editId        = $id;
        $this->subject_id    = $a->planning?->subject_id;
        $this->type_id       = $a->type_id;
        $this->space_id      = $a->space_id;
        $this->active        = (bool) $a->active;
        $this->subjectCredit = $a->planning?->subject?->credit ?? 0;
        $this->extraDuration = max(0, $a->duration - $this->subjectCredit);
        $this->teacherIds    = $a->teachers->pluck('id')->toArray();
        $this->studentIds    = $a->students->pluck('id')->toArray();
        $this->tagIds        = $a->tags->pluck('id')->toArray();
        $this->loadOptions();
        $this->searchSubjects();
        $this->searchTeachers();
        $this->searchStudents();
        $this->modal = true;
    }

    public function save(): void
    {
        $this->validate([
            'subject_id'    => 'required|exists:fetnet_subject,id',
            'extraDuration' => 'integer|min:0|max:8',
        ]);

        $program = $this->program();
        $credit  = Subject::find($this->subject_id)?->credit ?? 0;

        $planning = ActivityPlanning::withTrashed()->firstOrCreate(
            ['subject_id' => $this->subject_id, 'program_id' => $program->id, 'semester_id' => $this->semesterId],
            []
        );
        if ($planning->trashed()) $planning->restore();

        $data = [
            'planning_id' => $planning->id,
            'type_id'     => $this->type_id,
            'space_id'    => $this->space_id,
            'duration'    => $credit + $this->extraDuration,
            'active'      => $this->active,
        ];

        if ($this->editId) {
            $activity = Activity::findOrFail($this->editId);
            $activity->update($data);
        } else {
            $activity = Activity::create(array_merge($data, ['program_id' => $program->id]));
        }

        $activity->teachers()->sync($this->teacherIds);
        $activity->students()->sync($this->studentIds);
        $activity->tags()->sync($this->tagIds);

        $this->editId
            ? $this->success('Activity updated.', position: 'toast-top toast-center')
            : $this->success('Activity added.', position: 'toast-top toast-center');

        $this->modal = false;
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId = $id;
        $this->delModal = true;
    }

    public function toggleActive(int $id): void
    {
        $activity = Activity::findOrFail($id);
        $activity->update(['active' => ! $activity->active]);
    }

    public function openSplit(int $id): void
    {
        $activity              = Activity::with('subActivities')->findOrFail($id);
        $this->splitActivityId = $id;
        $this->splitTotal      = $activity->duration;

        $this->splits = $activity->subActivities->isNotEmpty()
            ? $activity->subActivities->map(fn($s) => ['duration' => $s->duration])->toArray()
            : [['duration' => $activity->duration]];

        $this->splitModal = true;
    }

    public function addSplit(): void
    {
        $this->splits[] = ['duration' => 1];
    }

    public function removeSplit(int $index): void
    {
        array_splice($this->splits, $index, 1);
        $this->splits = array_values($this->splits);
    }

    public function saveSplits(): void
    {
        $used = collect($this->splits)->sum('duration');

        if ($used !== $this->splitTotal) {
            $this->addError('splits', "Total duration must equal {$this->splitTotal} hrs (current: {$used}).");
            return;
        }

        SubActivity::where('activity_id', $this->splitActivityId)->delete();

        foreach ($this->splits as $i => $split) {
            SubActivity::create([
                'activity_id' => $this->splitActivityId,
                'duration'    => max(1, (int) $split['duration']),
                'order'       => $i,
            ]);
        }

        $this->splitModal = false;
        $this->success('Sub-activities saved.', position: 'toast-top toast-center');
    }

    // ── Space management ──────────────────────────────────────────────────────

    public function openSpaceManager(): void
    {
        $this->spaceQuery = '';
        $this->searchAvailableSpaces();
        $this->spaceModal = true;
    }

    public function updatedSpaceQuery(): void
    {
        $this->searchAvailableSpaces();
    }

    public function searchAvailableSpaces(): void
    {
        $program = $this->program();
        if (! $program) { $this->availableSpaces = []; return; }

        $this->availableSpaces = Space::where('client_id', $program->client_id)
            ->where(fn($q) => $q
                ->whereNull('type_id')
                ->orWhereHas('type', fn($q2) => $q2->where('is_theory', false)))
            ->when($this->spaceQuery, fn($q) => $q
                ->where(fn($q2) => $q2
                    ->where('name', 'ilike', "%{$this->spaceQuery}%")
                    ->orWhere('code', 'ilike', "%{$this->spaceQuery}%")))
            ->with(['claims' => fn($q) => $q->where('program_id', $program->id)])
            ->orderBy('name')->limit(20)->get()
            ->map(fn($s) => [
                'id'    => $s->id,
                'name'  => $s->name,
                'code'  => $s->code,
                'claim' => $s->claims->first(),
            ])
            ->toArray();
    }

    public function claimSpace(int $spaceId): void
    {
        $program = $this->program();
        if (! $program) return;

        SpaceClaim::updateOrCreate(
            ['space_id' => $spaceId, 'program_id' => $program->id],
            ['status' => 'pending', 'responded_at' => null]
        );

        $this->searchAvailableSpaces();
        $this->success('Claim request sent.', position: 'toast-top toast-center');
    }

    public function cancelClaim(int $claimId): void
    {
        $program = $this->program();
        if (! $program) return;

        SpaceClaim::where('id', $claimId)->where('program_id', $program->id)->delete();
        $this->searchAvailableSpaces();
        $this->loadSpaceOptions();
        $this->warning('Claim cancelled.', position: 'toast-top toast-center');
    }

    public function closeSpaceManager(): void
    {
        $this->loadSpaceOptions();
        $this->spaceModal = false;
    }

    // ── Tag management ────────────────────────────────────────────────────────

    public function openManageTags(): void
    {
        $this->newTagName = '';
        $this->tagModal   = true;
    }

    public function createTag(): void
    {
        $this->validate(['newTagName' => 'required|string|max:100']);
        $program = $this->program();
        if (! $program) return;

        ActivityTag::firstOrCreate(['program_id' => $program->id, 'name' => trim($this->newTagName)]);
        $this->newTagName = '';
        $this->loadOptions();
        $this->success('Tag created.', position: 'toast-top toast-center');
    }

    public function deleteTag(int $tagId): void
    {
        ActivityTag::find($tagId)?->delete();
        $this->tagIds = array_values(array_diff($this->tagIds, [$tagId]));
        $this->loadOptions();
        $this->warning('Tag deleted.', position: 'toast-top toast-center');
    }

    // ── Planning ──────────────────────────────────────────────────────────────

    public function openPlanning(): void
    {
        $this->reset(['planFilterYear', 'planFilterSemester']);
        $this->planPage = 1;
        $this->loadPlanSubjects();
        $this->planningModal = true;
    }

    public function loadPlanSubjects(): void
    {
        $program = $this->program();
        if (! $program || ! $this->semesterId) { return; }

        $all = Subject::where('program_id', $program->id)
            ->when($this->planFilterYear,     fn($q) => $q->where('curriculum_year_id', $this->planFilterYear))
            ->when($this->planFilterSemester, fn($q) => $q->where('semester', $this->planFilterSemester))
            ->orderBy('semester')->orderBy('code')
            ->get();

        $this->planTotal = $all->count();

        $this->planSubjects = $all
            ->slice(($this->planPage - 1) * $this->planPerPage, $this->planPerPage)
            ->map(fn($s) => [
                'id'       => $s->id,
                'code'     => $s->code,
                'name'     => $s->name,
                'credit'   => $s->credit,
                'semester' => $s->semester,
                'planned'  => ActivityPlanning::where('subject_id', $s->id)
                    ->where('program_id', $program->id)
                    ->where('semester_id', $this->semesterId)
                    ->exists(),
            ])
            ->values()
            ->toArray();
    }

    public function updatedPlanFilterYear(): void     { $this->planPage = 1; $this->loadPlanSubjects(); }
    public function updatedPlanFilterSemester(): void { $this->planPage = 1; $this->loadPlanSubjects(); }

    public function planPrevPage(): void { if ($this->planPage > 1) { $this->planPage--; $this->loadPlanSubjects(); } }
    public function planNextPage(): void { if ($this->planPage < ceil($this->planTotal / $this->planPerPage)) { $this->planPage++; $this->loadPlanSubjects(); } }

    public function togglePlanning(int $subjectId): void
    {
        $program = $this->program();
        if (! $program || ! $this->semesterId) return;

        $existing = ActivityPlanning::withTrashed()
            ->where('subject_id', $subjectId)
            ->where('program_id', $program->id)
            ->where('semester_id', $this->semesterId)
            ->first();

        if ($existing && ! $existing->trashed()) {
            $existing->delete();
        } elseif ($existing && $existing->trashed()) {
            $existing->restore();
        } else {
            ActivityPlanning::create([
                'subject_id'  => $subjectId,
                'program_id'  => $program->id,
                'semester_id' => $this->semesterId,
            ]);
        }

        $this->loadPlanSubjects();
        $this->searchSubjects();
    }

    public function planAllOdd(): void
    {
        $program = $this->program();
        if (! $program || ! $this->semesterId) return;

        Subject::where('program_id', $program->id)
            ->whereNotNull('semester')
            ->whereRaw('semester % 2 = 1')
            ->when($this->planFilterYear, fn($q) => $q->where('curriculum_year_id', $this->planFilterYear))
            ->pluck('id')
            ->each(function ($id) use ($program) {
                $record = ActivityPlanning::withTrashed()->firstOrCreate(
                    ['subject_id' => $id, 'program_id' => $program->id, 'semester_id' => $this->semesterId]
                );
                if ($record->trashed()) $record->restore();
            });

        $this->loadPlanSubjects();
        $this->searchSubjects();
    }

    public function planAllEven(): void
    {
        $program = $this->program();
        if (! $program || ! $this->semesterId) return;

        Subject::where('program_id', $program->id)
            ->whereNotNull('semester')
            ->whereRaw('semester % 2 = 0')
            ->when($this->planFilterYear, fn($q) => $q->where('curriculum_year_id', $this->planFilterYear))
            ->pluck('id')
            ->each(function ($id) use ($program) {
                $record = ActivityPlanning::withTrashed()->firstOrCreate(
                    ['subject_id' => $id, 'program_id' => $program->id, 'semester_id' => $this->semesterId]
                );
                if ($record->trashed()) $record->restore();
            });

        $this->loadPlanSubjects();
        $this->searchSubjects();
    }

    // ─────────────────────────────────────────────────────────────────────────

    public function delete(): void
    {
        Activity::findOrFail($this->deleteId)->delete();
        $this->delModal = false;
        $this->deleteId = null;
        $this->warning('Activity deleted.', position: 'toast-top toast-center');
    }

    public function with(): array
    {
        $program = $this->program();

        $subjects = $program
            ? Subject::with(['activities' => fn($q) => $q
                ->when($this->semesterId, fn($q) => $q->whereHas('planning', fn($p) =>
                    $p->where('semester_id', $this->semesterId)))
                ->with(['teachers', 'type', 'students', 'subActivities'])
              ])
                ->where('program_id', $program->id)
                ->when($this->semesterId, fn($q) => $q->whereHas('activityPlannings', fn($p) =>
                    $p->where('semester_id', $this->semesterId)->where('program_id', $program->id)))
                ->when($this->search, fn($q) => $q
                    ->where('name', 'ilike', "%{$this->search}%")
                    ->orWhere('code', 'ilike', "%{$this->search}%"))
                ->orderBy('semester')->orderBy('code')
                ->paginate(15)
            : collect();

        $activities = ($program && $this->view === 'all')
            ? Activity::with(['planning.subject', 'type', 'teachers', 'students'])
                ->where('program_id', $program->id)
                ->when($this->semesterId, fn($q) => $q->whereHas('planning', fn($p) => $p->where('semester_id', $this->semesterId)))
                ->when($this->search, fn($q) => $q->whereHas('planning', fn($p) => $p->whereHas('subject',
                    fn($s) => $s->where('name', 'ilike', "%{$this->search}%")
                                ->orWhere('code', 'ilike', "%{$this->search}%"))))
                ->paginate(15)
                ->through(fn($a) => tap($a, fn($item) => [
                    $item->subject_nm  = $a->planning?->subject?->code . ' — ' . $a->planning?->subject?->name,
                    $item->type_nm     = $a->type?->name ?? '-',
                    $item->teachers_nm = $a->teachers->pluck('code')->filter()->implode(', ') ?: '-',
                    $item->students_nm = $a->students->pluck('name')->implode(', ') ?: '-',
                ]))
            : collect();

        $myClaims = $program
            ? SpaceClaim::where('program_id', $program->id)
                ->with('space:id,name,code')
                ->orderByRaw("CASE status WHEN 'accepted' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END")
                ->orderBy('created_at', 'desc')
                ->get()
            : collect();

        return compact('subjects', 'activities', 'myClaims');
    }
}; ?>

<div>
    <x-header title="Activities" subtitle="Manage course sessions & assignments" separator>
        <x-slot:actions>
            @if(count($academicYearOptions))
            <x-select wire:model.live="academicYearId" :options="$academicYearOptions"
                      placeholder="Academic Year" class="w-36" />
            @endif
            @if(count($semesterOptions))
            <x-select wire:model.live="semesterId" :options="$semesterOptions"
                      placeholder="Semester" class="w-48" />
            @endif
            <x-input placeholder="Search subject..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
            <div class="join">
                <x-button label="By Subject" class="btn-sm join-item {{ $view === 'subject' ? 'btn-primary' : 'btn-ghost' }}"
                          wire:click="$set('view','subject')" />
                <x-button label="All"        class="btn-sm join-item {{ $view === 'all'     ? 'btn-primary' : 'btn-ghost' }}"
                          wire:click="$set('view','all')" />
            </div>
            @if($semesterId)
            <x-button label="Planning" icon="o-calendar-days" class="btn-ghost btn-sm" wire:click="openPlanning" />
            @endif
            <x-button label="Add" icon="o-plus" class="btn-primary" wire:click="openCreate()" />
        </x-slot:actions>
    </x-header>

    @if($view === 'subject')
    <x-card>
        <x-table :striped="true" :headers="$headers" :rows="$subjects" with-pagination container-class="overflow-hidden" class="table-fixed">

            @scope('cell_semester', $row)
                <div class="text-center">{{ $row->semester ?? '-' }}</div>
            @endscope

            @scope('cell_classes', $row)
                <div class="flex flex-wrap gap-y-1 gap-x-2">
                    @forelse($row->activities as $activity)
                        @php
                            $teachers   = $activity->teachers->pluck('code')->filter()->implode('|');
                            $groups     = $activity->students->pluck('name')->implode('|');
                            $tooltip    = $activity->type?->name ?? '';
                            $active     = $activity->active;
                            $subs       = $activity->subActivities;
                            $durationStr = $subs->count() > 1
                                ? $subs->pluck('duration')->implode('+')
                                : (string) $activity->duration;
                        @endphp
                        <div class="group flex flex-col items-start gap-0">
                            <div class="flex items-center gap-1">
                                <div class="tooltip tooltip-top" data-tip="{{ $active ? 'Set inactive' : 'Set active' }}">
                                    <button wire:click="toggleActive({{ $activity->id }})"
                                            class="w-2 h-2 rounded-full shrink-0 {{ $active ? 'bg-primary' : 'bg-base-content/20' }}"></button>
                                </div>
                                <div class="tooltip tooltip-top" data-tip="{{ $tooltip }}">
                                    <x-badge value="{{ ($teachers ?: '?') . ($groups ? ' ('.$groups.')' : ' (no student)') . ' ' . $durationStr }}"
                                             class="{{ $active ? 'badge-primary badge-dash' : 'badge-dash !bg-base-200 !text-base-content/40 !border-base-content/20' }} {{ !$groups ? 'border-warning text-warning' : '' }}" />
                                </div>
                            </div>
                            <div class="flex items-center h-0 overflow-hidden group-hover:h-auto group-hover:overflow-visible transition-all">
                                <button wire:click="openEdit({{ $activity->id }})"
                                        class="btn btn-ghost btn-xs btn-square" title="Edit">
                                    <x-icon name="o-pencil" class="w-3 h-3" />
                                </button>
                                <button wire:click="openSplit({{ $activity->id }})"
                                        class="btn btn-ghost btn-xs btn-square" title="Split">
                                    <x-icon name="o-scissors" class="w-3 h-3" />
                                </button>
                                <button wire:click="confirmDelete({{ $activity->id }})"
                                        class="btn btn-ghost btn-xs btn-square text-error" title="Delete">
                                    <x-icon name="o-trash" class="w-3 h-3" />
                                </button>
                            </div>
                        </div>
                    @empty
                        <span class="text-base-content/30 text-xs italic">no activities</span>
                    @endforelse
                </div>
            @endscope

            @scope('cell_action', $row)
                <x-button icon="o-plus-circle" class="btn-ghost btn-sm btn-square"
                          wire:click="openCreate({{ $row->id }})" tooltip="Add activity" />
            @endscope

        </x-table>
    </x-card>
    @else
    <x-card>
        @php
        $allHeaders = [
            ['key' => 'subject_nm',  'label' => 'Subject',   'class' => 'w-4/12'],
            ['key' => 'type_nm',     'label' => 'Type',      'class' => 'w-1/12'],
            ['key' => 'duration',    'label' => 'Duration',  'class' => 'w-1/12 text-center'],
            ['key' => 'teachers_nm', 'label' => 'Teachers',  'class' => 'w-2/12'],
            ['key' => 'students_nm', 'label' => 'Groups',    'class' => 'w-2/12'],
            ['key' => 'action',      'label' => '',          'class' => 'w-2/12 text-right'],
        ];
        @endphp
        <x-table :striped="true" :headers="$allHeaders" :rows="$activities" with-pagination container-class="overflow-hidden" class="table-fixed">
            @scope('cell_duration', $row)
                <div class="text-center">{{ $row->duration }} hr</div>
            @endscope
            @scope('cell_action', $row)
                <div class="flex justify-end gap-1">
                    <x-button icon="o-pencil" class="btn-ghost btn-sm btn-square"
                              wire:click="openEdit({{ $row->id }})" tooltip="Edit" />
                    <x-button icon="o-trash"  class="btn-ghost btn-sm btn-square text-error"
                              wire:click="confirmDelete({{ $row->id }})" tooltip="Delete" />
                </div>
            @endscope
        </x-table>
    </x-card>
    @endif

    {{-- Planning Modal --}}
    <x-modal wire:model="planningModal" title="Subject Planning"
             separator class="modal-bottom" box-class="!max-w-3xl mx-auto !rounded-t-2xl !mb-14">
        <div class="space-y-4">
            <input type="text" class="w-0 h-0 opacity-0 absolute pointer-events-none" autofocus />

            @if(! $semesterId)
                <x-alert title="Select a semester first" icon="o-exclamation-triangle" class="alert-warning" />
            @else
                {{-- Filters + bulk actions --}}
                <div class="flex flex-wrap items-end gap-3">
                    <x-select wire:model.live="planFilterYear"
                              :options="$curriculumYearOptions" placeholder="All Curricula" class="w-36" />
                    <x-select wire:model.live="planFilterSemester"
                              :options="$subjectSemesterOptions" placeholder="All Semesters" class="w-36" />
                    <x-button label="Plan All Odd"  icon="o-check" class="btn-sm btn-ghost"
                              wire:click="planAllOdd"  tooltip="Plan all odd-semester subjects" />
                    <x-button label="Plan All Even" icon="o-check" class="btn-sm btn-ghost"
                              wire:click="planAllEven" tooltip="Plan all even-semester subjects" />
                </div>

                {{-- Subject list --}}
                <div class="divide-y divide-base-200">
                    @forelse($planSubjects as $s)
                        <div class="flex items-center justify-between py-2 {{ $s['planned'] ? 'bg-primary/5' : '' }}">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    @if($s['semester'])
                                        <span class="text-xs text-base-content/40 w-4 text-center shrink-0">{{ $s['semester'] }}</span>
                                    @endif
                                    <span class="font-mono text-xs text-base-content/60 shrink-0">{{ $s['code'] }}</span>
                                    <span class="text-sm truncate">{{ $s['name'] }}</span>
                                    <span class="text-xs text-base-content/30 shrink-0">{{ $s['credit'] }} SKS</span>
                                </div>
                            </div>
                            <x-button
                                :icon="$s['planned'] ? 'o-check-circle' : 'o-plus-circle'"
                                :class="'btn-sm btn-square ' . ($s['planned'] ? 'btn-primary' : 'btn-ghost')"
                                wire:click="togglePlanning({{ $s['id'] }})"
                                :tooltip="$s['planned'] ? 'Remove from plan' : 'Add to plan'" />
                        </div>
                    @empty
                        <p class="text-center text-sm text-base-content/40 py-6 italic">No subjects found.</p>
                    @endforelse
                </div>

                {{-- Pagination --}}
                @if($planTotal > $planPerPage)
                <div class="flex items-center justify-between pt-2">
                    <span class="text-xs text-base-content/40">{{ $planTotal }} subjects</span>
                    <div class="join">
                        <x-button class="btn-xs join-item" icon="o-chevron-left"
                                  wire:click="planPrevPage" :disabled="$planPage <= 1" />
                        <span class="join-item btn btn-xs btn-ghost pointer-events-none">
                            {{ $planPage }} / {{ max(1, (int) ceil($planTotal / $planPerPage)) }}
                        </span>
                        <x-button class="btn-xs join-item" icon="o-chevron-right"
                                  wire:click="planNextPage" :disabled="$planPage >= ceil($planTotal / $planPerPage)" />
                    </div>
                </div>
                @endif
            @endif
        </div>
        <x-slot:actions>
            <x-button label="Done" icon="o-check" class="btn-primary" wire:click="$set('planningModal', false)" />
        </x-slot:actions>
    </x-modal>

    {{-- Add/Edit Activity Modal --}}
    <x-modal wire:model="modal" :title="$editId ? 'Edit Activity' : 'Add Activity'"
             separator class="modal-bottom" box-class="!max-w-2xl mx-auto !rounded-t-2xl !mb-14">
        <x-form wire:submit="save" class="space-y-4">
            <input type="text" class="w-0 h-0 opacity-0 absolute pointer-events-none" autofocus />
            <div class="flex items-end gap-3">
                <div class="flex-1">
                    <x-choices label="Subject" single searchable :search-function="'searchSubjects'"
                               wire:model="subject_id" :options="$subjectOptions"
                               placeholder="Select subject" required />
                </div>
                <div class="pb-1">
                    <x-toggle label="Active" wire:model="active" class="toggle-primary" />
                </div>
            </div>
            <div class="grid grid-cols-[1fr_auto_1fr] gap-3 items-start">
                <x-choices label="Activity Type" single searchable wire:model="type_id"
                           :options="$typeOptions" placeholder="-- Select type --" />
                <x-input label="Extra Duration (hrs)" wire:model.live="extraDuration"
                         type="number" min="0" max="8" class="w-28"
                         :hint="$subjectCredit ? $subjectCredit.'+'. $extraDuration.'='.($subjectCredit + $extraDuration) : ''" />
                <div>
                    <x-choices label="Tags" searchable wire:model="tagIds" :options="$tagOptions" placeholder="Select tags..." />
                    <button type="button" wire:click="openManageTags"
                            class="text-xs text-primary hover:underline mt-1 block">Manage tags</button>
                </div>
            </div>
            <x-choices label="Teachers" searchable :search-function="'searchTeachers'"
                       wire:model="teacherIds" :options="$teacherOptions" placeholder="Select teachers..." />
            <x-choices label="Student Groups" searchable :search-function="'searchStudents'"
                       wire:model="studentIds" :options="$studentOptions" placeholder="Select groups..." />
            <div class="w-3/4">
                <x-choices label="Space / Room" single wire:model="space_id"
                           :options="$spaceOptions" placeholder="— None —" clearable />
                @if(count($spaceOptions) === 0)
                    <button type="button" wire:click="openSpaceManager"
                            class="text-xs text-warning hover:underline mt-1 block">No spaces claimed yet — Manage Spaces</button>
                @else
                    <button type="button" wire:click="openSpaceManager"
                            class="text-xs text-primary hover:underline mt-1 block">Manage spaces</button>
                @endif
            </div>
            <x-slot:actions>
                <x-button label="Cancel" icon="o-x-circle"     wire:click="$set('modal', false)" />
                <x-button label="Save"   icon="o-check-circle" type="submit" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    {{-- Space Manager Modal --}}
    <x-modal wire:model="spaceModal" title="My Spaces"
             separator class="modal-bottom" box-class="!max-w-lg mx-auto !rounded-t-2xl !mb-14">
        <div class="space-y-4">
            <input type="text" class="w-0 h-0 opacity-0 absolute pointer-events-none" autofocus />

            {{-- My claims list --}}
            @if($myClaims->isNotEmpty())
                <div class="divide-y divide-base-200">
                    @foreach($myClaims as $claim)
                        <div class="flex items-center justify-between py-2">
                            <div class="flex items-center gap-2">
                                @if($claim->status === 'accepted')
                                    <x-badge value="Accepted" class="badge-success badge-xs" />
                                @elseif($claim->status === 'pending')
                                    <x-badge value="Pending" class="badge-warning badge-xs" />
                                @else
                                    <x-badge value="Rejected" class="badge-error badge-xs" />
                                @endif
                                <span class="text-sm font-medium">{{ $claim->space?->name }}</span>
                                @if($claim->space?->code)
                                    <span class="text-xs text-base-content/40">{{ $claim->space->code }}</span>
                                @endif
                            </div>
                            <x-button icon="o-x-mark" class="btn-ghost btn-xs btn-square text-error"
                                      wire:click="cancelClaim({{ $claim->id }})"
                                      tooltip="{{ $claim->status === 'accepted' ? 'Remove claim' : 'Cancel request' }}" />
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-base-content/40 text-center py-2 italic">No spaces claimed yet.</p>
            @endif

            {{-- Search & claim new spaces --}}
            <div class="border border-base-200 rounded-xl p-4 space-y-3">
                <p class="text-sm font-semibold text-base-content/70">Find & Claim Spaces</p>
                <x-input wire:model.live.debounce="spaceQuery" placeholder="Search by name or code..."
                         icon="o-magnifying-glass" clearable />
                <div class="divide-y divide-base-200 max-h-60 overflow-y-auto">
                    @forelse($availableSpaces as $s)
                        @php $claim = $s['claim'] ?? null; @endphp
                        <div class="flex items-center justify-between py-2">
                            <div>
                                <span class="text-sm font-medium">{{ $s['name'] }}</span>
                                @if($s['code'])
                                    <span class="text-xs text-base-content/40 ml-1">{{ $s['code'] }}</span>
                                @endif
                            </div>
                            @if($claim && $claim->status === 'accepted')
                                <x-badge value="Accepted" class="badge-success badge-xs" />
                            @elseif($claim && $claim->status === 'pending')
                                <x-badge value="Pending" class="badge-warning badge-xs" />
                            @else
                                <x-button label="Claim" icon="o-hand-raised" class="btn-primary btn-xs"
                                          wire:click="claimSpace({{ $s['id'] }})" />
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-base-content/40 text-center py-3 italic">No spaces found.</p>
                    @endforelse
                </div>
            </div>
        </div>
        <x-slot:actions>
            <x-button label="Done" icon="o-check" class="btn-primary"
                      wire:click="closeSpaceManager" />
        </x-slot:actions>
    </x-modal>

    {{-- Delete Confirm --}}
    <x-modal wire:model="delModal" title="Delete Activity"
             box-class="!max-w-sm">
        <p class="text-base-content/70 text-sm">Delete this activity? Teacher and student assignments will also be removed.</p>
        <x-slot:actions>
            <x-button label="Cancel" icon="o-x-circle" wire:click="$set('delModal', false)" />
            <x-button label="Delete" icon="o-trash"    class="btn-error" wire:click="delete" />
        </x-slot:actions>
    </x-modal>

    {{-- Split Sub-Activity Modal --}}
    <x-modal wire:model="splitModal" title="Split Activity"
             separator class="modal-bottom" box-class="!max-w-sm mx-auto !rounded-t-2xl !mb-14">
        <div class="space-y-3">
            <p class="text-sm text-base-content/60">
                Total duration: <span class="font-semibold text-base-content">{{ $splitTotal }} hrs</span>
                &nbsp;|&nbsp; Used:
                <span x-data x-text="$wire.splits.reduce((s,r)=>s+(+r.duration||0),0)"
                      :class="$wire.splits.reduce((s,r)=>s+(+r.duration||0),0)==={{ $splitTotal }} ? 'text-success font-semibold' : 'text-error font-semibold'">0</span> hrs
            </p>

            @error('splits')
                <p class="text-error text-sm">{{ $message }}</p>
            @enderror

            @foreach($splits as $i => $split)
                <div class="flex items-center gap-2">
                    <span class="text-xs text-base-content/40 w-4">{{ $i + 1 }}</span>
                    <x-input wire:model.live="splits.{{ $i }}.duration"
                             type="number" min="1" max="{{ $splitTotal }}"
                             class="flex-1" placeholder="Duration (hrs)" />
                    @if(count($splits) > 1)
                        <x-button icon="o-x-mark" class="btn-ghost btn-sm btn-square text-error"
                                  wire:click="removeSplit({{ $i }})" />
                    @endif
                </div>
            @endforeach

            <x-button label="Add Sub-Activity" icon="o-plus" class="btn-ghost btn-sm w-full"
                      wire:click="addSplit" />
        </div>
        <x-slot:actions>
            <x-button label="Cancel" icon="o-x-circle"     wire:click="$set('splitModal', false)" />
            <x-button label="Save"   icon="o-check-circle" class="btn-primary" wire:click="saveSplits" spinner="saveSplits" />
        </x-slot:actions>
    </x-modal>

    {{-- Manage Tags Modal --}}
    <x-modal wire:model="tagModal" title="Manage Tags"
             separator class="modal-bottom" box-class="!max-w-sm mx-auto !rounded-t-2xl !mb-14">
        <div class="space-y-3">
            {{-- All program tags --}}
            <div class="flex flex-wrap gap-1.5 min-h-8">
                @forelse($tagOptions as $tag)
                    <div class="badge badge-outline gap-1">
                        {{ $tag['name'] }}
                        <button wire:click="deleteTag({{ $tag['id'] }})"
                                wire:confirm="Delete tag '{{ $tag['name'] }}'?"
                                class="hover:text-error ml-0.5 leading-none">×</button>
                    </div>
                @empty
                    <p class="text-xs text-base-content/40 italic">No tags yet.</p>
                @endforelse
            </div>

            <div class="divider my-1"></div>

            {{-- Add new tag --}}
            <x-form wire:submit="createTag" class="flex gap-2 items-end">
                <div class="flex-1">
                    <x-input label="New tag" wire:model="newTagName"
                             placeholder="e.g. Lab, Sports, Music" />
                </div>
                <x-button icon="o-plus" type="submit" class="btn-primary btn-sm mb-0.5"
                          spinner="createTag" />
            </x-form>
        </div>
        <x-slot:actions>
            <x-button label="Done" icon="o-check" class="btn-primary" wire:click="$set('tagModal', false)" />
        </x-slot:actions>
    </x-modal>

    <x-toast />
</div>

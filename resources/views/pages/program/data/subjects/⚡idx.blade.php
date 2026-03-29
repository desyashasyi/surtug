<?php

use App\Exports\FetNet\SubjectsTemplateExport;
use App\Jobs\FetNet\SubjectsImportJob;
use App\Models\FetNet\CurriculumYear;
use App\Models\FetNet\Program;
use App\Models\FetNet\Specialization;
use App\Models\FetNet\Subject;
use App\Models\FetNet\SubjectType;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.program')] class extends Component
{
    use WithPagination, WithFileUploads, Toast;

    public string $search      = '';
    public ?int   $filterYear  = null;   // null = All curricula
    public bool   $modal       = false;
    public bool   $delModal    = false;
    public bool   $typeModal   = false;
    public bool   $yearModal   = false;
    public bool   $specModal   = false;
    public bool   $importModal = false;
    public ?int   $editId      = null;
    public ?int   $deleteId    = null;

    // Subject fields
    public string $code               = '';
    public string $name               = '';
    public int    $credit             = 2;
    public ?int   $semester           = null;
    public ?int   $curriculum_year_id = null;
    public ?int   $specialization_id  = null;
    public ?int   $type_id            = null;

    // Subject type fields
    public string $typeCode = '';
    public string $typeName = '';

    // Curriculum year fields
    public string $yearValue = '';
    public string $yearDesc  = '';

    // Specialization fields
    public ?int   $editSpecId = null;
    public string $specCode   = '';
    public string $specAbbrev = '';
    public string $specName   = '';

    // Curriculum year edit
    public ?int   $editYearId = null;

    // Subject type edit
    public ?int   $editTypeId = null;

    // Import
    public mixed $importFile = null;
    public bool  $importing  = false;

    public array $specializationOptions = [];
    public array $typeOptions           = [];
    public array $curriculumYearOptions = [];
    public array $semesterOptions       = [
        ['id' => 1, 'name' => '1'], ['id' => 2, 'name' => '2'],
        ['id' => 3, 'name' => '3'], ['id' => 4, 'name' => '4'],
        ['id' => 5, 'name' => '5'], ['id' => 6, 'name' => '6'],
        ['id' => 7, 'name' => '7'], ['id' => 8, 'name' => '8'],
    ];

    public array $headers = [
        ['key' => 'curriculum_yr',     'label' => 'Curriculum', 'class' => 'w-1/12 text-center'],
        ['key' => 'code',              'label' => 'Code',       'class' => 'w-1/12'],
        ['key' => 'name',              'label' => 'Name',       'class' => 'w-4/12'],
        ['key' => 'credit',            'label' => 'SKS',        'class' => 'w-1/12 text-center'],
        ['key' => 'semester',          'label' => 'Sem',        'class' => 'w-1/12 text-center'],
        ['key' => 'specialization_nm', 'label' => 'Specialize', 'class' => 'w-1/12'],
        ['key' => 'type_nm',           'label' => 'Type',       'class' => 'w-1/12'],
        ['key' => 'action',            'label' => '',           'class' => 'w-1/12 text-right'],
    ];

    private function program(): ?Program
    {
        return Program::where('user_id', auth()->id())->first();
    }

    public function mount(): void { $this->loadOptions(); }

    private function loadOptions(): void
    {
        $program = $this->program();
        if (! $program) return;

        $this->specializationOptions = Specialization::where('program_id', $program->id)
            ->orderBy('code')->get()
            ->map(fn($s) => [
                'id'     => $s->id,
                'name'   => "{$s->code} | {$s->name}",
                'code'   => $s->code,
                'abbrev' => $s->abbrev,
                'sname'  => $s->name,
            ])
            ->toArray();

        $this->typeOptions = SubjectType::where('program_id', $program->id)
            ->orderBy('code')->get()
            ->map(fn($t) => ['id' => $t->id, 'name' => "{$t->code} | {$t->name}"])
            ->toArray();

        $this->curriculumYearOptions = CurriculumYear::where('program_id', $program->id)
            ->orderByDesc('year')->get()
            ->map(fn($y) => ['id' => $y->id, 'name' => $y->year, 'description' => $y->description])
            ->toArray();
    }

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedFilterYear(): void { $this->resetPage(); }

    public function openCreate(): void
    {
        $this->reset(['code', 'name', 'credit', 'semester', 'curriculum_year_id', 'specialization_id', 'type_id', 'editId']);
        $this->credit = 2;
        $this->curriculum_year_id = $this->filterYear; // pre-fill from active filter
        $this->loadOptions();
        $this->modal = true;
    }

    public function openEdit(int $id): void
    {
        $s                        = Subject::findOrFail($id);
        $this->editId             = $id;
        $this->code               = $s->code;
        $this->name               = $s->name;
        $this->credit             = $s->credit;
        $this->semester           = $s->semester;
        $this->curriculum_year_id = $s->curriculum_year_id;
        $this->specialization_id  = $s->specialization_id;
        $this->type_id            = $s->type_id;
        $this->loadOptions();
        $this->modal = true;
    }

    protected function rules(): array
    {
        $unique = 'required|unique:fetnet_subject,code';
        if ($this->editId) $unique .= ',' . $this->editId;
        return [
            'code'   => $unique,
            'name'   => 'required',
            'credit' => 'required|integer|min:1|max:10',
        ];
    }

    public function save(): void
    {
        $this->validate();
        $data = [
            'code'               => $this->code,
            'name'               => $this->name,
            'credit'             => $this->credit,
            'semester'           => $this->semester,
            'curriculum_year_id' => $this->curriculum_year_id,
            'specialization_id'  => $this->specialization_id,
            'type_id'            => $this->type_id,
        ];

        if ($this->editId) {
            Subject::findOrFail($this->editId)->update($data);
            $this->success('Subject updated.', position: 'toast-top toast-center');
        } else {
            Subject::create(array_merge($data, ['program_id' => $this->program()->id]));
            $this->success('Subject added.', position: 'toast-top toast-center');
        }

        $this->modal = false;
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId = $id;
        $this->delModal = true;
    }

    public function delete(): void
    {
        Subject::findOrFail($this->deleteId)->delete();
        $this->delModal = false;
        $this->deleteId = null;
        $this->warning('Subject deleted. Related activities also deleted.', position: 'toast-top toast-center');
    }

    // ── Subject Type ──────────────────────────────────────────────────────────

    public function openEditType(int $id): void
    {
        $t = SubjectType::findOrFail($id);
        $this->editTypeId = $id;
        $this->typeCode   = $t->code;
        $this->typeName   = $t->name;
        $this->typeModal  = true;
    }

    public function cancelEditType(): void
    {
        $this->reset(['editTypeId', 'typeCode', 'typeName']);
    }

    public function saveType(): void
    {
        $unique = 'required|unique:fetnet_subject_type,code';
        if ($this->editTypeId) $unique .= ',' . $this->editTypeId;
        $this->validate(['typeCode' => $unique, 'typeName' => 'required']);

        if ($this->editTypeId) {
            SubjectType::findOrFail($this->editTypeId)->update(['code' => $this->typeCode, 'name' => $this->typeName]);
            $this->success('Subject type updated.', position: 'toast-top toast-center');
        } else {
            SubjectType::create(['program_id' => $this->program()->id, 'code' => $this->typeCode, 'name' => $this->typeName]);
            $this->success('Subject type added.', position: 'toast-top toast-center');
        }
        $this->reset(['typeCode', 'typeName', 'editTypeId']);
        $this->typeModal = false;
        $this->loadOptions();
    }

    public function deleteType(int $id): void
    {
        SubjectType::find($id)?->delete();
        $this->loadOptions();
        $this->warning('Subject type removed.', position: 'toast-top toast-center');
    }

    // ── Curriculum Year ───────────────────────────────────────────────────────

    public function openYearModal(): void
    {
        $this->reset(['yearValue', 'yearDesc', 'editYearId']);
        $this->loadOptions();
        $this->yearModal = true;
    }

    public function openEditYear(int $id): void
    {
        $y = CurriculumYear::findOrFail($id);
        $this->editYearId = $id;
        $this->yearValue  = $y->year;
        $this->yearDesc   = $y->description ?? '';
    }

    public function saveYear(): void
    {
        $this->validate(['yearValue' => 'required|string|max:20']);
        if ($this->editYearId) {
            CurriculumYear::findOrFail($this->editYearId)->update([
                'year'        => trim($this->yearValue),
                'description' => trim($this->yearDesc) ?: null,
            ]);
            $this->success('Curriculum year updated.', position: 'toast-top toast-center');
        } else {
            CurriculumYear::firstOrCreate(
                ['program_id' => $this->program()->id, 'year' => trim($this->yearValue)],
                ['description' => trim($this->yearDesc) ?: null]
            );
            $this->success('Curriculum year added.', position: 'toast-top toast-center');
        }
        $this->reset(['yearValue', 'yearDesc', 'editYearId']);
        $this->loadOptions();
    }

    public function deleteYear(int $id): void
    {
        CurriculumYear::find($id)?->delete();
        if ($this->filterYear === $id) $this->filterYear = null;
        $this->loadOptions();
        $this->warning('Curriculum year removed.', position: 'toast-top toast-center');
    }

    // ── Specialization ────────────────────────────────────────────────────────

    public function openSpecModal(): void
    {
        $this->reset(['specCode', 'specAbbrev', 'specName', 'editSpecId']);
        $this->loadOptions();
        $this->specModal = true;
    }

    public function openEditSpec(int $id): void
    {
        $s = Specialization::findOrFail($id);
        $this->editSpecId  = $id;
        $this->specCode    = $s->code;
        $this->specAbbrev  = $s->abbrev ?? '';
        $this->specName    = $s->name;
    }

    public function saveSpecialization(): void
    {
        $this->validate([
            'specCode' => 'required|string|max:10',
            'specName' => 'required|string|max:100',
        ]);
        if ($this->editSpecId) {
            Specialization::findOrFail($this->editSpecId)->update([
                'code'   => strtoupper(trim($this->specCode)),
                'abbrev' => strtoupper(trim($this->specAbbrev)) ?: null,
                'name'   => trim($this->specName),
            ]);
            $this->success('Specialization updated.', position: 'toast-top toast-center');
        } else {
            Specialization::firstOrCreate(
                ['program_id' => $this->program()->id, 'code' => strtoupper(trim($this->specCode))],
                ['abbrev' => strtoupper(trim($this->specAbbrev)) ?: null, 'name' => trim($this->specName)]
            );
            $this->success('Specialization added.', position: 'toast-top toast-center');
        }
        $this->reset(['specCode', 'specAbbrev', 'specName', 'editSpecId']);
        $this->loadOptions();
    }

    public function deleteSpecialization(int $id): void
    {
        Specialization::find($id)?->delete();
        $this->loadOptions();
        $this->warning('Specialization removed.', position: 'toast-top toast-center');
    }

    // ── Import ────────────────────────────────────────────────────────────────

    public function downloadTemplate(): mixed
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new SubjectsTemplateExport(),
            'subjects_template.xlsx'
        );
    }

    public function import(): void
    {
        $this->validate(['importFile' => 'required|file|mimes:xlsx,xls|max:5120']);

        $program = $this->program();
        if (! $program) {
            $this->error('Program not found.', position: 'toast-top toast-center');
            return;
        }

        $ext      = $this->importFile->getClientOriginalExtension();
        $filename = 'subjects_' . uniqid() . '.' . $ext;
        $destDir  = storage_path('app/imports/subjects');
        $destPath = $destDir . '/' . $filename;

        if (! is_dir($destDir)) mkdir($destDir, 0775, true);
        copy($this->importFile->getRealPath(), $destPath);

        SubjectsImportJob::dispatch($destPath, $program->id);

        $this->reset('importFile');
        $this->importModal = false;
        $this->importing   = true;
        $this->info('Import queued. You will be notified when done.', position: 'toast-top toast-center');
    }

    public function getListeners(): array
    {
        return ['echo:subjects-import,.SubjectsImportEvent' => 'onImportDone'];
    }

    public function onImportDone(array $event): void
    {
        $this->importing = false;
        ($event['status'] ?? '') === 'success'
            ? $this->success($event['message'], position: 'toast-top toast-center')
            : $this->error($event['message'],   position: 'toast-top toast-center');
    }

    public function with(): array
    {
        $program = $this->program();
        return [
            'subjects' => $program
                ? Subject::with(['curriculumYear', 'specialization', 'type'])
                    ->where('program_id', $program->id)
                    ->when($this->filterYear, fn($q) => $q->where('curriculum_year_id', $this->filterYear))
                    ->when($this->search, fn($q) => $q
                        ->where('name', 'ilike', "%{$this->search}%")
                        ->orWhere('code', 'ilike', "%{$this->search}%"))
                    ->orderBy('semester')->orderBy('code')
                    ->paginate(15)
                    ->through(fn($s) => tap($s, fn($item) => [
                        $item->curriculum_yr     = $s->curriculumYear?->year ?? '—',
                        $item->specialization_nm = $s->specialization?->code ?? '—',
                        $item->type_nm           = $s->type?->code ?? '—',
                    ]))
                : collect(),
        ];
    }
}; ?>

<div>
    <x-header title="Subjects" subtitle="Manage course subjects" separator>
        <x-slot:actions>
            {{-- Curriculum year filter --}}
            <x-select wire:model.live="filterYear" :options="$curriculumYearOptions"
                      placeholder="All Curricula" class="w-40" />
            <x-input placeholder="Search..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
            <x-button label="Curriculum"    icon="o-calendar"     class="btn-ghost btn-sm"
                      wire:click="openYearModal" />
            <x-button label="Specialization" icon="o-academic-cap" class="btn-ghost btn-sm"
                      wire:click="openSpecModal" />
            <x-button label="Types"  icon="o-tag"           class="btn-ghost btn-sm"
                      wire:click="$set('typeModal', true)" />
            <x-button label="Import" icon="o-arrow-up-tray" class="btn-ghost btn-sm"
                      wire:click="$set('importModal', true)"
                      :disabled="$importing" :spinner="$importing" />
            <x-button label="Add" icon="o-plus" class="btn-primary" wire:click="openCreate" />
        </x-slot:actions>
    </x-header>

    <x-card>
        <x-table :striped="true" :headers="$headers" :rows="$subjects" with-pagination container-class="overflow-hidden" class="table-fixed">
            @scope('cell_curriculum_yr', $row)
                <div class="text-center">
                    @if($row->curriculum_yr !== '—')
                        {{ $row->curriculum_yr }}
                    @else
                        <span class="text-base-content/20">—</span>
                    @endif
                </div>
            @endscope
            @scope('cell_credit', $row)
                <div class="text-center">{{ $row->credit }}</div>
            @endscope
            @scope('cell_semester', $row)
                <div class="text-center">
                    @if($row->semester)
                        {{ $row->semester }}
                    @else
                        <span class="text-base-content/20">—</span>
                    @endif
                </div>
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

    {{-- Add/Edit Subject Modal --}}
    <x-modal wire:model="modal" :title="$editId ? 'Edit Subject' : 'Add Subject'"
             separator class="modal-bottom" box-class="!max-w-xl mx-auto !rounded-t-2xl !mb-14">
        <x-form wire:submit="save" class="space-y-4">
            <input type="text" class="w-0 h-0 opacity-0 absolute pointer-events-none" autofocus />
            <div class="flex gap-3 items-end">
                <div class="flex-1">
                    <x-input label="Subject Name" wire:model="name" placeholder="Electrical Machines I" required />
                </div>
                <div class="w-32">
                    <x-select label="Curriculum Year" wire:model="curriculum_year_id"
                              :options="$curriculumYearOptions" placeholder="—" />
                </div>
            </div>
            <div class="grid grid-cols-5 gap-3">
                <div class="col-span-2">
                    <x-input label="Code" wire:model="code" placeholder="ELC301" required />
                </div>
                <x-input label="SKS" wire:model="credit" type="number" min="1" max="10" required />
                <div class="col-span-1">
                    <x-select label="Semester" wire:model="semester"
                              :options="$semesterOptions" placeholder="-" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <x-choices label="Specialization" single searchable wire:model="specialization_id"
                           :options="$specializationOptions" placeholder="-- None --" />
                <x-choices label="Type" single searchable wire:model="type_id"
                           :options="$typeOptions" placeholder="-- None --" />
            </div>
            <x-slot:actions>
                <x-button label="Cancel" icon="o-x-circle"     wire:click="$set('modal', false)" />
                <x-button label="Save"   icon="o-check-circle" type="submit" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    {{-- Curriculum Year Manager --}}
    <x-modal wire:model="yearModal" title="Curriculum Years"
             separator class="modal-bottom" box-class="!max-w-sm mx-auto !rounded-t-2xl !mb-14">
        <div class="space-y-3">
            <div class="divide-y divide-base-200">
                @forelse($curriculumYearOptions as $y)
                    <div class="flex items-center justify-between py-2
                                {{ $editYearId === $y['id'] ? 'bg-base-200/60 -mx-2 px-2 rounded' : '' }}">
                        <div class="text-sm">
                            <span class="font-medium">{{ $y['name'] }}</span>
                            @if(!empty($y['description']))
                                <span class="text-base-content/30 mx-1">|</span>
                                <span class="text-base-content/50">{{ $y['description'] }}</span>
                            @endif
                        </div>
                        <div class="flex gap-1">
                            <x-button icon="o-pencil" class="btn-ghost btn-xs btn-square"
                                      wire:click="openEditYear({{ $y['id'] }})" tooltip="Edit" />
                            <x-button icon="o-trash" class="btn-ghost btn-xs btn-square text-error"
                                      wire:click="deleteYear({{ $y['id'] }})"
                                      wire:confirm="Delete this curriculum year? Subjects will be unlinked." />
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-base-content/40 italic py-2">No curriculum years yet.</p>
                @endforelse
            </div>

            <div class="divider my-1"></div>

            <x-form wire:submit="saveYear" class="space-y-3">
                @if($editYearId)
                    <p class="text-xs text-primary font-medium">Editing — <button type="button" wire:click="openYearModal" class="underline">cancel</button></p>
                @endif
                <div class="flex gap-2 items-end">
                    <x-input label="Year" wire:model="yearValue" placeholder="2020" class="w-28" />
                    <div class="flex-1">
                        <x-input label="Description (optional)" wire:model="yearDesc" placeholder="Kurikulum Merdeka" />
                    </div>
                    <x-button :icon="$editYearId ? 'o-check' : 'o-plus'" type="submit"
                              class="btn-primary btn-sm mb-0.5" spinner="saveYear" />
                </div>
            </x-form>
        </div>
        <x-slot:actions>
            <x-button label="Done" icon="o-check" class="btn-primary" wire:click="$set('yearModal', false)" />
        </x-slot:actions>
    </x-modal>

    {{-- Specialization Manager --}}
    <x-modal wire:model="specModal" title="Specializations"
             separator class="modal-bottom" box-class="!max-w-sm mx-auto !rounded-t-2xl !mb-14">
        <div class="space-y-3">
            <div class="divide-y divide-base-200">
                @forelse($specializationOptions as $s)
                    <div class="flex items-center justify-between py-2
                                {{ $editSpecId === $s['id'] ? 'bg-base-200/60 -mx-2 px-2 rounded' : '' }}">
                        <div class="text-sm">
                            <span class="font-medium">{{ $s['code'] }}</span>
                            @if(!empty($s['abbrev']))
                                <span class="text-base-content/30 mx-1">|</span>
                                <span class="text-base-content/60">{{ $s['abbrev'] }}</span>
                            @endif
                            <span class="text-base-content/30 mx-1">|</span>
                            <span class="text-base-content/50">{{ $s['sname'] }}</span>
                        </div>
                        <div class="flex gap-1">
                            <x-button icon="o-pencil" class="btn-ghost btn-xs btn-square"
                                      wire:click="openEditSpec({{ $s['id'] }})" tooltip="Edit" />
                            <x-button icon="o-trash" class="btn-ghost btn-xs btn-square text-error"
                                      wire:click="deleteSpecialization({{ $s['id'] }})"
                                      wire:confirm="Delete this specialization? Subjects using it will be unlinked." />
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-base-content/40 italic py-2">No specializations yet.</p>
                @endforelse
            </div>

            <div class="divider my-1"></div>

            <x-form wire:submit="saveSpecialization" class="space-y-3">
                @if($editSpecId)
                    <p class="text-xs text-primary font-medium">Editing — <button type="button" wire:click="openSpecModal" class="underline">cancel</button></p>
                @endif
                <div class="grid grid-cols-2 gap-3">
                    <x-input label="Code" wire:model="specCode" placeholder="POWER" required />
                    <x-input label="Abbrev (optional)" wire:model="specAbbrev" placeholder="PWR" />
                </div>
                <div class="flex gap-2 items-end">
                    <div class="flex-1">
                        <x-input label="Name" wire:model="specName" placeholder="Power Systems" required />
                    </div>
                    <x-button :icon="$editSpecId ? 'o-check' : 'o-plus'" type="submit"
                              class="btn-primary mb-0.5" spinner="saveSpecialization" />
                </div>
            </x-form>
        </div>
        <x-slot:actions>
            <x-button label="Done" icon="o-check" class="btn-primary" wire:click="$set('specModal', false)" />
        </x-slot:actions>
    </x-modal>

    {{-- Import Modal --}}
    <x-modal wire:model="importModal" title="Import Subjects from Excel"
             separator class="modal-bottom" box-class="!max-w-md mx-auto !rounded-t-2xl !mb-14">
        <div class="space-y-4">
            <input type="text" class="w-0 h-0 opacity-0 absolute pointer-events-none" autofocus />
            <x-alert title="Required: code, name"
                     description="Optional: credit (default 2), semester (1–8), curriculum_year (year string, auto-created if not found), specialization (code), type (code). Download template to get started."
                     icon="o-information-circle" class="alert-info" />
            <div class="flex justify-end">
                <x-button label="Download Template" icon="o-arrow-down-tray" class="btn-ghost btn-sm"
                          wire:click="downloadTemplate" />
            </div>
            <x-form wire:submit="import" class="space-y-4">
                <x-file wire:model="importFile" label="Excel File (.xlsx / .xls)"
                        accept=".xlsx,.xls" hint="Max 5MB" />
                <x-slot:actions>
                    <x-button label="Cancel" icon="o-x-circle"      wire:click="$set('importModal', false)" />
                    <x-button label="Import" icon="o-arrow-up-tray" type="submit" class="btn-primary" spinner="import" />
                </x-slot:actions>
            </x-form>
        </div>
    </x-modal>

    {{-- Subject Type Manager --}}
    <x-modal wire:model="typeModal" title="Subject Types"
             separator class="modal-bottom" box-class="!max-w-sm mx-auto !rounded-t-2xl !mb-14">
        <div class="space-y-3">
            <div class="divide-y divide-base-200">
                @forelse($typeOptions as $t)
                    @php [$tCode, $tName] = array_pad(explode(' | ', $t['name'], 2), 2, null); @endphp
                    <div class="flex items-center justify-between py-2
                                {{ $editTypeId === $t['id'] ? 'bg-base-200/60 -mx-2 px-2 rounded' : '' }}">
                        <div class="text-sm">
                            <span class="font-medium">{{ $tCode }}</span>
                            @if($tName)
                                <span class="text-base-content/30 mx-1">|</span>
                                <span class="text-base-content/50">{{ $tName }}</span>
                            @endif
                        </div>
                        <div class="flex gap-1">
                            <x-button icon="o-pencil" class="btn-ghost btn-xs btn-square"
                                      wire:click="openEditType({{ $t['id'] }})" tooltip="Edit" />
                            <x-button icon="o-trash" class="btn-ghost btn-xs btn-square text-error"
                                      wire:click="deleteType({{ $t['id'] }})"
                                      wire:confirm="Delete this subject type?" />
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-base-content/40 italic py-2">No subject types yet.</p>
                @endforelse
            </div>

            <div class="divider my-1"></div>

            <x-form wire:submit="saveType" class="space-y-3">
                @if($editTypeId)
                    <p class="text-xs text-primary font-medium">Editing — <button type="button" wire:click="cancelEditType" class="underline">cancel</button></p>
                @endif
                <div class="flex gap-2 items-end">
                    <x-input label="Code" wire:model="typeCode" placeholder="MK" class="w-20" required />
                    <div class="flex-1">
                        <x-input label="Type Name" wire:model="typeName" placeholder="Mata Kuliah Wajib" required />
                    </div>
                    <x-button :icon="$editTypeId ? 'o-check' : 'o-plus'" type="submit"
                              class="btn-primary btn-sm mb-0.5" spinner="saveType" />
                </div>
            </x-form>
        </div>
        <x-slot:actions>
            <x-button label="Done" icon="o-check" class="btn-primary" wire:click="$set('typeModal', false)" />
        </x-slot:actions>
    </x-modal>

    {{-- Delete Confirm --}}
    <x-modal wire:model="delModal" title="Delete Subject" box-class="!max-w-xs">
        <p class="text-base-content/70 text-sm">Delete this subject? All related activities will also be deleted.</p>
        <x-slot:actions>
            <x-button label="Cancel" icon="o-x-circle" wire:click="$set('delModal', false)" />
            <x-button label="Delete" icon="o-trash"    class="btn-error" wire:click="delete" />
        </x-slot:actions>
    </x-modal>

    <x-toast />
</div>

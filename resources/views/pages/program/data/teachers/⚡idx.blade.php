<?php

use App\Exports\FetNet\TeachersTemplateExport;
use App\Jobs\FetNet\TeachersImportJob;
use App\Models\FetNet\Cluster;
use App\Models\FetNet\Program;
use App\Models\FetNet\Teacher;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.program')] class extends Component
{
    use WithPagination, WithFileUploads, Toast;

    public string $search       = '';
    public bool   $modal        = false;
    public bool   $delModal     = false;
    public bool   $importModal  = false;
    public bool   $importing    = false;
    public ?int   $editId       = null;
    public ?int   $deleteId     = null;
    public mixed  $importFile   = null;

    public string $code           = '';
    public string $univ_code      = '';
    public string $employee_id    = '';
    public string $position       = '';
    public string $civil_grade    = '';
    public string $front_title    = '';
    public string $rear_title     = '';
    public string $name           = '';
    public string $email          = '';
    public string $phone          = '';
    public ?int   $studyProgramId = null;

    // Guest teacher search
    public bool   $guestModal   = false;
    public string $guestSearch  = '';
    public array  $guestResults = [];

    public array $studyProgramOptions = [];

    private function program(): ?Program
    {
        return Program::where('user_id', auth()->id())->first();
    }

    private function clusterProgramIds(Program $program): array
    {
        $entry = Cluster::where('program_id', $program->id)->first();
        if (! $entry) return [$program->id];

        return Cluster::where('cluster_base_id', $entry->cluster_base_id)
            ->pluck('program_id')
            ->toArray();
    }

    private function clusterProgramMap(Program $program): array
    {
        $ids = $this->clusterProgramIds($program);
        return Program::whereIn('id', $ids)->get(['id', 'abbrev'])
            ->mapWithKeys(fn($p) => [strtolower($p->abbrev) => $p->id])
            ->toArray();
    }

    private function loadStudyProgramOptions(Program $program): void
    {
        $ids = $this->clusterProgramIds($program);
        if (count($ids) <= 1) {
            $this->studyProgramOptions = [];
            return;
        }
        $this->studyProgramOptions = Program::whereIn('id', $ids)->orderBy('abbrev')->get(['id', 'abbrev', 'name'])
            ->map(fn($p) => ['id' => $p->id, 'name' => "{$p->abbrev} — {$p->name}"])
            ->toArray();
    }

    /** Resolve a unique 3-char code. If $requested is valid & unused → use it; otherwise auto-generate. */
    private function resolveCode(string $requested, string $name, array $usedCodes, ?string $existingCode = null): array
    {
        $requested = strtoupper(trim($requested));
        if (strlen($requested) === 3 && ! in_array($requested, $usedCodes)) {
            return [$requested, false];
        }
        // Keep existing valid code if available
        if ($existingCode && strlen($existingCode) === 3 && ! in_array(strtoupper($existingCode), $usedCodes)) {
            return [strtoupper($existingCode), false];
        }
        return [Teacher::generateCode($name, $usedCodes), true];
    }

    public function mount(): void
    {
        $program = $this->program();
        if ($program) {
            $this->studyProgramId = $program->id;
            $this->loadStudyProgramOptions($program);
        }
    }

    public function updatedSearch(): void { $this->resetPage(); }

    public function openCreate(): void
    {
        $this->reset(['code', 'univ_code', 'employee_id', 'position', 'civil_grade', 'front_title', 'rear_title', 'name', 'email', 'phone', 'editId']);
        $this->studyProgramId = $this->program()?->id;
        $this->modal = true;
    }

    public function openEdit(int $id): void
    {
        $t                    = Teacher::findOrFail($id);
        $this->editId         = $id;
        $this->studyProgramId = $t->program_id;
        $this->code           = $t->code ?? '';
        $this->univ_code      = $t->univ_code ?? '';
        $this->employee_id    = $t->employee_id ?? '';
        $this->position       = $t->position    ?? '';
        $this->civil_grade    = $t->civil_grade ?? '';
        $this->front_title    = $t->front_title ?? '';
        $this->rear_title     = $t->rear_title ?? '';
        $this->name           = $t->name;
        $this->email          = $t->email ?? '';
        $this->phone          = $t->phone ?? '';
        $this->modal          = true;
    }

    protected function rules(): array
    {
        return [
            'name'           => 'required',
            'studyProgramId' => 'required|exists:institution_program,id',
            'code'           => 'nullable|size:3|alpha',
            'univ_code'      => 'nullable|max:4',
            'employee_id'    => 'nullable',
            'position'       => 'nullable|max:100',
            'civil_grade'    => 'nullable|max:50',
            'front_title'    => 'nullable',
            'rear_title'     => 'nullable',
            'email'          => 'nullable|email',
            'phone'          => 'nullable',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $program = $this->program();
        $ids     = $this->clusterProgramIds($program);

        if (! in_array($this->studyProgramId, $ids)) {
            $this->addError('studyProgramId', 'Invalid program.');
            return;
        }

        // Resolve unique code within cluster
        $usedCodes = Teacher::whereIn('program_id', $ids)
            ->when($this->editId, fn($q) => $q->where('id', '!=', $this->editId))
            ->whereNotNull('code')
            ->pluck('code')
            ->map(fn($c) => strtoupper($c))
            ->toArray();

        $existing     = $this->editId ? Teacher::find($this->editId) : null;
        [$code, $autoGen] = $this->resolveCode($this->code, $this->name, $usedCodes, $existing?->code);

        $data = [
            'program_id'  => $this->studyProgramId,
            'code'        => $code,
            'univ_code'   => strtoupper(trim($this->univ_code)) ?: null,
            'employee_id' => $this->employee_id ?: null,
            'position'    => $this->position    ?: null,
            'civil_grade' => $this->civil_grade ?: null,
            'front_title' => $this->front_title ?: null,
            'rear_title'  => $this->rear_title  ?: null,
            'name'        => $this->name,
            'email'       => $this->email       ?: null,
            'phone'       => $this->phone       ?: null,
        ];

        if ($this->editId) {
            Teacher::findOrFail($this->editId)->update($data);
            $msg = 'Teacher updated.' . ($autoGen ? " Code auto-generated: {$code}." : '');
            $this->success($msg, position: 'toast-top toast-center');
        } else {
            Teacher::create($data);
            $msg = 'Teacher added.' . ($autoGen ? " Code auto-generated: {$code}." : '');
            $this->success($msg, position: 'toast-top toast-center');
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
        Teacher::findOrFail($this->deleteId)->delete();
        $this->delModal = false;
        $this->deleteId = null;
        $this->warning('Teacher deleted.', position: 'toast-top toast-center');
    }

    // ── Guest teacher ─────────────────────────────────────────────────────

    public function openGuestSearch(): void
    {
        $this->reset(['guestSearch', 'guestResults']);
        $this->guestModal = true;
    }

    public function searchGuest(): void
    {
        $program  = $this->program();
        $ownIds   = $this->clusterProgramIds($program);
        $guestIds = $program->guestTeachers()->pluck('teacher_id')->toArray();

        $this->guestResults = Teacher::with('program:id,abbrev,name')
            ->whereNotIn('program_id', $ownIds)
            ->whereNotIn('id', $guestIds)
            ->where(fn($q) => $q
                ->where('name',        'ilike', "%{$this->guestSearch}%")
                ->orWhere('code',       'ilike', "%{$this->guestSearch}%")
                ->orWhere('employee_id','ilike', "%{$this->guestSearch}%"))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'program_id', 'code', 'name', 'front_title', 'rear_title'])
            ->map(fn($t) => [
                'id'       => $t->id,
                'name'     => $t->full_name,
                'prodi'    => $t->program?->abbrev ?? '-',
                'prodi_nm' => $t->program?->name ?? '',
            ])
            ->toArray();
    }

    public function addGuestTeacher(int $teacherId): void
    {
        $this->program()->guestTeachers()->syncWithoutDetaching([$teacherId]);
        $this->searchGuest();
        $this->success('Guest teacher added.', position: 'toast-top toast-center');
    }

    public function removeGuestTeacher(int $teacherId): void
    {
        $this->program()->guestTeachers()->detach($teacherId);
        $this->success('Guest teacher removed.', position: 'toast-top toast-center');
    }

    // ── Import ────────────────────────────────────────────────────────────

    public function downloadTemplate(): mixed
    {
        $abbrev = $this->program()?->abbrev ?? '';
        return \Maatwebsite\Excel\Facades\Excel::download(
            new TeachersTemplateExport($abbrev),
            'teachers_template.xlsx'
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
        $filename = 'teachers_' . uniqid() . '.' . $ext;
        $destDir  = storage_path('app/imports/teachers');
        $destPath = $destDir . '/' . $filename;

        if (! is_dir($destDir)) mkdir($destDir, 0775, true);
        copy($this->importFile->getRealPath(), $destPath);

        TeachersImportJob::dispatch($destPath, $program->id);

        $this->reset('importFile');
        $this->importModal = false;
        $this->importing   = true;
        $this->info('Import queued. You will be notified when done.', position: 'toast-top toast-center');
    }

    public function getListeners(): array
    {
        return ['echo:teachers-import,.TeachersImportEvent' => 'onImportDone'];
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
        $program   = $this->program();
        $ids       = $program ? $this->clusterProgramIds($program) : [];
        $inCluster = count($ids) > 1;

        $headers = [
            ['key' => 'code',      'label' => 'Code',      'class' => 'w-1/12'],
            ['key' => 'univ_code', 'label' => 'Univ Code', 'class' => 'w-1/12'],
            ['key' => 'full_name', 'label' => 'Name',      'class' => $inCluster ? 'w-3/12' : 'w-4/12'],
        ];
        if ($inCluster) {
            $headers[] = ['key' => 'study_program', 'label' => 'Program', 'class' => 'w-1/12'];
        }
        $headers[] = ['key' => 'guest_info', 'label' => 'Guest At', 'class' => 'w-1/12'];
        $headers[] = ['key' => 'email',      'label' => 'Email',    'class' => 'w-2/12 max-w-0 truncate'];
        $headers[] = ['key' => 'phone',      'label' => 'Phone',    'class' => 'w-1/12'];
        $headers[] = ['key' => 'action',     'label' => '',         'class' => 'w-2/12 text-right'];

        $guestHeaders = $headers; // same columns for guest table

        $ownTeachers = $program
            ? Teacher::with(['program:id,abbrev,name', 'guestPrograms:id,abbrev'])
                ->whereIn('program_id', $ids)
                ->when($this->search, fn($q) => $q
                    ->where('name',         'ilike', "%{$this->search}%")
                    ->orWhere('code',        'ilike', "%{$this->search}%")
                    ->orWhere('employee_id', 'ilike', "%{$this->search}%"))
                ->orderBy('name')
                ->paginate(15)
                ->through(fn($t) => tap($t, fn($item) => [
                    $item->full_name     = $t->full_name,
                    $item->study_program = $t->program?->abbrev ?? '-',
                    $item->program_name  = $t->program?->name  ?? '-',
                    $item->guest_abbrevs = $t->guestPrograms->pluck('abbrev')->toArray(),
                ]))
            : collect();

        $guestTeachers = $program
            ? $program->guestTeachers()
                ->with('program:id,abbrev,name')
                ->when($this->search, fn($q) => $q
                    ->where('fetnet_teacher.name',         'ilike', "%{$this->search}%")
                    ->orWhere('fetnet_teacher.code',        'ilike', "%{$this->search}%")
                    ->orWhere('fetnet_teacher.employee_id', 'ilike', "%{$this->search}%"))
                ->orderBy('fetnet_teacher.name')
                ->get()
                ->map(fn($t) => tap($t, fn($item) => [
                    $item->full_name     = $t->full_name,
                    $item->study_program = $t->program?->abbrev ?? '-',
                    $item->program_name  = $t->program?->name  ?? '-',
                    $item->guest_abbrevs = [],
                ]))
            : collect();

        return [
            'inCluster'    => $inCluster,
            'headers'      => $headers,
            'guestHeaders' => $guestHeaders,
            'ownTeachers'  => $ownTeachers,
            'guestTeachers'=> $guestTeachers,
        ];
    }
}; ?>

<div>
    <x-header title="Teachers" subtitle="Manage lecturers & instructors" separator>
        <x-slot:actions>
            <x-input placeholder="Search..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
            <x-button label="Import" icon="o-arrow-up-tray" class="btn-ghost btn-sm"
                      wire:click="$set('importModal', true)"
                      :disabled="$importing" :spinner="$importing" />
            <x-button label="Guest Teacher" icon="o-user-plus" class="btn-ghost btn-sm"
                      wire:click="openGuestSearch" />
            <x-button label="Add" icon="o-plus" class="btn-primary" wire:click="openCreate" />
        </x-slot:actions>
    </x-header>

    <x-card>
        <x-table :striped="true" :headers="$headers" :rows="$ownTeachers" with-pagination container-class="overflow-hidden" class="table-fixed">
            @scope('cell_guest_info', $row)
                @if(count($row->guest_abbrevs))
                    <div x-data="{ open: false }" class="relative">
                        <x-button label="{{ count($row->guest_abbrevs) }}" icon="o-building-office"
                                  class="btn-ghost btn-xs" x-on:click="open = !open" />
                        <div x-show="open" x-on:click.outside="open = false"
                             class="absolute z-50 left-0 top-6 bg-base-100 border border-base-200 rounded-lg shadow-lg p-2 min-w-max text-xs space-y-1">
                            @foreach($row->guest_abbrevs as $abbrev)
                                <div class="px-2 py-0.5">{{ $abbrev }}</div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <span class="text-base-content/20">—</span>
                @endif
            @endscope
            @scope('cell_action', $row)
                <div class="flex justify-end gap-1">
                    <div x-data="{ open: false, above: false }"
                         @click.outside="open = false"
                         class="relative">
                        <button @click="above = $el.getBoundingClientRect().top > window.innerHeight / 2; open = !open"
                                class="btn btn-ghost btn-sm btn-square" title="Detail">
                            <x-icon name="o-eye" class="w-4 h-4" />
                        </button>
                        <div x-show="open" x-cloak
                             :class="above ? 'bottom-full mb-1' : 'top-full mt-1'"
                             class="absolute right-0 z-50 w-72 bg-base-100 border border-base-200 rounded-xl shadow-xl p-4 text-xs space-y-1.5">
                            <p class="font-semibold text-sm text-base-content mb-2">{{ $row->full_name }}</p>
                            @php
                                $details = [
                                    ['Program',     $row->study_program . ' — ' . $row->program_name],
                                    ['Code',        $row->code],
                                    ['Univ Code',   $row->univ_code],
                                    ['NIP/NIDN',    $row->employee_id],
                                    ['Position',    $row->position],
                                    ['Civil Grade', $row->civil_grade],
                                    ['Email',       $row->email],
                                    ['Phone',       $row->phone],
                                ];
                            @endphp
                            @foreach($details as [$label, $val])
                                @if($val)
                                <div class="flex gap-2">
                                    <span class="text-base-content/40 w-24 shrink-0">{{ $label }}</span>
                                    <span class="text-base-content font-medium break-all">{{ $val }}</span>
                                </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                    <x-button icon="o-pencil" class="btn-ghost btn-sm btn-square"
                              wire:click="openEdit({{ $row->id }})" tooltip="Edit" />
                    <x-button icon="o-trash"  class="btn-ghost btn-sm btn-square text-error"
                              wire:click="confirmDelete({{ $row->id }})" tooltip="Delete" />
                </div>
            @endscope
        </x-table>
    </x-card>

    @if($guestTeachers->isNotEmpty())
    <x-card title="Guest Teachers" class="mt-4">
        <x-table :striped="true" :headers="$guestHeaders" :rows="$guestTeachers" container-class="overflow-hidden" class="table-fixed">
            @scope('cell_guest_info', $row)
                <x-badge value="Guest" class="badge-warning badge-sm badge-dash" />
            @endscope
            @scope('cell_action', $row)
                <div class="flex justify-end gap-1">
                    <div x-data="{ open: false, above: false }"
                         @click.outside="open = false"
                         class="relative">
                        <button @click="above = $el.getBoundingClientRect().top > window.innerHeight / 2; open = !open"
                                class="btn btn-ghost btn-sm btn-square" title="Detail">
                            <x-icon name="o-eye" class="w-4 h-4" />
                        </button>
                        <div x-show="open" x-cloak
                             :class="above ? 'bottom-full mb-1' : 'top-full mt-1'"
                             class="absolute right-0 z-50 w-72 bg-base-100 border border-base-200 rounded-xl shadow-xl p-4 text-xs space-y-1.5">
                            <p class="font-semibold text-sm text-base-content mb-2">{{ $row->full_name }}</p>
                            @php
                                $details = [
                                    ['Program',     $row->study_program . ' — ' . $row->program_name],
                                    ['Code',        $row->code],
                                    ['Univ Code',   $row->univ_code],
                                    ['NIP/NIDN',    $row->employee_id],
                                    ['Position',    $row->position],
                                    ['Civil Grade', $row->civil_grade],
                                    ['Email',       $row->email],
                                    ['Phone',       $row->phone],
                                ];
                            @endphp
                            @foreach($details as [$label, $val])
                                @if($val)
                                <div class="flex gap-2">
                                    <span class="text-base-content/40 w-24 shrink-0">{{ $label }}</span>
                                    <span class="text-base-content font-medium break-all">{{ $val }}</span>
                                </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                    <x-button icon="o-x-mark" class="btn-ghost btn-sm btn-square text-error"
                              wire:click="removeGuestTeacher({{ $row->id }})" tooltip="Remove guest" />
                </div>
            @endscope
        </x-table>
    </x-card>
    @endif

    {{-- Add/Edit Modal --}}
    <x-modal wire:model="modal" :title="$editId ? 'Edit Teacher' : 'Add Teacher'"
             separator class="modal-bottom" box-class="!max-w-xl mx-auto !rounded-t-2xl !mb-14">
        <x-form wire:submit="save" class="space-y-4">
            <input type="text" class="w-0 h-0 opacity-0 absolute pointer-events-none" autofocus />
            @if(count($studyProgramOptions))
                <div class="w-3/4">
                    <x-choices label="Study Program" single searchable wire:model="studyProgramId"
                               :options="$studyProgramOptions" placeholder="-- Select Program --" required />
                </div>
            @endif
            <x-input label="Full Name" wire:model="name" placeholder="Ahmad Fauzan" required />
            <div class="grid grid-cols-5 gap-3">
                <div class="col-span-2">
                    <x-input label="Front Title" wire:model="front_title" placeholder="Dr." />
                </div>
                <div class="col-span-3">
                    <x-input label="Rear Title" wire:model="rear_title" placeholder="M.T., Ph.D." />
                </div>
            </div>
            <div class="grid grid-cols-4 gap-3">
                <x-input label="Code" wire:model="code" placeholder="AFK" />
                <x-input label="Univ Code" wire:model="univ_code" placeholder="A001" />
                <div class="col-span-2">
                    <x-input label="Employee ID (NIP/NIDN)" wire:model="employee_id" placeholder="19800101 200901 1 001" />
                </div>
            </div>
            <div class="grid grid-cols-[1fr_auto] gap-3">
                <x-input label="Position (Jabatan)" wire:model.live="position"
                         placeholder="e.g. Lektor Kepala" />
                <x-input label="Civil Grade (Golongan)" wire:model.live="civil_grade"
                         placeholder="e.g. IV/a" class="w-4Pada 8" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <x-input label="Email" wire:model="email" type="email" placeholder="lecturer@univ.ac.id" />
                <x-input label="Phone" wire:model="phone" placeholder="08123456789" />
            </div>
            <x-slot:actions>
                <x-button label="Cancel" icon="o-x-circle"     wire:click="$set('modal', false)" />
                <x-button label="Save"   icon="o-check-circle" type="submit" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    {{-- Guest Teacher Search Modal --}}
    <x-modal wire:model="guestModal" title="Search Guest Teacher"
             separator class="modal-bottom" box-class="!max-w-xl mx-auto !rounded-t-2xl !mb-14">
        <div class="space-y-4">
            <input type="text" class="w-0 h-0 opacity-0 absolute pointer-events-none" autofocus />
            <p class="text-sm text-base-content/60">Search for teachers from programs outside your cluster. Guest teachers can be assigned to activities but cannot be edited here.</p>
            <div class="flex gap-2">
                <x-input wire:model="guestSearch" placeholder="Name, code, or employee ID..." class="flex-1" clearable />
                <x-button label="Search" icon="o-magnifying-glass" class="btn-primary" wire:click="searchGuest" />
            </div>

            @if(count($guestResults))
                <div class="divide-y divide-base-200">
                    @foreach($guestResults as $r)
                        <div class="flex items-center justify-between py-2">
                            <div>
                                <div class="font-medium text-sm">{{ $r['name'] }}</div>
                                <div class="text-xs text-base-content/50 flex items-center gap-1">
                                    <x-badge value="{{ $r['prodi'] }}" class="badge-xs badge-neutral" />
                                    {{ $r['prodi_nm'] }}
                                </div>
                            </div>
                            <x-button icon="o-plus" class="btn-primary btn-sm btn-square"
                                      wire:click="addGuestTeacher({{ $r['id'] }})" tooltip="Add as guest teacher" />
                        </div>
                    @endforeach
                </div>
            @elseif($guestSearch !== '')
                <p class="text-center text-sm text-base-content/40 py-4">No results found.</p>
            @endif
        </div>
        <!--
        <x-slot:actions>
            <x-button label="Close" icon="o-x-circle" wire:click="$set('guestModal', false)" />
        </x-slot:actions>
        -->
    </x-modal>

    {{-- Import Modal --}}
    <x-modal wire:model="importModal" title="Import Teachers from Excel"
             separator class="modal-bottom" box-class="!max-w-md mx-auto !rounded-t-2xl !mb-14">
        <div class="space-y-4">
            <input type="text" class="w-0 h-0 opacity-0 absolute pointer-events-none" autofocus />
            <x-alert title="Required: study_program, name"
                     description="study_program must match the program abbreviation. code = 3 chars (auto-generated if blank/duplicate). Optional: univ_code, employee_id, position, civil_grade, front_title, rear_title, email, phone."
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

    {{-- Delete Confirm --}}
    <x-modal wire:model="delModal" title="Delete Teacher"
             box-class="!max-w-sm">
        <p class="text-base-content/70 text-sm">Delete this teacher? They will be removed from all activities.</p>
        <x-slot:actions>
            <x-button label="Cancel" icon="o-x-circle" wire:click="$set('delModal', false)" />
            <x-button label="Delete" icon="o-trash"    class="btn-error" wire:click="delete" />
        </x-slot:actions>
    </x-modal>

    <x-toast />
</div>

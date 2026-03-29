<?php

use App\Models\FetNet\Client;
use App\Models\FetNet\Program;
use App\Models\FetNet\Teacher;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.admin')] class extends Component
{
    use WithPagination, Toast;

    public string $search           = '';
    public ?int   $filterProgramId  = null;
    public bool   $modal            = false;
    public bool   $delModal         = false;
    public ?int   $editId           = null;
    public ?int   $deleteId         = null;

    public string $code        = '';
    public string $univ_code   = '';
    public string $employee_id = '';
    public string $position    = '';
    public string $civil_grade = '';
    public string $front_title = '';
    public string $rear_title  = '';
    public string $name        = '';
    public string $email       = '';
    public string $phone       = '';
    public ?int   $programId   = null;

    // Shared options list for both filter and modal
    public array $programOptions = [];

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
        $this->loadProgramOptions();
    }

    private function loadProgramOptions(string $search = ''): void
    {
        $client = $this->client();
        if (! $client) { $this->programOptions = []; return; }

        $this->programOptions = Program::where('client_id', $client->id)
            ->when($search, fn($q) => $q
                ->where('name',   'ilike', "%{$search}%")
                ->orWhere('abbrev', 'ilike', "%{$search}%"))
            ->orderBy('abbrev')
            ->limit(30)
            ->get(['id', 'abbrev', 'name'])
            ->map(fn($p) => ['id' => $p->id, 'name' => "{$p->abbrev} — {$p->name}"])
            ->toArray();
    }

    public function updatedSearch(): void          { $this->resetPage(); }
    public function updatedFilterProgramId(): void { $this->resetPage(); }

    public function openCreate(): void
    {
        $this->reset(['code', 'univ_code', 'employee_id', 'position', 'civil_grade',
                      'front_title', 'rear_title', 'name', 'email', 'phone', 'editId', 'programId']);
        $this->loadProgramOptions();
        $this->modal = true;
    }

    public function openEdit(int $id): void
    {
        $t                  = Teacher::findOrFail($id);
        $this->editId       = $id;
        $this->programId    = $t->program_id;
        $this->code         = $t->code         ?? '';
        $this->univ_code    = $t->univ_code    ?? '';
        $this->employee_id  = $t->employee_id  ?? '';
        $this->position     = $t->position     ?? '';
        $this->civil_grade  = $t->civil_grade  ?? '';
        $this->front_title  = $t->front_title  ?? '';
        $this->rear_title   = $t->rear_title   ?? '';
        $this->name         = $t->name;
        $this->email        = $t->email        ?? '';
        $this->phone        = $t->phone        ?? '';
        $this->loadProgramOptions();
        $this->modal = true;
    }

    protected function rules(): array
    {
        return [
            'name'       => 'required',
            'programId'  => 'required|exists:institution_program,id',
            'code'       => 'nullable|size:3|alpha',
            'univ_code'  => 'nullable|max:4',
            'employee_id'=> 'nullable',
            'position'   => 'nullable|max:100',
            'civil_grade'=> 'nullable|max:50',
            'front_title'=> 'nullable',
            'rear_title' => 'nullable',
            'email'      => 'nullable|email',
            'phone'      => 'nullable',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $ids = $this->clientProgramIds();
        if (! in_array($this->programId, $ids)) {
            $this->addError('programId', 'Invalid program.');
            return;
        }

        // Resolve unique code within all client programs
        $usedCodes = Teacher::whereIn('program_id', $ids)
            ->when($this->editId, fn($q) => $q->where('id', '!=', $this->editId))
            ->whereNotNull('code')
            ->pluck('code')
            ->map(fn($c) => strtoupper($c))
            ->toArray();

        $reqCode = strtoupper(trim($this->code));
        if (strlen($reqCode) === 3 && ! in_array($reqCode, $usedCodes)) {
            $code    = $reqCode;
            $autoGen = false;
        } elseif ($this->editId) {
            $existing = Teacher::find($this->editId);
            $excCode  = strtoupper($existing?->code ?? '');
            if (strlen($excCode) === 3 && ! in_array($excCode, $usedCodes)) {
                $code    = $excCode;
                $autoGen = false;
            } else {
                $code    = Teacher::generateCode($this->name, $usedCodes);
                $autoGen = true;
            }
        } else {
            $code    = Teacher::generateCode($this->name, $usedCodes);
            $autoGen = true;
        }

        $data = [
            'program_id'  => $this->programId,
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

    public function with(): array
    {
        $ids = $this->clientProgramIds();

        $headers = [
            ['key' => 'code',         'label' => 'Code',     'class' => 'w-1/12'],
            ['key' => 'univ_code',    'label' => 'Univ Code','class' => 'w-1/12'],
            ['key' => 'full_name',    'label' => 'Name',     'class' => 'w-3/12'],
            ['key' => 'study_program','label' => 'Program',  'class' => 'w-2/12'],
            ['key' => 'email',        'label' => 'Email',    'class' => 'w-2/12 max-w-0 truncate'],
            ['key' => 'phone',        'label' => 'Phone',    'class' => 'w-1/12'],
            ['key' => 'action',       'label' => '',         'class' => 'w-2/12 text-right'],
        ];

        $filterIds = $this->filterProgramId ? [$this->filterProgramId] : $ids;

        $teachers = count($ids)
            ? Teacher::with('program:id,abbrev,name')
                ->whereIn('program_id', $filterIds)
                ->when($this->search, fn($q) => $q
                    ->where('name',         'ilike', "%{$this->search}%")
                    ->orWhere('code',        'ilike', "%{$this->search}%")
                    ->orWhere('employee_id', 'ilike', "%{$this->search}%"))
                ->orderBy('name')
                ->paginate(10)
                ->through(fn($t) => tap($t, fn($item) => [
                    $item->full_name     = $t->full_name,
                    $item->study_program = $t->program?->abbrev ?? '-',
                    $item->program_name  = $t->program?->name  ?? '-',
                ]))
            : collect();

        return compact('headers', 'teachers');
    }
}; ?>

<div>
    <x-header title="Teachers" subtitle="All teachers across programs" separator>
        <x-slot:actions>
            <div class="w-64">
                <x-choices single searchable
                           wire:model.live="filterProgramId"
                           :options="$programOptions"
                           placeholder="— All Programs —"
                           clearable />
            </div>
            <x-input placeholder="Search..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
            <x-button label="Add" icon="o-plus" class="btn-primary" wire:click="openCreate" />
        </x-slot:actions>
    </x-header>

    <x-card>
        <x-table :striped="true" :headers="$headers" :rows="$teachers" with-pagination container-class="overflow-hidden" class="table-fixed">
            @scope('cell_action', $row)
                <div class="flex justify-end gap-1">
                    {{-- Eye: detail popover --}}
                    <div x-data="{ open: false, above: false }"
                         @click.outside="open = false"
                         class="relative">
                        <button @click="above = $el.getBoundingClientRect().top > window.innerHeight / 2; open = !open"
                                class="btn btn-ghost btn-sm btn-square"
                                title="Detail">
                            <x-icon name="o-eye" class="w-4 h-4" />
                        </button>
                        <div x-show="open" x-cloak
                             :class="above ? 'bottom-full mb-1' : 'top-full mt-1'"
                             class="absolute right-0 z-50 w-72 bg-base-100 border border-base-200 rounded-xl shadow-xl p-4 text-xs space-y-1.5">
                            <p class="font-semibold text-sm text-base-content mb-2">
                                {{ $row->full_name }}
                            </p>
                            @php
                                $rows = [
                                    ['Program',      $row->study_program . ' — ' . $row->program_name],
                                    ['Code',         $row->code],
                                    ['Univ Code',    $row->univ_code],
                                    ['NIP/NIDN',     $row->employee_id],
                                    ['Position',     $row->position],
                                    ['Civil Grade',  $row->civil_grade],
                                    ['Email',        $row->email],
                                    ['Phone',        $row->phone],
                                ];
                            @endphp
                            @foreach($rows as [$label, $val])
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

    {{-- Add/Edit Modal --}}
    <x-modal wire:model="modal" :title="$editId ? 'Edit Teacher' : 'Add Teacher'"
             separator class="modal-bottom" box-class="!max-w-xl mx-auto !rounded-t-2xl !mb-14">
        <x-form wire:submit="save" class="space-y-4">
            <input type="text" class="w-0 h-0 opacity-0 absolute pointer-events-none" autofocus />
            <div class="w-3/4">
                <x-choices label="Program" single searchable
                           wire:model="programId"
                           :options="$programOptions"
                           placeholder="— Select Study Program —" required />
            </div>
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
                <x-input label="Position (Jabatan)" wire:model="position" placeholder="e.g. Lektor Kepala" />
                <x-input label="Civil Grade (Golongan)" wire:model="civil_grade" placeholder="e.g. IV/a" />
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

    {{-- Delete Confirm --}}
    <x-modal wire:model="delModal" title="Delete Teacher" box-class="!max-w-sm">
        <p class="text-base-content/70 text-sm">Delete this teacher? They will be removed from all activities.</p>
        <x-slot:actions>
            <x-button label="Cancel" icon="o-x-circle" wire:click="$set('delModal', false)" />
            <x-button label="Delete" icon="o-trash"    class="btn-error" wire:click="delete" />
        </x-slot:actions>
    </x-modal>

    <x-toast />
</div>

<?php

use App\Livewire\Concerns\HasProgramSemester;
use App\Models\FetNet\Program;
use App\Models\FetNet\Student;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;

new #[Layout('layouts.program')] class extends Component
{
    use Toast, HasProgramSemester;

    // Batch modal
    public bool   $batchModal    = false;
    public bool   $editBatch     = false;
    public ?int   $batchId       = null;
    public string $batchName     = '';
    public string $batchBatch    = '';
    public int    $batchCount    = 0;

    // Group modal
    public bool   $groupModal    = false;
    public bool   $editGroup     = false;
    public ?int   $groupId       = null;
    public ?int   $groupParentId = null;
    public string $groupName     = '';
    public int    $groupCount    = 0;

    // Delete
    public bool  $delModal  = false;
    public ?int  $deleteId  = null;

    private function program(): ?Program
    {
        return Program::where('user_id', auth()->id())->first();
    }

    public function mount(): void
    {
        $program = $this->program();
        if ($program) $this->mountSemesterContext($program->client_id);
    }

    // ── Batch (root level) ──────────────────────────────────────────────────

    public function openCreateBatch(): void
    {
        $this->reset(['batchName', 'batchBatch', 'batchCount', 'batchId']);
        $this->editBatch  = false;
        $this->batchModal = true;
    }

    public function openEditBatch(int $id): void
    {
        $s                = Student::findOrFail($id);
        $this->batchId    = $id;
        $this->batchName  = $s->name;
        $this->batchBatch = $s->batch ?? '';
        $this->batchCount = $s->number_of_student;
        $this->editBatch  = true;
        $this->batchModal = true;
    }

    public function saveBatch(): void
    {
        $this->validate([
            'batchName'  => 'required',
            'batchBatch' => 'nullable',
            'batchCount' => 'required|integer|min:0',
        ]);

        $data = [
            'name'              => $this->batchName,
            'batch'             => $this->batchBatch ?: null,
            'number_of_student' => $this->batchCount,
        ];

        if ($this->editBatch && $this->batchId) {
            Student::findOrFail($this->batchId)->update($data);
            $this->success('Batch updated.', position: 'toast-top toast-center');
        } else {
            Student::create(array_merge($data, [
                'program_id' => $this->program()->id,
                'parent_id'  => null,
            ]));
            $this->success('Batch added.', position: 'toast-top toast-center');
        }

        $this->batchModal = false;
    }

    // ── Group / Sub-group ───────────────────────────────────────────────────

    public function openAddGroup(int $parentId): void
    {
        $this->reset(['groupName', 'groupCount', 'groupId']);
        $this->groupParentId = $parentId;
        $this->editGroup     = false;
        $this->groupModal    = true;
    }

    public function openEditGroup(int $id): void
    {
        $g                   = Student::findOrFail($id);
        $this->groupId       = $id;
        $this->groupParentId = $g->parent_id;
        $this->groupName     = $g->name;
        $this->groupCount    = $g->number_of_student;
        $this->editGroup     = true;
        $this->groupModal    = true;
    }

    public function saveGroup(): void
    {
        $this->validate([
            'groupName'  => 'required',
            'groupCount' => 'required|integer|min:0',
        ]);

        $data = [
            'name'              => $this->groupName,
            'number_of_student' => $this->groupCount,
            'parent_id'         => $this->groupParentId,
        ];

        if ($this->editGroup && $this->groupId) {
            Student::findOrFail($this->groupId)->update($data);
            $this->success('Group updated.', position: 'toast-top toast-center');
        } else {
            Student::create(array_merge($data, ['program_id' => $this->program()->id]));
            $this->success('Group added.', position: 'toast-top toast-center');
        }

        $this->groupModal = false;
    }

    // ── Delete ──────────────────────────────────────────────────────────────

    public function confirmDelete(int $id): void
    {
        $this->deleteId = $id;
        $this->delModal = true;
    }

    public function delete(): void
    {
        Student::findOrFail($this->deleteId)->delete();
        $this->delModal = false;
        $this->deleteId = null;
        $this->warning('Deleted (including sub-groups).', position: 'toast-top toast-center');
    }

    public function with(): array
    {
        $program = $this->program();
        return [
            'batches' => $program
                ? Student::where('program_id', $program->id)
                    ->whereNull('parent_id')
                    ->with(['children.children'])
                    ->orderBy('batch')
                    ->orderBy('name')
                    ->get()
                : collect(),
        ];
    }
}; ?>

<div>
    <x-header title="Students" subtitle="Manage student batches & groups" separator>
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
            <x-button label="Add Batch" icon="o-plus" class="btn-primary" wire:click="openCreateBatch" />
        </x-slot:actions>
    </x-header>

    @forelse($batches as $batch)
        <x-card class="mb-3">
            <div class="flex items-start justify-between gap-3">
                <div class="flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-semibold text-base-content">
                            {{ $batch->name }}
                            @if($batch->number_of_student)
                                <span class="text-base-content/40 font-normal">({{ $batch->number_of_student }})</span>
                            @endif
                        </span>
                    </div>

                    @if($batch->children->isNotEmpty())
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            @foreach($batch->children as $group)
                                <div class="bg-base-200 rounded-xl p-3">
                                    <div class="flex items-center justify-between gap-1 mb-1">
                                        <span class="text-sm font-medium">
                                            {{ $group->name }}
                                            @if($group->number_of_student)
                                                <span class="text-base-content/40 font-normal">({{ $group->number_of_student }})</span>
                                            @endif
                                        </span>
                                        <div class="flex gap-0.5">
                                            <x-button icon="o-plus" class="btn-ghost btn-xs btn-square"
                                                      wire:click="openAddGroup({{ $group->id }})" tooltip="Add sub-group" />
                                            <x-button icon="o-pencil" class="btn-ghost btn-xs btn-square"
                                                      wire:click="openEditGroup({{ $group->id }})" tooltip="Edit" />
                                            <x-button icon="o-trash" class="btn-ghost btn-xs btn-square text-error"
                                                      wire:click="confirmDelete({{ $group->id }})" tooltip="Delete" />
                                        </div>
                                    </div>
                                    @if($group->children->isNotEmpty())
                                        <div class="mt-2 ml-3 border-l-2 border-base-300 pl-3 space-y-1">
                                            @foreach($group->children as $sub)
                                                <div class="flex items-center justify-between bg-base-100 rounded-lg px-2 py-1">
                                                    <span class="text-xs">{{ $sub->name }}
                                                        @if($sub->number_of_student)
                                                            <span class="text-base-content/40">({{ $sub->number_of_student }})</span>
                                                        @endif
                                                    </span>
                                                    <div class="flex gap-0.5">
                                                        <x-button icon="o-pencil" class="btn-ghost btn-xs btn-square"
                                                                  wire:click="openEditGroup({{ $sub->id }})" tooltip="Edit" />
                                                        <x-button icon="o-trash" class="btn-ghost btn-xs btn-square text-error"
                                                                  wire:click="confirmDelete({{ $sub->id }})" tooltip="Delete" />
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="flex gap-1 shrink-0">
                    <x-button icon="o-plus" class="btn-ghost btn-sm btn-square"
                              wire:click="openAddGroup({{ $batch->id }})" tooltip="Add group" />
                    <x-button icon="o-pencil" class="btn-ghost btn-sm btn-square"
                              wire:click="openEditBatch({{ $batch->id }})" tooltip="Edit batch" />
                    <x-button icon="o-trash" class="btn-ghost btn-sm btn-square text-error"
                              wire:click="confirmDelete({{ $batch->id }})" tooltip="Delete batch" />
                </div>
            </div>
        </x-card>
    @empty
        <x-card>
            <p class="text-center text-base-content/50 py-6">No student data yet. Add a batch to get started.</p>
        </x-card>
    @endforelse

    {{-- Batch Modal --}}
    <x-modal wire:model="batchModal" :title="$editBatch ? 'Edit Batch' : 'Add Batch'"
             separator class="modal-bottom" box-class="!max-w-md mx-auto !rounded-t-2xl !mb-14">
        <x-form wire:submit="saveBatch" class="space-y-4">
            <input type="text" class="w-0 h-0 opacity-0 absolute pointer-events-none" autofocus />
            <x-input label="Batch Name" wire:model="batchName" placeholder="2021 Regular" required />
            <div class="grid grid-cols-3 gap-3">
                <x-input label="Year" wire:model="batchBatch" placeholder="2021" />
                <div class="col-span-2">
                    <x-input label="Total Students" wire:model="batchCount" type="number" min="0" />
                </div>
            </div>
            <x-slot:actions>
                <x-button label="Cancel" icon="o-x-circle"     wire:click="$set('batchModal', false)" />
                <x-button label="Save"   icon="o-check-circle" type="submit" class="btn-primary" spinner="saveBatch" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    {{-- Group Modal --}}
    <x-modal wire:model="groupModal" :title="$editGroup ? 'Edit Group' : 'Add Group'"
             separator class="modal-bottom" box-class="!max-w-sm mx-auto !rounded-t-2xl !mb-14">
        <x-form wire:submit="saveGroup" class="space-y-4">
            <input type="text" class="w-0 h-0 opacity-0 absolute pointer-events-none" autofocus />
            <div class="grid grid-cols-3 gap-3">
                <div class="col-span-2">
                    <x-input label="Group Name" wire:model="groupName" placeholder="Group A" required />
                </div>
                <x-input label="# Students" wire:model="groupCount" type="number" min="0" />
            </div>
            <x-slot:actions>
                <x-button label="Cancel" icon="o-x-circle"     wire:click="$set('groupModal', false)" />
                <x-button label="Save"   icon="o-check-circle" type="submit" class="btn-primary" spinner="saveGroup" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    {{-- Delete Confirm --}}
    <x-modal wire:model="delModal" title="Delete"
             box-class="!max-w-sm">
        <p class="text-base-content/70 text-sm">Delete this item? All sub-groups and activity assignments will also be removed.</p>
        <x-slot:actions>
            <x-button label="Cancel" icon="o-x-circle" wire:click="$set('delModal', false)" />
            <x-button label="Delete" icon="o-trash"    class="btn-error" wire:click="delete" />
        </x-slot:actions>
    </x-modal>

    <x-toast />
</div>

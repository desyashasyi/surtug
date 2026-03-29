<?php

use App\Models\FetNet\Program;
use App\Models\FetNet\Specialization;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.program')] class extends Component
{
    use WithPagination, Toast;

    public string $search    = '';
    public bool   $modal     = false;
    public bool   $delModal  = false;
    public ?int   $editId    = null;
    public ?int   $deleteId  = null;

    public string $code   = '';
    public string $abbrev = '';
    public string $name   = '';

    public array $headers = [
        ['key' => 'code',   'label' => 'Kode',        'class' => 'w-2/12'],
        ['key' => 'abbrev', 'label' => 'Singkatan',   'class' => 'w-2/12'],
        ['key' => 'name',   'label' => 'Nama',         'class' => 'w-6/12'],
        ['key' => 'action', 'label' => '',             'class' => 'w-2/12 text-right'],
    ];

    private function program(): ?Program
    {
        return Program::where('user_id', auth()->id())->first();
    }

    public function updatedSearch(): void { $this->resetPage(); }

    public function openCreate(): void
    {
        $this->reset(['code', 'abbrev', 'name', 'editId']);
        $this->modal = true;
    }

    public function openEdit(int $id): void
    {
        $s            = Specialization::findOrFail($id);
        $this->editId = $id;
        $this->code   = $s->code;
        $this->abbrev = $s->abbrev ?? '';
        $this->name   = $s->name;
        $this->modal  = true;
    }

    protected function rules(): array
    {
        $unique = 'required|unique:fetnet_specialization,code';
        if ($this->editId) $unique .= ',' . $this->editId;
        return [
            'code'   => $unique,
            'abbrev' => 'nullable',
            'name'   => 'required',
        ];
    }

    public function save(): void
    {
        $this->validate();
        $data = ['code' => $this->code, 'abbrev' => $this->abbrev, 'name' => $this->name];

        if ($this->editId) {
            Specialization::findOrFail($this->editId)->update($data);
            $this->success('Specialization updated.', position: 'toast-top toast-center');
        } else {
            Specialization::create(array_merge($data, ['program_id' => $this->program()->id]));
            $this->success('Specialization added.', position: 'toast-top toast-center');
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
        Specialization::findOrFail($this->deleteId)->delete();
        $this->delModal = false;
        $this->deleteId = null;
        $this->warning('Specialization deleted.', position: 'toast-top toast-center');
    }

    public function with(): array
    {
        $program = $this->program();
        return [
            'specializations' => $program
                ? Specialization::where('program_id', $program->id)
                    ->when($this->search, fn($q) => $q
                        ->where('name', 'ilike', "%{$this->search}%")
                        ->orWhere('code', 'ilike', "%{$this->search}%"))
                    ->orderBy('code')
                    ->paginate(10)
                : collect(),
        ];
    }
}; ?>

<div>
    <x-header title="Specializations" subtitle="Manage study concentrations" separator>
        <x-slot:actions>
            <x-input placeholder="Search..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
            <x-button label="Add" icon="o-plus" class="btn-primary" wire:click="openCreate" />
        </x-slot:actions>
    </x-header>

    <x-card>
        <x-table :striped="true" :headers="$headers" :rows="$specializations" with-pagination container-class="overflow-hidden">
            @scope('cell_action', $row)
                <div class="flex justify-end gap-1">
                    <x-button icon="o-pencil" class="btn-ghost btn-sm btn-square"
                              wire:click="openEdit({{ $row->id }})" tooltip="Edit" />
                    <x-button icon="o-trash" class="btn-ghost btn-sm btn-square text-error"
                              wire:click="confirmDelete({{ $row->id }})" tooltip="Delete" />
                </div>
            @endscope
        </x-table>
    </x-card>

    <x-modal wire:model="modal" :title="$editId ? 'Edit Specialization' : 'Add Specialization'"
             separator class="modal-bottom" box-class="!max-w-lg mx-auto !rounded-t-2xl !mb-14">
        <x-form wire:submit="save" class="space-y-4">
            <input type="text" class="w-0 h-0 opacity-0 absolute pointer-events-none" autofocus />
            <div class="w-5/6">
                <x-input label="Name" wire:model="name" placeholder="Electrical Power Engineering" required />
            </div>
            <div class="grid grid-cols-3 gap-3">
                <x-input label="Code" wire:model="code" placeholder="EPE" required />
                <div class="col-span-2">
                    <x-input label="Abbreviation" wire:model="abbrev" placeholder="EP. Eng" />
                </div>
            </div>
            <x-slot:actions>
                <x-button label="Cancel" icon="o-x-circle"     wire:click="$set('modal', false)" />
                <x-button label="Save"   icon="o-check-circle" type="submit" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    <x-modal wire:model="delModal" title="Delete Specialization"
             box-class="!max-w-sm">
        <p class="text-base-content/70 text-sm">Delete this specialization? Subjects linked to it will be unlinked.</p>
        <x-slot:actions>
            <x-button label="Cancel" icon="o-x-circle" wire:click="$set('delModal', false)" />
            <x-button label="Delete" icon="o-trash"    class="btn-error" wire:click="delete" />
        </x-slot:actions>
    </x-modal>

    <x-toast />
</div>

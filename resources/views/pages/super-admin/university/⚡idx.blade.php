<?php

use App\Models\FetNet\University;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.super-admin')] class extends Component
{
    use WithPagination, Toast;

    public string $search      = '';
    public bool   $modal       = false;
    public bool   $deleteModal = false;
    public ?int   $deleteId    = null;
    public string $code        = '';
    public string $name        = '';
    public string $name_eng    = '';
    public ?int   $editId      = null;

    public array $headers = [
        ['key' => 'code',     'label' => 'Kode',           'class' => 'w-1/12'],
        ['key' => 'name',     'label' => 'Nama',           'class' => 'w-5/12'],
        ['key' => 'name_eng', 'label' => 'Nama (Inggris)', 'class' => 'w-5/12'],
        ['key' => 'action',   'label' => '',               'class' => 'w-1/12 text-right'],
    ];

    protected function rules(): array
    {
        $uniqueCode = 'required|unique:institution_university,code';
        $uniqueName = 'required|unique:institution_university,name';

        if ($this->editId) {
            $uniqueCode .= ',' . $this->editId;
            $uniqueName .= ',' . $this->editId;
        }

        return [
            'code'     => $uniqueCode,
            'name'     => $uniqueName,
            'name_eng' => 'nullable',
        ];
    }

    public function updatedSearch(): void { $this->resetPage(); }

    public function openCreate(): void
    {
        $this->reset(['code', 'name', 'name_eng', 'editId']);
        $this->modal = true;
    }

    public function openEdit(int $id): void
    {
        $u              = University::findOrFail($id);
        $this->editId   = $id;
        $this->code     = $u->code;
        $this->name     = $u->name;
        $this->name_eng = $u->name_eng ?? '';
        $this->modal    = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = ['code' => $this->code, 'name' => $this->name, 'name_eng' => $this->name_eng];

        if ($this->editId) {
            University::findOrFail($this->editId)->update($data);
        } else {
            University::create($data);
        }

        $this->success(
            $this->editId ? 'University updated.' : 'University added successfully.',
            position: 'toast-top toast-center'
        );
        $this->modal = false;
        $this->reset(['code', 'name', 'name_eng', 'editId']);
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId    = $id;
        $this->deleteModal = true;
    }

    public function delete(): void
    {
        University::destroy($this->deleteId);
        $this->deleteModal = false;
        $this->deleteId    = null;
        $this->warning('University deleted.', position: 'toast-top toast-center');
    }

    public function with(): array
    {
        return [
            'universities' => University::query()
                ->when($this->search, fn($q) => $q
                    ->where('code', 'ilike', "%{$this->search}%")
                    ->orWhere('name', 'ilike', "%{$this->search}%"))
                ->paginate(10),
        ];
    }
}; ?>

<div>
    <x-header title="Universities" subtitle="Manage university data" separator>
        <x-slot:actions>
            <x-input placeholder="Search..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
            <x-button label="Add" icon="o-plus" class="btn-primary" wire:click="openCreate" />
        </x-slot:actions>
    </x-header>

    <x-card>
        <x-table :striped="true" :headers="$headers" :rows="$universities" with-pagination container-class="overflow-hidden">
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

    <x-modal wire:model="modal" :title="$editId ? 'Edit University' : 'Add University'" separator class="modal-bottom" box-class="!max-w-xl mx-auto !rounded-t-2xl !mb-14">
        <x-form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-4 gap-3">
                <x-input label="Code" wire:model="code" placeholder="UPI" required />
                <div class="col-span-3">
                    <x-input label="Name" wire:model="name" placeholder="Universitas Pendidikan Indonesia" required />
                </div>
            </div>
            <div class="w-5/6">
                <x-input label="Name (EN)" wire:model="name_eng" placeholder="Indonesia University of Education" />
            </div>
            <x-slot:actions>
                <x-button label="Cancel" icon="o-x-circle"     wire:click="$set('modal', false)" />
                <x-button label="Save"   icon="o-check-circle" type="submit" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    <x-modal wire:model="deleteModal" title="Delete University" box-class="!max-w-xs mx-auto">
        <p class="text-base-content/70 text-sm">Are you sure you want to delete this university?</p>
        <x-slot:actions>
            <x-button label="Cancel" icon="o-x-circle" wire:click="$set('deleteModal', false)" />
            <x-button label="Delete" icon="o-trash"    class="btn-error" wire:click="delete" />
        </x-slot:actions>
    </x-modal>

    <x-toast />
</div>

<?php

use App\Models\FetNet\Faculty;
use App\Models\FetNet\University;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.super-admin')] class extends Component
{
    use WithPagination, Toast;

    public string $search             = '';
    public bool   $modal              = false;
    public bool   $deleteModal        = false;
    public ?int   $deleteId           = null;
    public string $code               = '';
    public string $name               = '';
    public string $name_eng           = '';
    public ?int   $university_id      = null;
    public ?int   $editId             = null;
    public array  $universitiesOptions = [];

    public array $headers = [
        ['key' => 'code',            'label' => 'Kode',        'class' => 'w-1/12'],
        ['key' => 'name',            'label' => 'Nama',        'class' => 'w-4/12'],
        ['key' => 'university_name', 'label' => 'Universitas', 'class' => 'w-4/12'],
        ['key' => 'action',          'label' => '',            'class' => 'w-1/12 text-right'],
    ];

    protected function rules(): array
    {
        $uniqueCode = 'required|unique:institution_faculty,code';
        if ($this->editId) $uniqueCode .= ',' . $this->editId;

        return [
            'code'          => $uniqueCode,
            'name'          => 'required',
            'name_eng'      => 'nullable',
            'university_id' => 'required|exists:institution_university,id',
        ];
    }

    public function mount(): void { $this->loadUniversities(); }

    private function loadUniversities(): void
    {
        $this->universitiesOptions = University::orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn($u) => ['id' => $u->id, 'name' => "{$u->code} | {$u->name}"])
            ->toArray();
    }

    public function updatedSearch(): void { $this->resetPage(); }

    public function openCreate(): void
    {
        $this->reset(['code', 'name', 'name_eng', 'university_id', 'editId']);
        $this->modal = true;
    }

    public function openEdit(int $id): void
    {
        $f                   = Faculty::findOrFail($id);
        $this->editId        = $id;
        $this->code          = $f->code;
        $this->name          = $f->name;
        $this->name_eng      = $f->name_eng ?? '';
        $this->university_id = $f->university_id;
        $this->modal         = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'code'          => $this->code,
            'name'          => $this->name,
            'name_eng'      => $this->name_eng,
            'university_id' => $this->university_id,
        ];

        if ($this->editId) {
            Faculty::findOrFail($this->editId)->update($data);
        } else {
            Faculty::create($data);
        }

        $this->success(
            $this->editId ? 'Faculty updated.' : 'Faculty added successfully.',
            position: 'toast-top toast-center'
        );
        $this->modal = false;
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId    = $id;
        $this->deleteModal = true;
    }

    public function delete(): void
    {
        Faculty::destroy($this->deleteId);
        $this->deleteModal = false;
        $this->deleteId    = null;
        $this->warning('Faculty deleted.', position: 'toast-top toast-center');
    }

    public function with(): array
    {
        return [
            'faculties' => Faculty::with('university')
                ->when($this->search, fn($q) => $q
                    ->where('code', 'ilike', "%{$this->search}%")
                    ->orWhere('name', 'ilike', "%{$this->search}%"))
                ->paginate(10)
                ->through(fn($f) => tap($f, fn($item) =>
                    $item->university_name = $f->university?->name ?? '-'
                )),
        ];
    }
}; ?>

<div>
    <x-header title="Faculties" subtitle="Manage faculty data" separator>
        <x-slot:actions>
            <x-input placeholder="Search..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
            <x-button label="Add" icon="o-plus" class="btn-primary" wire:click="openCreate" />
        </x-slot:actions>
    </x-header>

    <x-card>
        <x-table :striped="true" :headers="$headers" :rows="$faculties" with-pagination container-class="overflow-hidden">
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

    <x-modal wire:model="modal" :title="$editId ? 'Edit Faculty' : 'Add Faculty'" separator class="modal-bottom" box-class="!max-w-xl mx-auto !rounded-t-2xl !mb-14">
        <x-form wire:submit="save" class="space-y-4">
            <input type="text" class="w-0 h-0 opacity-0 absolute pointer-events-none" autofocus />
            <div class="w-3/4">
                <x-choices label="University" single searchable wire:model="university_id" :options="$universitiesOptions" placeholder="Select university" required />
            </div>
            <div class="grid grid-cols-4 gap-3">
                <x-input label="Code" wire:model="code" placeholder="FPTEK" required />
                <div class="col-span-3">
                    <x-input label="Name" wire:model="name" placeholder="Faculty of Technology and Vocational Education" required />
                </div>
            </div>
            <div class="w-5/6">
                <x-input label="Name (EN)" wire:model="name_eng" placeholder="Faculty of Technology and Vocational Education" />
            </div>
            <x-slot:actions>
                <x-button label="Cancel" icon="o-x-circle"     wire:click="$set('modal', false)" />
                <x-button label="Save"   icon="o-check-circle" type="submit" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    <x-modal wire:model="deleteModal" title="Delete Faculty" box-class="!max-w-xs mx-auto">
        <p class="text-base-content/70 text-sm">Are you sure you want to delete this faculty?</p>
        <x-slot:actions>
            <x-button label="Cancel" icon="o-x-circle" wire:click="$set('deleteModal', false)" />
            <x-button label="Delete" icon="o-trash"    class="btn-error" wire:click="delete" />
        </x-slot:actions>
    </x-modal>

    <x-toast />
</div>

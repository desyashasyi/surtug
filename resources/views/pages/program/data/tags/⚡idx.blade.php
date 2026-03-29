<?php

use App\Models\FetNet\ActivityTag;
use App\Models\FetNet\Program;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;

new #[Layout('layouts.program')] class extends Component
{
    use Toast;

    public bool    $modal    = false;
    public bool    $delModal = false;
    public ?int    $editId   = null;
    public ?int    $deleteId = null;
    public string  $name     = '';

    private function program(): ?Program
    {
        return Program::where('user_id', auth()->id())->first();
    }

    public function openCreate(): void
    {
        $this->reset(['name', 'editId']);
        $this->modal = true;
    }

    public function openEdit(int $id): void
    {
        $tag = ActivityTag::findOrFail($id);
        $this->editId = $id;
        $this->name   = $tag->name;
        $this->modal  = true;
    }

    public function save(): void
    {
        $this->validate(['name' => 'required|string|max:100']);

        $program = $this->program();

        if ($this->editId) {
            ActivityTag::findOrFail($this->editId)->update(['name' => $this->name]);
            $this->success('Tag updated.', position: 'toast-top toast-center');
        } else {
            ActivityTag::create(['program_id' => $program->id, 'name' => $this->name]);
            $this->success('Tag added.', position: 'toast-top toast-center');
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
        ActivityTag::find($this->deleteId)?->delete();
        $this->deleteId = null;
        $this->delModal = false;
        $this->warning('Tag deleted.', position: 'toast-top toast-center');
    }

    public function with(): array
    {
        $program = $this->program();
        $tags    = $program
            ? ActivityTag::where('program_id', $program->id)->orderBy('name')->get()
            : collect();

        $headers = [
            ['key' => 'name',   'label' => 'Tag Name', 'class' => 'w-10/12'],
            ['key' => 'action', 'label' => '',          'class' => 'w-2/12 text-right'],
        ];

        return compact('tags', 'headers');
    }
}; ?>

<div>
    <x-header title="Activity Tags" subtitle="Tags used to group activities for time/space constraints" separator>
        <x-slot:actions>
            <x-button label="Add Tag" icon="o-plus" class="btn-primary" wire:click="openCreate" />
        </x-slot:actions>
    </x-header>

    <x-card>
        <x-table :striped="true" :headers="$headers" :rows="$tags" container-class="overflow-hidden">

            @scope('cell_action', $row)
                <div class="flex justify-end gap-1">
                    <x-button icon="o-pencil" class="btn-ghost btn-sm btn-square"
                              wire:click="openEdit({{ $row->id }})" tooltip="Edit" />
                    <x-button icon="o-trash" class="btn-ghost btn-sm btn-square text-error"
                              wire:click="confirmDelete({{ $row->id }})" tooltip="Delete" />
                </div>
            @endscope

        </x-table>

        @if($tags->isEmpty())
            <p class="text-center text-base-content/40 py-8 text-sm">
                No tags yet. Tags are used in time/space constraints (e.g. "Max hours daily with activity tag").
            </p>
        @endif
    </x-card>

    {{-- Add/Edit Modal --}}
    <x-modal wire:model="modal" :title="$editId ? 'Edit Tag' : 'Add Tag'"
             class="modal-bottom" box-class="!max-w-sm mx-auto !rounded-t-2xl !mb-14" separator>
        <x-form wire:submit="save" class="space-y-4">
            <input type="text" class="w-0 h-0 opacity-0 absolute pointer-events-none" autofocus />
            <x-input label="Tag Name" wire:model="name" placeholder="e.g. Lab, Sports, Music" required />
            <x-slot:actions>
                <x-button label="Cancel" icon="o-x-circle" wire:click="$set('modal', false)" />
                <x-button label="Save"   icon="o-check-circle" type="submit" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    {{-- Delete Confirm --}}
    <x-modal wire:model="delModal" title="Delete Tag"
             box-class="!max-w-sm">
        <p class="text-sm text-base-content/70">Delete this tag? Constraints using it will lose the tag reference.</p>
        <x-slot:actions>
            <x-button label="Cancel" icon="o-x-circle" wire:click="$set('delModal', false)" />
            <x-button label="Delete" icon="o-trash" class="btn-error" wire:click="delete" />
        </x-slot:actions>
    </x-modal>

    <x-toast />
</div>

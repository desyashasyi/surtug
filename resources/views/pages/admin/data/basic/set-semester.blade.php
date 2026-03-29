<?php

use App\Models\FetNet\Client;
use App\Models\FetNet\Semester;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component
{
    use WithPagination, Toast;

    public bool    $showForm    = false;
    public bool    $deleteModal = false;
    public ?int    $deleteId    = null;
    public ?string $year        = null;
    public ?string $semester    = null;

    public array $headers = [
        ['key' => 'year',     'label' => 'Tahun',   'class' => 'w-2/12'],
        ['key' => 'semester', 'label' => 'Semester', 'class' => 'w-2/12 text-center'],
        ['key' => 'action',   'label' => '',         'class' => 'w-1/12 text-right'],
    ];

    private function clientId(): ?int
    {
        return Client::where('user_id', auth()->id())->value('id');
    }

    public function toggleForm(): void
    {
        $this->showForm = ! $this->showForm;
        if (! $this->showForm) {
            $this->reset(['year', 'semester']);
        }
    }

    public function save(): void
    {
        $this->validate([
            'year'     => 'required|digits:4',
            'semester' => 'required|in:1,2',
        ]);

        $clientId = $this->clientId();

        $exists = Semester::where('client_id', $clientId)
            ->where('year', $this->year)
            ->where('semester', $this->semester)
            ->exists();

        if ($exists) {
            $this->error('Semester already exists.', position: 'toast-top toast-center');
            return;
        }

        Semester::create([
            'year'      => $this->year,
            'semester'  => $this->semester,
            'client_id' => $clientId,
        ]);

        $this->success('Semester saved successfully.', position: 'toast-top toast-center');
        $this->reset(['year', 'semester']);
        $this->showForm = false;
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId    = $id;
        $this->deleteModal = true;
    }

    public function delete(): void
    {
        Semester::findOrFail($this->deleteId)->delete();
        $this->deleteModal = false;
        $this->deleteId    = null;
        $this->success('Semester deleted.', position: 'toast-top toast-center');
    }

    public function with(): array
    {
        return [
            'semesters' => Semester::where('client_id', $this->clientId())
                ->orderByDesc('year')
                ->orderBy('semester')
                ->paginate(5),
        ];
    }
}; ?>

<div>
    <div class="flex justify-end mb-4">
        <x-button
            :label="$showForm ? 'Cancel' : 'Add Semester'"
            :icon="$showForm ? 'o-x-circle' : 'o-plus'"
            :class="$showForm ? 'btn-error btn-sm' : 'btn-success btn-sm'"
            wire:click="toggleForm"
        />
    </div>

    @if($showForm)
        <x-form wire:submit="save" class="mb-4">
            <div class="flex gap-4 items-end">
                <x-input label="Year" wire:model="year" placeholder="2024" class="w-32" />
                <x-choices label="Semester" single wire:model="semester" :options="[['id'=>'1','name'=>'1'],['id'=>'2','name'=>'2']]" placeholder="-- Select --" class="w-32" />
                <x-button label="Save" type="submit" class="btn-primary btn-sm" spinner="save" />
            </div>
        </x-form>
    @endif

    @if($semesters->isNotEmpty())
        <x-table :striped="true" :headers="$headers" :rows="$semesters" with-pagination container-class="overflow-hidden">
            @scope('cell_semester', $row)
                <x-badge value="Semester {{ $row->semester }}" class="badge-neutral" />
            @endscope
            @scope('cell_action', $row)
                <x-button icon="o-trash" class="btn-ghost btn-sm btn-square text-error"
                          wire:click="confirmDelete({{ $row->id }})" tooltip="Delete" />
            @endscope
        </x-table>
    @else
        <p class="text-center text-base-content/50 py-4">No semester data yet.</p>
    @endif

    <x-modal wire:model="deleteModal" title="Delete Semester" box-class="!max-w-xs mx-auto">
        <p class="text-base-content/70 text-sm">Are you sure you want to delete this semester?</p>
        <x-slot:actions>
            <x-button label="Cancel" icon="o-x-circle" wire:click="$set('deleteModal', false)" />
            <x-button label="Delete" icon="o-trash"    class="btn-error" wire:click="delete" />
        </x-slot:actions>
    </x-modal>

    <x-toast />
</div>

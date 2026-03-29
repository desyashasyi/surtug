<?php

use App\Models\FetNet\AcademicYear;
use App\Models\FetNet\Client;
use App\Models\FetNet\Semester;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;

new #[Layout('layouts.admin')] class extends Component
{
    use Toast;

    public bool   $modal     = false;
    public bool   $delModal  = false;
    public ?int   $editId    = null;
    public ?int   $deleteId  = null;

    // Form fields
    public ?int    $yearStart    = null;
    public string  $semName      = '';
    public ?int    $semType      = null;   // 1=Odd, 2=Even
    public ?int    $startMonth   = null;
    public ?int    $endMonth     = null;
    public ?string $lectureStart = null;
    public ?string $lectureEnd   = null;

    private function client(): ?Client
    {
        return Client::where('user_id', auth()->id())->first();
    }

    private function monthOptions(): array
    {
        return [
            ['id' => 1,  'name' => 'January'],   ['id' => 2,  'name' => 'February'],
            ['id' => 3,  'name' => 'March'],      ['id' => 4,  'name' => 'April'],
            ['id' => 5,  'name' => 'May'],         ['id' => 6,  'name' => 'June'],
            ['id' => 7,  'name' => 'July'],        ['id' => 8,  'name' => 'August'],
            ['id' => 9,  'name' => 'September'],  ['id' => 10, 'name' => 'October'],
            ['id' => 11, 'name' => 'November'],   ['id' => 12, 'name' => 'December'],
        ];
    }

    public function openCreate(): void
    {
        $this->reset(['yearStart', 'semName', 'semType', 'startMonth', 'endMonth', 'lectureStart', 'lectureEnd', 'editId']);
        $this->modal = true;
    }

    public function openEdit(int $id): void
    {
        $sem = Semester::with('academicYear')->findOrFail($id);

        $this->editId       = $id;
        $this->yearStart    = $sem->academicYear?->year_start;
        $this->semName      = $sem->name ?? '';
        $this->semType      = $sem->semester;
        $this->startMonth   = $sem->start_month;
        $this->endMonth     = $sem->end_month;
        $this->lectureStart = $sem->lecture_start?->format('Y-m-d');
        $this->lectureEnd   = $sem->lecture_end?->format('Y-m-d');
        $this->modal        = true;
    }

    public function save(): void
    {
        $this->validate([
            'yearStart'    => 'required|integer|min:2000|max:2099',
            'semName'      => 'required|string|max:50',
            'semType'      => 'required|in:1,2',
            'startMonth'   => 'required|integer|between:1,12',
            'endMonth'     => 'required|integer|between:1,12',
            'lectureStart' => 'nullable|date',
            'lectureEnd'   => 'nullable|date|after_or_equal:lectureStart',
        ]);

        $client = $this->client();

        // Ensure AcademicYear exists for this year
        $ay = AcademicYear::firstOrCreate(
            ['client_id' => $client->id, 'year_start' => $this->yearStart],
            ['is_active' => false]
        );

        // No duplicate type within the same AY
        $duplicate = Semester::where('academic_year_id', $ay->id)
            ->where('semester', $this->semType)
            ->when($this->editId, fn($q) => $q->where('id', '!=', $this->editId))
            ->exists();

        if ($duplicate) {
            $this->addError('semType', 'A semester of this type already exists for ' . $ay->label . '.');
            return;
        }

        $data = [
            'academic_year_id' => $ay->id,
            'client_id'        => $client->id,
            'year'             => $this->yearStart,
            'semester'         => $this->semType,
            'name'             => $this->semName,
            'start_month'      => $this->startMonth,
            'end_month'        => $this->endMonth,
            'lecture_start'    => $this->lectureStart ?: null,
            'lecture_end'      => $this->lectureEnd   ?: null,
        ];

        if ($this->editId) {
            Semester::findOrFail($this->editId)->update($data);
            $this->success('Semester updated.', position: 'toast-top toast-center');
        } else {
            Semester::create($data);
            $this->success('Semester added.', position: 'toast-top toast-center');
        }

        $this->modal = false;
    }

    public function setActive(int $ayId): void
    {
        $client = $this->client();
        AcademicYear::where('client_id', $client->id)->update(['is_active' => false]);
        AcademicYear::find($ayId)?->update(['is_active' => true]);
        $this->success('Active academic year updated.', position: 'toast-top toast-center');
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId = $id;
        $this->delModal = true;
    }

    public function delete(): void
    {
        $sem = Semester::find($this->deleteId);
        if ($sem) {
            $ayId = $sem->academic_year_id;
            $sem->delete();

            // If no more semesters under that AY, remove the AY too
            if ($ayId && Semester::where('academic_year_id', $ayId)->doesntExist()) {
                AcademicYear::find($ayId)?->delete();
            }
        }

        $this->deleteId = null;
        $this->delModal = false;
        $this->warning('Semester deleted.', position: 'toast-top toast-center');
    }

    public function with(): array
    {
        $client = $this->client();

        $semesters = $client
            ? Semester::with('academicYear')
                ->whereHas('academicYear', fn($q) => $q->where('client_id', $client->id))
                ->orderByDesc('year')
                ->orderBy('semester')
                ->get()
                ->map(fn($s) => tap($s, fn($item) => [
                    $item->ay_label  = $s->academicYear?->label ?? ($s->year . '/'. ($s->year + 1)),
                    $item->ay_active = (bool) $s->academicYear?->is_active,
                    $item->ay_id     = $s->academic_year_id,
                ]))
            : collect();

        $headers = [
            ['key' => 'ay_label',    'label' => 'Academic Year', 'class' => 'w-3/12'],
            ['key' => 'name',        'label' => 'Semester',      'class' => 'w-2/12'],
            ['key' => 'period',      'label' => 'Period',        'class' => 'w-2/12'],
            ['key' => 'lecture',     'label' => 'Lecture Dates', 'class' => 'w-3/12'],
            ['key' => 'action',      'label' => '',              'class' => 'w-2/12 text-right'],
        ];

        return [
            'semesters'    => $semesters,
            'headers'      => $headers,
            'monthOptions' => $this->monthOptions(),
        ];
    }
}; ?>

<div>
    <x-header title="Academic Year" subtitle="Manage academic years and semesters" separator>
        <x-slot:actions>
            <x-button label="Add Semester" icon="o-plus" class="btn-primary" wire:click="openCreate" />
        </x-slot:actions>
    </x-header>

    <x-card>
        <x-table :striped="true" :headers="$headers" :rows="$semesters" container-class="overflow-hidden" class="table-fixed">

            @scope('cell_ay_label', $row)
                <div class="flex items-center gap-2">
                    <span class="font-medium">{{ $row->ay_label }}</span>
                    @if($row->ay_active)
                        <x-badge value="Active" class="badge-success badge-sm" />
                    @endif
                </div>
                @if(! $row->ay_active)
                    <button wire:click="setActive({{ $row->ay_id }})"
                            class="text-xs text-primary hover:underline mt-0.5">Set active</button>
                @endif
            @endscope

            @scope('cell_name', $row)
                <div>
                    <span class="font-medium text-sm">{{ $row->name ?? ($row->semester == 1 ? 'Odd' : 'Even') }}</span>
                    <x-badge value="{{ $row->semester == 1 ? 'Odd' : 'Even' }}" class="badge-neutral badge-xs ml-1" />
                </div>
            @endscope

            @scope('cell_period', $row)
                @php
                    $mn = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',
                           7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec'];
                @endphp
                @if($row->start_month && $row->end_month)
                    <span class="text-sm">{{ $mn[$row->start_month] ?? '?' }} – {{ $mn[$row->end_month] ?? '?' }}</span>
                @else
                    <span class="text-base-content/30 text-sm italic">not set</span>
                @endif
            @endscope

            @scope('cell_lecture', $row)
                @if($row->lecture_start && $row->lecture_end)
                    <span class="text-sm">
                        {{ $row->lecture_start->format('d M Y') }} – {{ $row->lecture_end->format('d M Y') }}
                    </span>
                @else
                    <span class="text-base-content/30 text-sm italic">not set</span>
                @endif
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

        @if($semesters->isEmpty())
            <p class="text-center text-base-content/40 py-8 text-sm">No semesters yet. Click "Add Semester" to get started.</p>
        @endif
    </x-card>

    {{-- Add / Edit Semester Modal --}}
    <x-modal wire:model="modal" :title="$editId ? 'Edit Semester' : 'Add Semester'"
             separator class="modal-bottom" box-class="!max-w-xl mx-auto !rounded-t-2xl !mb-14">
        <x-form wire:submit="save" class="space-y-4">
            <input type="text" class="w-0 h-0 opacity-0 absolute pointer-events-none" autofocus />
            <x-input label="Year Start" wire:model="yearStart" type="number" min="2000" max="2099"
                     placeholder="e.g. 2024" hint="e.g. 2024 for academic year 2024/2025" required />
            <div class="grid grid-cols-2 gap-3">
                <x-input label="Semester Name" wire:model="semName" placeholder="e.g. Odd" required />
                <x-choices label="Type" single wire:model="semType"
                           :options="[['id'=>1,'name'=>'Odd'],['id'=>2,'name'=>'Even']]"
                           placeholder="Select type" required />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <x-choices label="Start Month" single wire:model="startMonth"
                           :options="$monthOptions" placeholder="Select month" required />
                <x-choices label="End Month" single wire:model="endMonth"
                           :options="$monthOptions" placeholder="Select month" required />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <x-input label="Lecture Start" wire:model="lectureStart" type="date" />
                <x-input label="Lecture End"   wire:model="lectureEnd"   type="date" />
            </div>
            <x-slot:actions>
                <x-button label="Cancel" icon="o-x-circle"     wire:click="$set('modal', false)" />
                <x-button label="Save"   icon="o-check-circle" type="submit" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    {{-- Delete Confirm --}}
    <x-modal wire:model="delModal" title="Delete Semester"
             class="modal-bottom" box-class="!max-w-xs mx-auto !rounded-t-2xl !mb-14">
        <p class="text-base-content/70 text-sm">Delete this semester? If it is the last semester for its academic year, the academic year will also be removed.</p>
        <x-slot:actions>
            <x-button label="Cancel" icon="o-x-circle" wire:click="$set('delModal', false)" />
            <x-button label="Delete" icon="o-trash"    class="btn-error" wire:click="delete" />
        </x-slot:actions>
    </x-modal>

    <x-toast />
</div>

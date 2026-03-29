<?php

use App\Exports\FetNet\SpaceTemplateExport;
use App\Jobs\FetNet\SpaceImportJob;
use App\Models\FetNet\Building;
use App\Models\FetNet\Client;
use App\Models\FetNet\Faculty;
use App\Models\FetNet\Program;
use App\Models\FetNet\Space;
use App\Models\FetNet\SpaceClaim;
use App\Models\FetNet\SpaceType;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.admin')] class extends Component
{
    use WithPagination, WithFileUploads, Toast;

    public string $search       = '';
    public bool   $modal        = false;
    public bool   $delModal     = false;
    public bool   $importModal  = false;
    public bool   $importing    = false;
    public bool   $buildingModal = false;
    public ?int   $editId       = null;
    public ?int   $deleteId     = null;
    public mixed  $importFile   = null;

    // Space fields
    public string $name       = '';
    public string $code       = '';
    public string $floor      = '';
    public ?int   $capacity   = null;
    public ?int   $typeId     = null;
    public ?int   $buildingId = null;
    public ?int   $facultyId  = null;

    // Building modal fields
    public ?int   $editBuildingId   = null;
    public string $buildingName     = '';
    public string $buildingCode     = '';
    public bool   $delBuildingModal = false;
    public ?int   $deleteBuildingId = null;

    // Space type manager modal
    public bool   $typeModal       = false;
    public ?int   $editTypeId      = null;
    public string $typeName        = '';
    public string $typeCode        = '';
    public bool   $typeIsTheory    = false;
    public bool   $delTypeModal    = false;
    public ?int   $deleteTypeId    = null;

    // Filter & sort
    public ?int   $filterBuildingId = null;
    public array  $sortBy           = ['column' => 'name', 'direction' => 'asc'];

    // Claims modal
    public bool   $claimsModal     = false;

    public array $buildingOptions = [];
    public array $facultyOptions  = [];
    public array $typeOptions     = [];

    private function client(): ?Client
    {
        return Client::where('user_id', auth()->id())->first();
    }

    public function mount(): void
    {
        $this->loadOptions();
    }

    private function loadOptions(): void
    {
        $client = $this->client();
        if (! $client) return;

        $this->buildingOptions = Building::where('client_id', $client->id)
            ->orderBy('name')->limit(30)
            ->get(['id', 'name', 'code'])
            ->map(fn($b) => ['id' => $b->id, 'name' => $b->code ? "[{$b->code}] {$b->name}" : $b->name])
            ->toArray();

        $this->facultyOptions = Faculty::when($client->university_id, fn($q) => $q->where('university_id', $client->university_id))
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn($f) => ['id' => $f->id, 'name' => $f->code ? "[{$f->code}] {$f->name}" : $f->name])
            ->toArray();


        $this->typeOptions = SpaceType::orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn($t) => ['id' => $t->id, 'name' => $t->code ? "[{$t->code}] {$t->name}" : $t->name])
            ->toArray();
    }

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedFilterBuildingId(): void { $this->resetPage(); }

    public function sortBy(array $sortBy): void
    {
        $this->sortBy = $sortBy;
        $this->resetPage();
    }

    public function searchBuildings(string $value = ''): void
    {
        $client = $this->client();
        if (! $client) { $this->buildingOptions = []; return; }

        $this->buildingOptions = Building::where('client_id', $client->id)
            ->where(fn($q) => $q
                ->where('name', 'ilike', "%{$value}%")
                ->orWhere('code', 'ilike', "%{$value}%"))
            ->orderBy('name')->limit(30)->get(['id', 'name', 'code'])
            ->map(fn($b) => ['id' => $b->id, 'name' => $b->code ? "[{$b->code}] {$b->name}" : $b->name])
            ->toArray();
    }

    public function openCreate(): void
    {
        $this->reset(['name', 'code', 'floor', 'capacity', 'typeId', 'buildingId', 'facultyId', 'editId']);
        $this->modal = true;
    }

    public function openEdit(int $id): void
    {
        $s                = Space::findOrFail($id);
        $this->editId     = $id;
        $this->name       = $s->name;
        $this->code       = $s->code      ?? '';
        $this->floor      = $s->floor     ?? '';
        $this->capacity   = $s->capacity;
        $this->typeId     = $s->type_id;
        $this->buildingId = $s->building_id;
        $this->facultyId  = $s->faculty_id;
        $this->modal = true;
    }

    protected function rules(): array
    {
        return [
            'name'       => 'required|max:200',
            'code'       => 'nullable|max:20',
            'floor'      => 'nullable|max:20',
            'capacity'   => 'nullable|integer|min:1',
            'typeId'     => 'nullable|exists:fetnet_space_type,id',
            'buildingId' => 'nullable|exists:fetnet_building,id',
            'facultyId'  => 'nullable',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $client = $this->client();
        if (! $client) return;

        $data = [
            'client_id'   => $client->id,
            'name'        => $this->name,
            'code'        => trim($this->code) ?: null,
            'type_id'     => $this->typeId,
            'floor'       => trim($this->floor) ?: null,
            'capacity'    => $this->capacity,
            'building_id' => $this->buildingId,
            'faculty_id'  => $this->facultyId,
        ];

        if ($this->editId) {
            Space::findOrFail($this->editId)->update($data);
            $this->success('Space updated.', position: 'toast-top toast-center');
        } else {
            Space::create($data);
            $this->success('Space added.', position: 'toast-top toast-center');
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
        Space::findOrFail($this->deleteId)->delete();
        $this->delModal = false;
        $this->deleteId = null;
        $this->warning('Space deleted.', position: 'toast-top toast-center');
    }

    // ── Buildings ────────────────────────────────────────────────────────────

    public function openBuildingManager(): void
    {
        $this->reset(['editBuildingId', 'buildingName', 'buildingCode']);
        $this->buildingModal = true;
    }

    public function openEditBuilding(int $id): void
    {
        $b = Building::findOrFail($id);
        $this->editBuildingId = $id;
        $this->buildingName   = $b->name;
        $this->buildingCode   = $b->code ?? '';
    }

    public function saveBuilding(): void
    {
        $this->validate([
            'buildingName' => 'required|max:200',
            'buildingCode' => 'nullable|max:20',
        ]);

        $client = $this->client();
        if (! $client) return;

        $data = [
            'client_id' => $client->id,
            'name'      => $this->buildingName,
            'code'      => trim($this->buildingCode) ?: null,
        ];

        if ($this->editBuildingId) {
            Building::findOrFail($this->editBuildingId)->update($data);
            $this->success('Building updated.', position: 'toast-top toast-center');
        } else {
            Building::create($data);
            $this->success('Building added.', position: 'toast-top toast-center');
        }

        $this->reset(['editBuildingId', 'buildingName', 'buildingCode']);
        $this->loadOptions();
    }

    public function confirmDeleteBuilding(int $id): void
    {
        $this->deleteBuildingId = $id;
        $this->delBuildingModal = true;
    }

    public function deleteBuilding(): void
    {
        Building::findOrFail($this->deleteBuildingId)->delete();
        $this->delBuildingModal  = false;
        $this->deleteBuildingId  = null;
        $this->warning('Building deleted.', position: 'toast-top toast-center');
        $this->loadOptions();
    }

    // ── Space Types ──────────────────────────────────────────────────────────

    public function openTypeManager(): void
    {
        $this->reset(['editTypeId', 'typeName', 'typeCode', 'typeIsTheory']);
        $this->typeModal = true;
    }

    public function openEditType(int $id): void
    {
        $t = SpaceType::findOrFail($id);
        $this->editTypeId   = $id;
        $this->typeName     = $t->name;
        $this->typeCode     = $t->code ?? '';
        $this->typeIsTheory = (bool) $t->is_theory;
    }

    public function saveType(): void
    {
        $this->validate([
            'typeName' => 'required|max:100',
            'typeCode' => 'nullable|max:10',
        ]);

        $data = [
            'name'      => $this->typeName,
            'code'      => strtoupper(trim($this->typeCode)) ?: null,
            'is_theory' => $this->typeIsTheory,
        ];

        if ($this->editTypeId) {
            SpaceType::findOrFail($this->editTypeId)->update($data);
            $this->success('Type updated.', position: 'toast-top toast-center');
        } else {
            SpaceType::create($data);
            $this->success('Type added.', position: 'toast-top toast-center');
        }

        $this->reset(['editTypeId', 'typeName', 'typeCode', 'typeIsTheory']);
        $this->loadOptions();
    }

    public function confirmDeleteType(int $id): void
    {
        $this->deleteTypeId = $id;
        $this->delTypeModal = true;
    }

    public function deleteType(): void
    {
        SpaceType::findOrFail($this->deleteTypeId)->delete();
        $this->delTypeModal  = false;
        $this->deleteTypeId  = null;
        $this->warning('Type deleted.', position: 'toast-top toast-center');
        $this->loadOptions();
    }

    // ── Space Claims ─────────────────────────────────────────────────────────

    public function acceptClaim(int $id): void
    {
        SpaceClaim::findOrFail($id)->update(['status' => 'accepted', 'responded_at' => now()]);
        $this->success('Claim accepted.', position: 'toast-top toast-center');
    }

    public function rejectClaim(int $id): void
    {
        SpaceClaim::findOrFail($id)->update(['status' => 'rejected', 'responded_at' => now()]);
        $this->warning('Claim rejected.', position: 'toast-top toast-center');
    }

    // ── Import ────────────────────────────────────────────────────────────────

    public function downloadTemplate(): mixed
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new SpaceTemplateExport(),
            'space_template.xlsx'
        );
    }

    public function import(): void
    {
        $this->validate(['importFile' => 'required|file|mimes:xlsx,xls|max:5120']);

        $client = $this->client();
        if (! $client) {
            $this->error('Client not found.', position: 'toast-top toast-center');
            return;
        }

        $ext      = $this->importFile->getClientOriginalExtension();
        $filename = 'space_' . uniqid() . '.' . $ext;
        $destDir  = storage_path('app/imports/space');
        $destPath = $destDir . '/' . $filename;

        if (! is_dir($destDir)) mkdir($destDir, 0775, true);
        copy($this->importFile->getRealPath(), $destPath);

        SpaceImportJob::dispatch($destPath, $client->id);

        $this->reset('importFile');
        $this->importModal = false;
        $this->importing   = true;
        $this->info('Import queued. You will be notified when done.', position: 'toast-top toast-center');
    }

    public function getListeners(): array
    {
        return ['echo:space-import,.SpaceImportEvent' => 'onImportDone'];
    }

    public function onImportDone(array $event): void
    {
        $this->importing = false;
        $this->loadOptions();
        ($event['status'] ?? '') === 'success'
            ? $this->success($event['message'], position: 'toast-top toast-center')
            : $this->error($event['message'],   position: 'toast-top toast-center');
    }

    public function with(): array
    {
        $client   = $this->client();
        $clientId = $client?->id;

        $headers = [
            ['key' => 'code',           'label' => 'Code',     'class' => 'w-1/12'],
            ['key' => 'name',           'label' => 'Name',     'class' => 'w-3/12', 'sortable' => true],
            ['key' => 'type_label',     'label' => 'Type',     'class' => 'w-1/12', 'sortable' => true],
            ['key' => 'building_label', 'label' => 'Building', 'class' => 'w-2/12', 'sortable' => true],
            ['key' => 'floor',          'label' => 'Floor',    'class' => 'w-1/12 text-center'],
            ['key' => 'capacity',       'label' => 'Cap.',     'class' => 'w-1/12 text-center'],
            ['key' => 'action',         'label' => '',         'class' => 'w-2/12 text-right'],
        ];

        $spaceTypes = SpaceType::orderBy('name')->get();

        $spaces = $clientId
            ? Space::with(['type:id,name,code', 'building:id,name,code'])
                ->where('fetnet_space.client_id', $clientId)
                ->when($this->filterBuildingId, fn($q) => $q->where('building_id', $this->filterBuildingId))
                ->when($this->search, fn($q) => $q
                    ->where('fetnet_space.name', 'ilike', "%{$this->search}%")
                    ->orWhere('fetnet_space.code', 'ilike', "%{$this->search}%"))
                ->when($this->sortBy['column'] === 'name',           fn($q) => $q->orderBy('fetnet_space.name', $this->sortBy['direction']))
                ->when($this->sortBy['column'] === 'type_label',     fn($q) => $q->leftJoin('fetnet_space_type as st', 'fetnet_space.type_id', '=', 'st.id')->orderBy('st.name', $this->sortBy['direction'])->select('fetnet_space.*'))
                ->when($this->sortBy['column'] === 'building_label', fn($q) => $q->leftJoin('fetnet_building as fb', 'fetnet_space.building_id', '=', 'fb.id')->orderBy('fb.name', $this->sortBy['direction'])->select('fetnet_space.*'))
                ->when(! in_array($this->sortBy['column'], ['name', 'type_label', 'building_label']), fn($q) => $q->orderBy('fetnet_space.name'))
                ->paginate(15)
                ->through(fn($s) => tap($s, fn($item) => [
                    $item->type_label     = $s->type?->code ?? '—',
                    $item->building_label = $s->building
                        ? ($s->building->code ? "[{$s->building->code}] {$s->building->name}" : $s->building->name)
                        : '—',
                ]))
            : collect();

        $buildings = $clientId
            ? Building::where('client_id', $clientId)->orderBy('name')->get()
            : collect();

        $pendingClaims = $clientId
            ? SpaceClaim::with(['space:id,name,code', 'program:id,abbrev,name'])
                ->whereHas('space', fn($q) => $q->where('client_id', $clientId))
                ->where('status', 'pending')
                ->orderBy('created_at')
                ->get()
            : collect();

        return compact('headers', 'spaces', 'buildings', 'spaceTypes', 'pendingClaims');
    }
}; ?>

<div>
    <x-header title="Spaces / Rooms" subtitle="Manage rooms and spaces" separator>
        <x-slot:actions>
            {{-- Claim notification bell --}}
            <div class="relative">
                <x-button icon="o-bell" class="btn-ghost btn-sm btn-square"
                          wire:click="$set('claimsModal', true)" tooltip="Space Claim Requests" />
                @if($pendingClaims->isNotEmpty())
                    <span class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-warning text-warning-content text-[10px] font-bold pointer-events-none">
                        {{ $pendingClaims->count() }}
                    </span>
                @endif
            </div>
            <x-input placeholder="Search..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
            <x-button label="Types" icon="o-tag" class="btn-ghost btn-sm"
                      wire:click="openTypeManager" />
            <x-button label="Buildings" icon="o-building-office-2" class="btn-ghost btn-sm"
                      wire:click="openBuildingManager" />
            <x-button label="Import" icon="o-arrow-up-tray" class="btn-ghost btn-sm"
                      wire:click="$set('importModal', true)"
                      :disabled="$importing" :spinner="$importing" />
            <x-button label="Add" icon="o-plus" class="btn-primary" wire:click="openCreate" />
        </x-slot:actions>
    </x-header>

    {{-- Building filter --}}
    <div class="mb-4 w-64">
        <x-choices single searchable clearable
                   wire:model.live="filterBuildingId"
                   :search-function="'searchBuildings'"
                   :options="$buildingOptions"
                   placeholder="— All Buildings —" />
    </div>

    <x-card>
        <x-table :striped="true" :headers="$headers" :rows="$spaces" with-pagination container-class="overflow-hidden"
                 :sort-by="$sortBy" wire:sort="sortBy">
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

    {{-- Add/Edit Space Modal --}}
    <x-modal wire:model="modal" :title="$editId ? 'Edit Space' : 'Add Space'"
             separator class="modal-bottom" box-class="!max-w-lg mx-auto !rounded-t-2xl !mb-14">
        <x-form wire:submit="save" class="space-y-4">
            <input type="text" class="w-0 h-0 opacity-0 absolute pointer-events-none" autofocus />
            <div class="grid grid-cols-3 gap-3">
                <div class="col-span-2">
                    <x-input label="Name" wire:model="name" placeholder="Lab Komputer A" required />
                </div>
                <x-input label="Code" wire:model="code" placeholder="LAB-A" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <x-choices label="Type" single wire:model="typeId"
                           :options="$typeOptions" placeholder="— None —" />
                <x-choices label="Building" single searchable clearable wire:model="buildingId"
                           :search-function="'searchBuildings'"
                           :options="$buildingOptions" placeholder="— None —" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <x-input label="Floor" wire:model="floor" placeholder="2" />
                <x-input label="Capacity" wire:model="capacity" type="number" placeholder="40" />
            </div>
            <x-choices label="Faculty (optional)" single wire:model="facultyId"
                       :options="$facultyOptions" placeholder="— None —" />
            <x-slot:actions>
                <x-button label="Cancel" icon="o-x-circle"     wire:click="$set('modal', false)" />
                <x-button label="Save"   icon="o-check-circle" type="submit" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    {{-- Building Manager Modal --}}
    <x-modal wire:model="buildingModal" title="Manage Buildings"
             separator class="modal-bottom" box-class="!max-w-lg mx-auto !rounded-t-2xl !mb-14">
        <div class="space-y-4">
            <input type="text" class="w-0 h-0 opacity-0 absolute pointer-events-none" autofocus />

            {{-- Building list --}}
            @if($buildings->isNotEmpty())
                <div class="divide-y divide-base-200">
                    @foreach($buildings as $b)
                        <div class="flex items-center justify-between py-2">
                            <div>
                                <span class="font-medium text-sm">{{ $b->name }}</span>
                                @if($b->code)
                                    <x-badge value="{{ $b->code }}" class="badge-neutral badge-xs ml-2" />
                                @endif
                            </div>
                            <div class="flex gap-1">
                                <x-button icon="o-pencil" class="btn-ghost btn-xs btn-square"
                                          wire:click="openEditBuilding({{ $b->id }})" />
                                <x-button icon="o-trash"  class="btn-ghost btn-xs btn-square text-error"
                                          wire:click="confirmDeleteBuilding({{ $b->id }})" />
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-base-content/40 text-center py-3">No buildings yet.</p>
            @endif

            {{-- Add/Edit building inline form --}}
            <div class="border border-base-200 rounded-xl p-4 space-y-3">
                <p class="text-sm font-semibold text-base-content/70">
                    {{ $editBuildingId ? 'Edit Building' : 'Add Building' }}
                </p>
                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-2">
                        <x-input label="Name" wire:model="buildingName" placeholder="Gedung A" />
                    </div>
                    <x-input label="Code" wire:model="buildingCode" placeholder="GDA" />
                </div>
                <div class="flex gap-2">
                    @if($editBuildingId)
                        <x-button label="Cancel Edit" icon="o-x-mark" class="btn-ghost btn-sm"
                                  wire:click="$set('editBuildingId', null); $set('buildingName', ''); $set('buildingCode', '')" />
                    @endif
                    <x-button :label="$editBuildingId ? 'Update' : 'Add Building'"
                              icon="{{ $editBuildingId ? 'o-check' : 'o-plus' }}"
                              class="btn-primary btn-sm"
                              wire:click="saveBuilding" />
                </div>
            </div>
        </div>
    </x-modal>

    {{-- Delete Building Confirm --}}
    <x-modal wire:model="delBuildingModal" title="Delete Building" box-class="!max-w-sm">
        <p class="text-base-content/70 text-sm">Delete this building? Spaces assigned to it will have their building cleared.</p>
        <x-slot:actions>
            <x-button label="Cancel" icon="o-x-circle" wire:click="$set('delBuildingModal', false)" />
            <x-button label="Delete" icon="o-trash"    class="btn-error" wire:click="deleteBuilding" />
        </x-slot:actions>
    </x-modal>

    {{-- Space Type Manager Modal --}}
    <x-modal wire:model="typeModal" title="Manage Space Types"
             separator class="modal-bottom" box-class="!max-w-lg mx-auto !rounded-t-2xl !mb-14">
        <div class="space-y-4">
            <input type="text" class="w-0 h-0 opacity-0 absolute pointer-events-none" autofocus />

            {{-- Type list --}}
            @if($spaceTypes->isNotEmpty())
                <div class="divide-y divide-base-200">
                    @foreach($spaceTypes as $t)
                        <div class="flex items-center justify-between py-2">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-sm">{{ $t->name }}</span>
                                @if($t->code)
                                    <x-badge value="{{ $t->code }}" class="badge-neutral badge-xs" />
                                @endif
                                @if($t->is_theory)
                                    <x-badge value="theory" class="badge-warning badge-xs badge-dash" />
                                @endif
                            </div>
                            <div class="flex gap-1">
                                <x-button icon="o-pencil" class="btn-ghost btn-xs btn-square"
                                          wire:click="openEditType({{ $t->id }})" />
                                <x-button icon="o-trash"  class="btn-ghost btn-xs btn-square text-error"
                                          wire:click="confirmDeleteType({{ $t->id }})" />
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-base-content/40 text-center py-3">No types yet.</p>
            @endif

            {{-- Add/Edit form --}}
            <div class="border border-base-200 rounded-xl p-4 space-y-3">
                <p class="text-sm font-semibold text-base-content/70">
                    {{ $editTypeId ? 'Edit Type' : 'Add Type' }}
                </p>
                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-2">
                        <x-input label="Name" wire:model="typeName" placeholder="Laboratory" />
                    </div>
                    <x-input label="Code" wire:model="typeCode" placeholder="LAB" />
                </div>
                <x-toggle label="Theory (not claimable by programs)" wire:model="typeIsTheory" class="toggle-warning" />
                <div class="flex gap-2">
                    @if($editTypeId)
                        <x-button label="Cancel Edit" icon="o-x-mark" class="btn-ghost btn-sm"
                                  wire:click="$set('editTypeId', null); $set('typeName', ''); $set('typeCode', ''); $set('typeIsTheory', false)" />
                    @endif
                    <x-button :label="$editTypeId ? 'Update' : 'Add Type'"
                              icon="{{ $editTypeId ? 'o-check' : 'o-plus' }}"
                              class="btn-primary btn-sm"
                              wire:click="saveType" />
                </div>
            </div>
        </div>
    </x-modal>

    {{-- Delete Type Confirm --}}
    <x-modal wire:model="delTypeModal" title="Delete Space Type" box-class="!max-w-sm">
        <p class="text-base-content/70 text-sm">Delete this type? Spaces assigned to it will have their type cleared.</p>
        <x-slot:actions>
            <x-button label="Cancel" icon="o-x-circle" wire:click="$set('delTypeModal', false)" />
            <x-button label="Delete" icon="o-trash"    class="btn-error" wire:click="deleteType" />
        </x-slot:actions>
    </x-modal>

    {{-- Import Modal --}}
    <x-modal wire:model="importModal" title="Import Spaces from Excel"
             separator class="modal-bottom" box-class="!max-w-md mx-auto !rounded-t-2xl !mb-14">
        <div class="space-y-4">
            <input type="text" class="w-0 h-0 opacity-0 absolute pointer-events-none" autofocus />
            <x-alert title="Required: name"
                     description="type_code must match a space type code (e.g. LAB, CLS). building_code must match an existing building code. Optional: code, floor, capacity."
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

    {{-- Delete Space Confirm --}}
    {{-- Space Claim Requests Modal --}}
    <x-modal wire:model="claimsModal" title="Space Claim Requests"
             separator class="modal-bottom" box-class="!max-w-2xl mx-auto !rounded-t-2xl !mb-14">
        <div>
            @if($pendingClaims->isNotEmpty())
                <div class="overflow-hidden">
                    <table class="table table-sm table-zebra w-full">
                        <thead>
                            <tr class="text-base-content/60 text-xs">
                                <th class="w-7/12">Room Name</th>
                                <th class="w-1/12">Code</th>
                                <th class="w-2/12">Req. By</th>
                                <th class="w-2/12 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pendingClaims as $claim)
                            <tr class="hover:bg-base-200/40">
                                <td class="font-medium text-sm">{{ $claim->space?->name ?? '—' }}</td>
                                <td>
                                    @if($claim->space?->code)
                                        <x-badge value="{{ $claim->space->code }}" class="badge-neutral badge-sm" />
                                    @else
                                        <span class="text-base-content/30">—</span>
                                    @endif
                                </td>
                                <td class="text-sm font-semibold">{{ $claim->program?->abbrev ?? '—' }}</td>
                                <td>
                                    <div class="flex justify-end gap-1">
                                        <x-button icon="o-check-circle" class="btn-success btn-xs btn-square"
                                                  wire:click="acceptClaim({{ $claim->id }})" tooltip="Accept" />
                                        <x-button icon="o-x-circle" class="btn-error btn-xs btn-square"
                                                  wire:click="rejectClaim({{ $claim->id }})" tooltip="Reject" />
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="flex flex-col items-center py-10 text-base-content/40 gap-2">
                    <x-icon name="o-bell-slash" class="w-10 h-10" />
                    <p class="text-sm">No pending claim requests.</p>
                </div>
            @endif
        </div>
        <x-slot:actions>
            <x-button label="Close" icon="o-x-circle" wire:click="$set('claimsModal', false)" />
        </x-slot:actions>
    </x-modal>

    <x-modal wire:model="delModal" title="Delete Space" box-class="!max-w-sm">
        <p class="text-base-content/70 text-sm">Delete this space/room?</p>
        <x-slot:actions>
            <x-button label="Cancel" icon="o-x-circle" wire:click="$set('delModal', false)" />
            <x-button label="Delete" icon="o-trash"    class="btn-error" wire:click="delete" />
        </x-slot:actions>
    </x-modal>

    <x-toast />
</div>

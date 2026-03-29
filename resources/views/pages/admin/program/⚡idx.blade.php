<?php

use App\Models\FetNet\Client;
use App\Models\FetNet\Cluster;
use App\Models\FetNet\ClusterBase;
use App\Models\FetNet\Program;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.admin')] class extends Component
{
    use WithPagination, Toast;

    public string $search      = '';
    public bool   $createModal = false;
    public bool   $loginModal  = false;
    public ?int   $loginId     = null;
    public bool   $editModal   = false;

    // Create fields
    public string $name           = '';
    public string $code           = '';
    public string $abbrev         = '';
    public string $email          = '';
    public string $password       = '';
    public ?int   $cluster_base_id = null;

    // Edit fields
    public ?int   $editId        = null;
    public string $editName      = '';
    public string $editCode      = '';
    public string $editAbbrev    = '';
    public string $editEmail     = '';
    public ?int   $editClusterId = null;

    // Create Cluster fields
    public bool   $clusterModal    = false;
    public string $clusterCode     = '';
    public string $clusterName     = '';
    public string $clusterNameEng  = '';
    public string $clusterContext  = 'create'; // 'create' | 'edit'

    public array $clustersOptions = [];

    public array $headers = [
        ['key' => 'abbrev',       'label' => 'Kode',    'class' => 'w-1/12'],
        ['key' => 'name',         'label' => 'Nama',    'class' => 'w-4/12'],
        ['key' => 'user_email',   'label' => 'Email',   'class' => 'w-3/12'],
        ['key' => 'cluster_name', 'label' => 'Cluster', 'class' => 'w-2/12'],
        ['key' => 'action',       'label' => '',        'class' => 'w-2/12 text-right'],
    ];

    public function mount(): void { $this->loadClusters(); }

    private function client(): ?Client
    {
        return Client::where('user_id', auth()->id())->first();
    }

    private function loadClusters(): void
    {
        $client = $this->client();
        $this->clustersOptions = $client
            ? ClusterBase::where('client_id', $client->id)
                ->get(['id', 'code', 'name'])
                ->map(fn($c) => ['id' => $c->id, 'name' => "{$c->code} | {$c->name}"])
                ->toArray()
            : [];
    }

    private function isClusterLevel(): bool
    {
        return $this->client()?->level?->code === 'CLU';
    }

    public function openClusterModal(string $context): void
    {
        $this->reset(['clusterCode', 'clusterName', 'clusterNameEng']);
        $this->clusterContext = $context;
        $this->clusterModal   = true;
    }

    public function saveCluster(): void
    {
        $this->validate([
            'clusterCode' => 'required|max:10',
            'clusterName' => 'required|max:100',
        ], [], [
            'clusterCode' => 'Code',
            'clusterName' => 'Name',
        ]);

        $cluster = ClusterBase::create([
            'client_id' => $this->client()->id,
            'code'      => $this->clusterCode,
            'name'      => $this->clusterName,
            'name_eng'  => $this->clusterNameEng ?: null,
        ]);

        $this->loadClusters();

        if ($this->clusterContext === 'create') {
            $this->cluster_base_id = $cluster->id;
        } else {
            $this->editClusterId = $cluster->id;
        }

        $this->clusterModal = false;
        $this->success('Cluster created.', position: 'toast-top toast-center');
    }

    public function updatedSearch(): void { $this->resetPage(); }

    public function openCreate(): void
    {
        $this->reset(['name', 'code', 'abbrev', 'email', 'password', 'cluster_base_id']);
        if ($this->isClusterLevel()) {
            $this->cluster_base_id = $this->client()?->cluster?->id;
        }
        $this->loadClusters();
        $this->createModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'name'     => 'required|unique:institution_program,name',
            'code'     => 'required|unique:institution_program,code',
            'abbrev'   => 'required|unique:institution_program,abbrev',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:6',
        ]);

        $user = User::create([
            'name'     => $this->abbrev,
            'email'    => $this->email,
            'password' => Hash::make($this->password),
        ]);
        $user->assignRole('user');

        $program = Program::create([
            'name'      => $this->name,
            'code'      => $this->code,
            'abbrev'    => $this->abbrev,
            'client_id' => $this->client()->id,
            'user_id'   => $user->id,
        ]);

        if ($this->cluster_base_id) {
            Cluster::create([
                'program_id'      => $program->id,
                'cluster_base_id' => $this->cluster_base_id,
            ]);
        }

        $this->success('Program added successfully.', position: 'toast-top toast-center');
        $this->createModal = false;
    }

    public function openEdit(int $id): void
    {
        $program             = Program::with(['user', 'cluster'])->findOrFail($id);
        $this->editId        = $id;
        $this->editName      = $program->name;
        $this->editCode      = $program->code;
        $this->editAbbrev    = $program->abbrev;
        $this->editEmail     = $program->user?->email ?? '';
        $this->editClusterId = $program->cluster?->cluster_base_id;
        $this->loadClusters();
        $this->editModal     = true;
    }

    public function update(): void
    {
        $this->validate([
            'editName'  => 'required',
            'editCode'  => 'required',
            'editEmail' => 'required|email',
        ]);

        $program = Program::findOrFail($this->editId);
        $program->update([
            'name'   => $this->editName,
            'code'   => $this->editCode,
            'abbrev' => $this->editAbbrev,
        ]);

        $program->user?->update(['email' => $this->editEmail]);

        if ($this->editClusterId) {
            Cluster::updateOrCreate(
                ['program_id' => $this->editId],
                ['cluster_base_id' => $this->editClusterId]
            );
        }

        $this->success('Program updated.', position: 'toast-top toast-center');
        $this->editModal = false;
    }

    public function confirmLoginAs(int $id): void
    {
        $this->loginId    = $id;
        $this->loginModal = true;
    }

    public function loginAs(): mixed
    {
        $program = Program::with('user')->findOrFail($this->loginId);
        auth()->login($program->user);
        session()->save();
        return redirect()->route('program.idx');
    }

    public function with(): array
    {
        $client = $this->client();

        return [
            'programs' => $client
                ? Program::with(['user', 'cluster.base'])
                    ->where('client_id', $client->id)
                    ->when($this->search, fn($q) => $q
                        ->where('name', 'ilike', "%{$this->search}%")
                        ->orWhere('abbrev', 'ilike', "%{$this->search}%"))
                    ->paginate(10)
                    ->through(fn($p) => tap($p, fn($item) => [
                        $item->user_email   = $p->user?->email ?? '-',
                        $item->cluster_name = $p->cluster?->base?->code ?? '-',
                    ]))
                : collect(),
        ];
    }
}; ?>

<div>
    <x-header title="Study Programs" subtitle="Manage study program data" separator>
        <x-slot:actions>
            <x-input placeholder="Search..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
            <x-button label="Add" icon="o-plus" wire:click="openCreate" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-card>
        <x-table :striped="true" :headers="$headers" :rows="$programs" with-pagination container-class="overflow-hidden">
            @scope('cell_action', $row)
                <div class="flex justify-end gap-1">
                    <x-button icon="o-pencil-square" class="btn-ghost btn-sm btn-square"
                              wire:click="openEdit({{ $row->id }})" tooltip="Edit" />
                    <x-button icon="o-arrow-right-on-rectangle" class="btn-ghost btn-sm btn-square"
                              wire:click="confirmLoginAs({{ $row->id }})" tooltip="Login as" />
                </div>
            @endscope
        </x-table>
    </x-card>

    <x-modal wire:model="createModal" title="Add Study Program" separator class="modal-bottom" box-class="!max-w-xl mx-auto !rounded-t-2xl !mb-14">
        <x-form wire:submit="save" class="space-y-4">
            <div class="w-5/6">
                <x-input label="Program Name" wire:model="name" required />
            </div>
            <div class="grid grid-cols-4 gap-3">
                <x-input label="Code" wire:model="code" required />
                <div class="col-span-2">
                    <x-input label="Abbreviation" wire:model="abbrev" required />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <x-input    label="Email"    wire:model="email"    type="email" required />
                <x-password label="Password" wire:model="password" required />
            </div>
            <div class="flex items-end gap-2 w-3/4">
                <div class="flex-1">
                    <x-choices label="Cluster" single searchable wire:model="cluster_base_id"
                               :options="$clustersOptions" placeholder="-- Select Cluster --" />
                </div>
                <x-button icon="o-plus-circle" class="btn-ghost btn-square btn-sm mb-1"
                          wire:click="openClusterModal('create')" tooltip="Create new cluster" />
            </div>
            <x-slot:actions>
                <x-button label="Cancel" icon="o-x-circle"     wire:click="$set('createModal', false)" />
                <x-button label="Save"   icon="o-check-circle" type="submit" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    <x-modal wire:model="editModal" title="Edit Study Program" separator class="modal-bottom" box-class="!max-w-xl mx-auto !rounded-t-2xl !mb-14">
        <x-form wire:submit="update" class="space-y-4">
            <div class="w-5/6">
                <x-input label="Program Name" wire:model="editName" required />
            </div>
            <div class="grid grid-cols-4 gap-3">
                <x-input label="Code" wire:model="editCode" required />
                <div class="col-span-2">
                    <x-input label="Abbreviation" wire:model="editAbbrev" />
                </div>
            </div>
            <div class="w-3/4">
                <x-input label="Email" wire:model="editEmail" type="email" required />
            </div>
            <div class="flex items-end gap-2 w-3/4">
                <div class="flex-1">
                    <x-choices label="Cluster" single searchable wire:model="editClusterId"
                               :options="$clustersOptions" placeholder="-- Select Cluster --" />
                </div>
                <x-button icon="o-plus-circle" class="btn-ghost btn-square btn-sm mb-1"
                          wire:click="openClusterModal('edit')" tooltip="Create new cluster" />
            </div>
            <x-slot:actions>
                <x-button label="Cancel" icon="o-x-circle"     wire:click="$set('editModal', false)" />
                <x-button label="Save"   icon="o-check-circle" type="submit" class="btn-primary" spinner="update" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    <x-modal wire:model="loginModal" title="Login as Program" box-class="!max-w-xs mx-auto">
        <p class="text-base-content/70 text-sm">Login as this study program? Your current session will be replaced.</p>
        <x-slot:actions>
            <x-button label="Cancel" icon="o-x-circle"                 wire:click="$set('loginModal', false)" />
            <x-button label="Login"  icon="o-arrow-right-on-rectangle" class="btn-primary" wire:click="loginAs" />
        </x-slot:actions>
    </x-modal>

    {{-- Create Cluster Modal --}}
    <x-modal wire:model="clusterModal" title="Create Cluster" separator
             class="modal-bottom" box-class="!max-w-sm mx-auto !rounded-t-2xl !mb-14">
        <x-form wire:submit="saveCluster" class="space-y-3">
            <input type="text" class="w-0 h-0 opacity-0 absolute pointer-events-none" autofocus />
            <x-input label="Code"     wire:model="clusterCode"    placeholder="e.g. CLU-A" required />
            <x-input label="Name"     wire:model="clusterName"    placeholder="Cluster name" required />
            <x-input label="Name (EN)" wire:model="clusterNameEng" placeholder="English name (optional)" />
            <x-slot:actions>
                <x-button label="Cancel" icon="o-x-circle"     wire:click="$set('clusterModal', false)" />
                <x-button label="Save"   icon="o-check-circle" type="submit" class="btn-primary" spinner="saveCluster" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    <x-toast />
</div>

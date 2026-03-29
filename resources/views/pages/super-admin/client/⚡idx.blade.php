<?php

use App\Models\FetNet\Client;
use App\Models\FetNet\ClientConfig;
use App\Models\FetNet\ClientLevel;
use App\Models\FetNet\ClusterBase;
use App\Models\FetNet\Faculty;
use App\Models\FetNet\University;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.super-admin')] class extends Component
{
    use WithPagination, Toast;

    public string $search      = '';
    public bool   $modal       = false;
    public bool   $editModal   = false;
    public ?int   $editId      = null;
    public bool   $loginModal  = false;
    public ?int   $loginId     = null;

    // Create fields
    public string $username      = '';
    public string $email         = '';
    public string $password      = '';
    public string $description   = '';
    public ?int   $level_id      = null;
    public ?int   $university_id = null;
    public ?int   $faculty_id    = null;
    public string $clusterCode   = '';
    public string $clusterName   = '';

    // Edit fields
    public string $editDescription    = '';
    public ?int   $editLevelId        = null;
    public ?int   $editUniversityId   = null;
    public ?int   $editFacultyId      = null;
    public string $editClusterCode    = '';
    public string $editClusterName    = '';
    public string $editEmail          = '';

    public array $levelsOptions        = [];
    public array $universitiesOptions  = [];
    public array $facultiesOptions     = [];
    public array $editFacultiesOptions = [];

    // Faculty quick-create
    public bool   $facultyModal      = false;
    public string $facultyContext    = 'create'; // 'create' | 'edit'
    public ?int   $facultyUniversityId = null;
    public string $newFacultyCode    = '';
    public string $newFacultyName    = '';
    public string $newFacultyNameEng = '';

    // University quick-create (nested inside faculty modal)
    public bool   $universityModal      = false;
    public string $newUniversityCode    = '';
    public string $newUniversityName    = '';
    public string $newUniversityNameEng = '';

    public array $headers = [
        ['key' => 'user_name',  'label' => 'Username', 'class' => 'w-2/12 max-w-0 truncate'],
        ['key' => 'user_email', 'label' => 'Email',    'class' => 'w-3/12 max-w-0 truncate'],
        ['key' => 'university', 'label' => 'Univ',     'class' => 'w-1/12'],
        ['key' => 'faculty',    'label' => 'Faculty',  'class' => 'w-1/12'],
        ['key' => 'level',      'label' => 'Level',    'class' => 'w-1/12'],
        ['key' => 'action',     'label' => '',         'class' => 'w-2/12 text-right'],
    ];

    public function mount(): void { $this->loadOptions(); }

    private function loadOptions(): void
    {
        $this->levelsOptions = ClientLevel::all(['id', 'code', 'level'])
            ->map(fn($l) => ['id' => $l->id, 'name' => "{$l->code} | {$l->level}"])
            ->toArray();

        $this->universitiesOptions = University::orderBy('code')->get(['id', 'code', 'name'])
            ->map(fn($u) => ['id' => $u->id, 'name' => "{$u->code} | {$u->name}"])
            ->toArray();

        $this->facultiesOptions = Faculty::when(
            $this->university_id, fn($q) => $q->where('university_id', $this->university_id)
        )->orderBy('code')->get(['id', 'code', 'name'])
            ->map(fn($f) => ['id' => $f->id, 'name' => "{$f->code} | {$f->name}"])
            ->toArray();
    }

    private function loadEditFaculties(): void
    {
        $this->editFacultiesOptions = Faculty::when(
            $this->editUniversityId, fn($q) => $q->where('university_id', $this->editUniversityId)
        )->orderBy('code')->get(['id', 'code', 'name'])
            ->map(fn($f) => ['id' => $f->id, 'name' => "{$f->code} | {$f->name}"])
            ->toArray();
    }

    public function updatedSearch(): void { $this->resetPage(); }

    public function updatedUniversityId(): void
    {
        $this->faculty_id = null;
        $this->loadOptions();
    }

    public function updatedEditUniversityId(): void
    {
        $this->editFacultyId = null;
        $this->loadEditFaculties();
    }

    public function openCreate(): void
    {
        $this->reset(['username', 'email', 'password', 'description',
                      'level_id', 'university_id', 'faculty_id', 'clusterCode', 'clusterName']);
        $this->loadOptions();
        $this->modal = true;
    }

    public function openEdit(int $id): void
    {
        $client = Client::with(['user', 'cluster'])->findOrFail($id);

        $this->editId           = $id;
        $this->editDescription  = $client->description ?? '';
        $this->editLevelId      = $client->client_level_id;
        $this->editUniversityId = $client->university_id;
        $this->editFacultyId    = $client->faculty_id;
        $this->editEmail        = $client->user?->email ?? '';
        $this->editClusterCode  = $client->cluster?->code ?? '';
        $this->editClusterName  = $client->cluster?->name ?? '';

        $this->loadEditFaculties();
        $this->editModal = true;
    }

    public function isClusterLevel(): bool
    {
        if (! $this->level_id) return false;
        return ClientLevel::find($this->level_id)?->code === 'CLU';
    }

    public function isEditClusterLevel(): bool
    {
        if (! $this->editLevelId) return false;
        return ClientLevel::find($this->editLevelId)?->code === 'CLU';
    }

    protected function rules(): array
    {
        $rules = [
            'username'      => 'required|unique:users,name',
            'email'         => 'required|email|unique:users,email',
            'password'      => 'required|min:6',
            'description'   => 'required',
            'level_id'      => 'required|exists:fetnet_client_level,id',
            'university_id' => 'required|exists:institution_university,id',
            'faculty_id'    => 'required|exists:institution_faculty,id',
        ];

        if ($this->isClusterLevel()) {
            $rules['clusterCode'] = 'required';
            $rules['clusterName'] = 'required';
        }

        return $rules;
    }

    public function save(): void
    {
        $this->validate();

        $user = User::create([
            'name'     => $this->username,
            'email'    => $this->email,
            'password' => Hash::make($this->password),
        ]);
        $user->assignRole('admin');

        $client = Client::create([
            'user_id'         => $user->id,
            'university_id'   => $this->university_id,
            'faculty_id'      => $this->faculty_id,
            'client_level_id' => $this->level_id,
            'description'     => $this->description,
        ]);

        $user->update(['client_id' => $client->id]);

        ClientConfig::create([
            'client_id'       => $client->id,
            'number_of_days'  => 0,
            'number_of_hours' => 0,
        ]);

        if ($this->isClusterLevel()) {
            ClusterBase::create([
                'client_id' => $client->id,
                'code'      => $this->clusterCode,
                'name'      => $this->clusterName,
            ]);
        }

        $this->success('Client added successfully.', position: 'toast-top toast-center');
        $this->modal = false;
    }

    public function update(): void
    {
        $this->validate([
            'editDescription'  => 'required',
            'editLevelId'      => 'required|exists:fetnet_client_level,id',
            'editUniversityId' => 'required|exists:institution_university,id',
            'editFacultyId'    => 'required|exists:institution_faculty,id',
            'editEmail'        => 'required|email',
        ]);

        $client = Client::with(['user', 'cluster'])->findOrFail($this->editId);

        $client->update([
            'description'     => $this->editDescription,
            'client_level_id' => $this->editLevelId,
            'university_id'   => $this->editUniversityId,
            'faculty_id'      => $this->editFacultyId,
        ]);

        $client->user?->update(['email' => $this->editEmail]);

        if ($this->isEditClusterLevel()) {
            ClusterBase::updateOrCreate(
                ['client_id' => $client->id],
                ['code' => $this->editClusterCode, 'name' => $this->editClusterName]
            );
        } else {
            ClusterBase::where('client_id', $client->id)->delete();
        }

        $this->success('Client updated successfully.', position: 'toast-top toast-center');
        $this->editModal = false;
    }

    public function openFacultyCreate(string $context): void
    {
        $this->facultyContext      = $context;
        $this->facultyUniversityId = $context === 'edit' ? $this->editUniversityId : $this->university_id;
        $this->newFacultyCode      = '';
        $this->newFacultyName      = '';
        $this->newFacultyNameEng   = '';
        $this->facultyModal        = true;
    }

    public function saveFaculty(): void
    {
        $this->validate([
            'facultyUniversityId' => 'required|exists:institution_university,id',
            'newFacultyCode'      => 'required|string|max:10',
            'newFacultyName'      => 'required|string|max:100',
            'newFacultyNameEng'   => 'nullable|string|max:100',
        ]);

        $faculty = Faculty::create([
            'code'          => $this->newFacultyCode,
            'name'          => $this->newFacultyName,
            'name_eng'      => $this->newFacultyNameEng ?: null,
            'university_id' => $this->facultyUniversityId,
        ]);

        // Sync university selection back to parent modal and reload its faculty list
        if ($this->facultyContext === 'edit') {
            $this->editUniversityId = $this->facultyUniversityId;
            $this->loadEditFaculties();
            $this->editFacultyId = $faculty->id;
        } else {
            $this->university_id = $this->facultyUniversityId;
            $this->loadOptions();
            $this->faculty_id = $faculty->id;
        }

        $this->facultyModal = false;
        $this->success("Faculty '{$faculty->name}' added.", position: 'toast-top toast-center');
    }

    public function openUniversityCreate(): void
    {
        $this->newUniversityCode    = '';
        $this->newUniversityName    = '';
        $this->newUniversityNameEng = '';
        $this->universityModal      = true;
    }

    public function saveUniversity(): void
    {
        $this->validate([
            'newUniversityCode' => 'required|string|max:10',
            'newUniversityName' => 'required|string|max:100',
            'newUniversityNameEng' => 'nullable|string|max:100',
        ]);

        $university = University::create([
            'code'     => $this->newUniversityCode,
            'name'     => $this->newUniversityName,
            'name_eng' => $this->newUniversityNameEng ?: null,
        ]);

        // Reload universities and auto-select in faculty modal
        $this->loadOptions();
        $this->facultyUniversityId = $university->id;

        $this->universityModal = false;
        $this->success("University '{$university->name}' added.", position: 'toast-top toast-center');
    }

    public function confirmLoginAs(int $id): void
    {
        $this->loginId    = $id;
        $this->loginModal = true;
    }

    public function loginAs(): mixed
    {
        $client = Client::with('user')->findOrFail($this->loginId);
        auth()->login($client->user);
        session()->save();
        return redirect()->route('admin.idx');
    }

    public function with(): array
    {
        return [
            'clients' => Client::with(['user', 'university', 'faculty', 'level'])
                ->when($this->search, fn($q) => $q->whereHas('user',
                    fn($u) => $u->where('name', 'ilike', "%{$this->search}%")
                ))
                ->paginate(5)
                ->through(fn($c) => tap($c, fn($item) => [
                    $item->user_name  = $c->user?->name ?? '-',
                    $item->user_email = $c->user?->email ?? '-',
                    $item->university = $c->university?->code ?? '-',
                    $item->faculty    = $c->faculty?->code ?? '-',
                    $item->level      = $c->level?->code ?? '-',
                ])),
        ];
    }
}; ?>

<div>
    <x-header title="Clients" subtitle="Manage FetNet clients" separator>
        <x-slot:actions>
            <x-input placeholder="Search username..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
            <x-button label="Add Client" icon="o-plus" class="btn-primary" wire:click="openCreate" />
        </x-slot:actions>
    </x-header>

    <x-card>
        <x-table :striped="true" :headers="$headers" :rows="$clients" with-pagination class="table-fixed" container-class="overflow-hidden">
            @scope('cell_action', $row)
                <div class="flex justify-end gap-1">
                    <x-button icon="o-pencil-square" class="btn-ghost btn-sm btn-square"
                              wire:click="openEdit({{ $row->id }})" tooltip="Edit" />
                    <x-button icon="o-arrow-right-on-rectangle" class="btn-ghost btn-sm btn-square"
                              wire:click="confirmLoginAs({{ $row->id }})" tooltip="Login as client" />
                </div>
            @endscope
        </x-table>
    </x-card>

    {{-- Create Modal --}}
    <x-modal wire:model="modal" title="Add Client" separator box-class="!max-w-2xl mx-auto !rounded-t-2xl !mb-14" class="modal-bottom">
        <x-form wire:submit="save" class="space-y-4">
            <input type="text" class="w-0 h-0 opacity-0 absolute pointer-events-none" autofocus />
            <div class="grid grid-cols-3 gap-3">
                <x-choices label="Level" single searchable wire:model.live="level_id" :options="$levelsOptions" placeholder="Select" required />
                <div class="col-span-2">
                    <x-choices label="Faculty" single searchable wire:model="faculty_id" :options="$facultiesOptions" placeholder="Select faculty" required>
                        <x-slot:append>
                            <x-button icon="o-plus" class="btn-ghost btn-sm rounded-l-none border-l border-base-300" tooltip="Add faculty"
                                      wire:click.prevent="openFacultyCreate('create')" />
                        </x-slot:append>
                    </x-choices>
                </div>
            </div>
            <div class="w-3/4">
                <x-choices label="University" single searchable wire:model.live="university_id" :options="$universitiesOptions" placeholder="Select university" required />
            </div>
            @if($this->isClusterLevel())
                <div class="grid grid-cols-4 gap-3">
                    <x-input label="Code" wire:model="clusterCode" placeholder="GRP1" required />
                    <div class="col-span-3">
                        <x-input label="Cluster Name" wire:model="clusterName" placeholder="Group 1" required />
                    </div>
                </div>
            @endif
            <x-input label="Description" wire:model="description" placeholder="Electrical Engineering Study Program" required />
            <div class="divider text-xs">User Account</div>
            <div class="grid grid-cols-2 gap-3">
                <x-input label="Username" wire:model="username" placeholder="electrical.engineering" required />
                <x-input label="Email"    wire:model="email"    placeholder="name@university.ac.id" type="email" required />
            </div>
            <div class="w-2/3">
                <x-password label="Password" wire:model="password" placeholder="••••••••" right required />
            </div>
            <x-slot:actions>
                <x-button label="Cancel" icon="o-x-circle"     wire:click="$set('modal', false)" />
                <x-button label="Save"   icon="o-check-circle" type="submit" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    {{-- Edit Modal --}}
    <x-modal wire:model="editModal" title="Edit Client" separator box-class="!max-w-2xl mx-auto !rounded-t-2xl !mb-14" class="modal-bottom">
        <x-form wire:submit="update" class="space-y-4">
            <input type="text" class="w-0 h-0 opacity-0 absolute pointer-events-none" autofocus />
            <div class="grid grid-cols-3 gap-3">
                <x-choices label="Level" single searchable wire:model.live="editLevelId" :options="$levelsOptions" placeholder="Select" required />
                <div class="col-span-2">
                    <x-choices label="Faculty" single searchable wire:model="editFacultyId" :options="$editFacultiesOptions" placeholder="Select faculty" required>
                        <x-slot:append>
                            <x-button icon="o-plus" class="btn-ghost btn-sm rounded-l-none border-l border-base-300" tooltip="Add faculty"
                                      wire:click.prevent="openFacultyCreate('edit')" />
                        </x-slot:append>
                    </x-choices>
                </div>
            </div>
            <div class="w-3/4">
                <x-choices label="University" single searchable wire:model.live="editUniversityId" :options="$universitiesOptions" placeholder="Select university" required />
            </div>
            @if($this->isEditClusterLevel())
                <div class="grid grid-cols-4 gap-3">
                    <x-input label="Code" wire:model="editClusterCode" placeholder="GRP1" required />
                    <div class="col-span-3">
                        <x-input label="Cluster Name" wire:model="editClusterName" placeholder="Group 1" required />
                    </div>
                </div>
            @endif
            <x-input label="Description" wire:model="editDescription" required />
            <div class="divider text-xs">User Account</div>
            <div class="w-5/6">
                <x-input label="Email" wire:model="editEmail" type="email" required />
            </div>
            <x-slot:actions>
                <x-button label="Cancel" icon="o-x-circle"     wire:click="$set('editModal', false)" />
                <x-button label="Save"   icon="o-check-circle" type="submit" class="btn-primary" spinner="update" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    <x-modal wire:model="loginModal" title="Login as Client" box-class="!max-w-xs mx-auto">
        <p class="text-base-content/70 text-sm">Login as this client? Your current session will be replaced.</p>
        <x-slot:actions>
            <x-button label="Cancel" icon="o-x-circle"                 wire:click="$set('loginModal', false)" />
            <x-button label="Login"  icon="o-arrow-right-on-rectangle" class="btn-primary" wire:click="loginAs" />
        </x-slot:actions>
    </x-modal>

    {{-- Faculty Quick-Create Sub-Modal --}}
    <x-modal wire:model="facultyModal" title="Add Faculty" separator box-class="!max-w-md mx-auto">
        <x-form wire:submit="saveFaculty" class="space-y-4">
            <input type="text" class="w-0 h-0 opacity-0 absolute pointer-events-none" autofocus />
            <x-choices label="University" single searchable wire:model="facultyUniversityId"
                       :options="$universitiesOptions" placeholder="Select university" required>
                <x-slot:append>
                    <x-button icon="o-plus" class="btn-ghost btn-sm rounded-l-none border-l border-base-300"
                              tooltip="Add university" wire:click.prevent="openUniversityCreate" />
                </x-slot:append>
            </x-choices>
            <div class="grid grid-cols-3 gap-3">
                <x-input label="Code" wire:model="newFacultyCode" placeholder="FTE" required />
                <div class="col-span-2">
                    <x-input label="Name" wire:model="newFacultyName" placeholder="Faculty of Technology" required />
                </div>
            </div>
            <x-input label="English Name" wire:model="newFacultyNameEng" placeholder="Faculty of Technology (optional)" />
            <x-slot:actions>
                <x-button label="Cancel" icon="o-x-circle"     wire:click="$set('facultyModal', false)" />
                <x-button label="Save"   icon="o-check-circle" type="submit" class="btn-primary" spinner="saveFaculty" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    {{-- University Quick-Create Sub-Modal --}}
    <x-modal wire:model="universityModal" title="Add University" separator box-class="!max-w-sm mx-auto">
        <x-form wire:submit="saveUniversity" class="space-y-4">
            <input type="text" class="w-0 h-0 opacity-0 absolute pointer-events-none" autofocus />
            <div class="grid grid-cols-3 gap-3">
                <x-input label="Code" wire:model="newUniversityCode" placeholder="UPI" required />
                <div class="col-span-2">
                    <x-input label="Name" wire:model="newUniversityName" placeholder="Universitas Pendidikan Indonesia" required />
                </div>
            </div>
            <x-input label="English Name" wire:model="newUniversityNameEng" placeholder="Indonesia University of Education (optional)" />
            <x-slot:actions>
                <x-button label="Cancel" icon="o-x-circle"     wire:click="$set('universityModal', false)" />
                <x-button label="Save"   icon="o-check-circle" type="submit" class="btn-primary" spinner="saveUniversity" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    <x-toast />
</div>

<?php

use App\Models\FetNet\Client;
use App\Models\User;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use Spatie\Permission\Models\Role;

new #[Layout('layouts.super-admin')] class extends Component
{
    use WithPagination, Toast;

    public string $search = '';

    // Create / Edit modal (shared)
    public bool    $userModal   = false;
    public bool    $isCreating  = false;
    public ?int    $userId      = null;
    public string  $formName    = '';
    public string  $formEmail   = '';
    public string  $formSso     = '';
    public string  $formPassword = '';
    public ?int    $formRoleId  = null;
    public ?int    $formClientId = null;

    // Options
    public array $rolesOptions   = [];
    public array $clientsOptions = [];

    public array $headers = [
        ['key' => 'name',        'label' => 'Name',   'class' => 'w-3/12 max-w-0 truncate'],
        ['key' => 'email',       'label' => 'Email',  'class' => 'w-3/12 max-w-0 truncate'],
        ['key' => 'sso',         'label' => 'SSO / NIP', 'class' => 'w-2/12 max-w-0 truncate'],
        ['key' => 'role_names',  'label' => 'Role',   'class' => 'w-1/12'],
        ['key' => 'client_name', 'label' => 'Client', 'class' => 'w-2/12 max-w-0 truncate'],
        ['key' => 'action',      'label' => '',       'class' => 'w-1/12 text-right'],
    ];

    public function mount(): void
    {
        $this->rolesOptions = Role::all(['id', 'name'])
            ->map(fn($r) => ['id' => $r->id, 'name' => $r->name])
            ->toArray();

        $this->clientsOptions = Client::with('university')
            ->get()
            ->map(fn($c) => [
                'id'   => $c->id,
                'name' => $c->university?->name ?? "Client #{$c->id}",
            ])
            ->toArray();
    }

    public function updatedSearch(): void { $this->resetPage(); }

    public function openCreate(): void
    {
        $this->isCreating   = true;
        $this->userId       = null;
        $this->formName     = '';
        $this->formEmail    = '';
        $this->formSso      = '';
        $this->formPassword = '';
        $this->formRoleId   = null;
        $this->formClientId = null;
        $this->userModal    = true;
    }

    public function openEdit(int $id): void
    {
        $user               = User::with('roles')->findOrFail($id);
        $this->isCreating   = false;
        $this->userId       = $id;
        $this->formName     = $user->name;
        $this->formEmail    = $user->email    ?? '';
        $this->formSso      = $user->sso      ?? '';
        $this->formPassword = '';
        $this->formRoleId   = $user->roles->first()?->id;
        $this->formClientId = $user->client_id;
        $this->userModal    = true;
    }

    public function saveUser(): void
    {
        $uniqueEmail  = $this->userId ? "unique:users,email,{$this->userId}" : 'unique:users,email';
        $uniqueSso    = $this->userId ? "unique:users,sso,{$this->userId}"   : 'unique:users,sso';

        $this->validate([
            'formName'     => 'required|string|max:255',
            'formEmail'    => "nullable|email|max:255|{$uniqueEmail}",
            'formSso'      => "nullable|string|max:50|{$uniqueSso}",
            'formPassword' => $this->isCreating ? 'nullable|string|min:8' : 'nullable|string|min:8',
            'formRoleId'   => 'nullable|exists:roles,id',
            'formClientId' => 'nullable|exists:fetnet_client,id',
        ]);

        if ($this->isCreating) {
            $user = User::create([
                'name'      => $this->formName,
                'email'     => $this->formEmail    ?: null,
                'sso'       => $this->formSso      ?: null,
                'password'  => $this->formPassword ? bcrypt($this->formPassword) : null,
                'client_id' => $this->formClientId,
            ]);
        } else {
            $user = User::findOrFail($this->userId);
            $user->name      = $this->formName;
            $user->email     = $this->formEmail    ?: null;
            $user->sso       = $this->formSso      ?: null;
            $user->client_id = $this->formClientId;

            if ($this->formPassword !== '') {
                $user->password = bcrypt($this->formPassword);
            }

            $user->save();
        }

        if ($this->formRoleId) {
            $role = Role::findOrFail($this->formRoleId);
            $user->syncRoles($role->name);
        } else {
            $user->syncRoles([]);
        }

        $this->success(
            $this->isCreating ? 'User created successfully.' : 'User updated successfully.',
            position: 'toast-top toast-center'
        );
        $this->userModal = false;
    }

    public function with(): array
    {
        return [
            'users' => User::with(['roles', 'client.university'])
                ->when($this->search, fn($q) => $q
                    ->where('name', 'ilike', "%{$this->search}%")
                    ->orWhere('email', 'ilike', "%{$this->search}%")
                    ->orWhere('sso', 'ilike', "%{$this->search}%"))
                ->paginate(15)
                ->through(fn($u) => tap($u, fn($item) => [
                    $item->role_names  = $u->roles->pluck('name')->implode(', ') ?: '-',
                    $item->client_name = $u->client?->university?->name ?? '-',
                ])),
        ];
    }
}; ?>

<div>
    <x-header title="Users" subtitle="Manage system users" separator>
        <x-slot:actions>
            <x-input placeholder="Search..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
            <x-button label="New User" icon="o-plus" class="btn-primary btn-sm" wire:click="openCreate" />
        </x-slot:actions>
    </x-header>

    <x-card>
        <x-table :striped="true" :headers="$headers" :rows="$users" with-pagination class="table-fixed" container-class="overflow-hidden">
            @scope('cell_action', $row)
                <div class="flex justify-end">
                    <x-button icon="o-pencil" class="btn-ghost btn-sm btn-square"
                              wire:click="openEdit({{ $row->id }})" tooltip="Edit" />
                </div>
            @endscope
        </x-table>
    </x-card>

    {{-- Create / Edit Modal --}}
    <x-modal wire:model="userModal" separator class="modal-bottom"
             box-class="!max-w-md mx-auto !rounded-t-2xl !mb-14">
        <x-slot:title>
            {{ $isCreating ? 'New User' : 'Edit User' }}
        </x-slot:title>

        <x-form wire:submit="saveUser" class="space-y-4">
            <input type="text" class="w-0 h-0 opacity-0 absolute pointer-events-none" autofocus />

            <x-input label="Name"      wire:model="formName"  required />

            <div class="grid grid-cols-2 gap-3">
                <x-input label="Email"     wire:model="formEmail" type="email" />
                <x-input label="SSO / NIP" wire:model="formSso" />
            </div>

            <x-input label="Password" wire:model="formPassword" type="password"
                     hint="{{ $isCreating ? 'Leave blank for no password (SSO login only)' : 'Leave blank to keep current password' }}" />

            <div class="grid grid-cols-2 gap-3">
                <x-choices label="Role"   single searchable wire:model="formRoleId"   :options="$rolesOptions"   placeholder="No role" />
                <x-choices label="Client" single searchable wire:model="formClientId" :options="$clientsOptions" placeholder="No client (super-admin)" />
            </div>

            <x-slot:actions>
                <x-button label="Cancel" icon="o-x-circle"     wire:click="$set('userModal', false)" />
                <x-button label="Save"   icon="o-check-circle" type="submit" class="btn-primary" spinner="saveUser" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    <x-toast />
</div>
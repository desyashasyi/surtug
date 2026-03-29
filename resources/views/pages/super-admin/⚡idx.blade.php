<?php

use App\Models\FetNet\Client;
use App\Models\FetNet\Faculty;
use App\Models\FetNet\University;
use App\Models\User;
use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.super-admin')] class extends Component
{
    public function with(): array
    {
        return [
            'totalClients'      => Client::count(),
            'totalUniversities' => University::count(),
            'totalFaculties'    => Faculty::count(),
            'totalUsers'        => User::count(),
        ];
    }
}; ?>

<div>
    <x-header title="Super Admin" subtitle="System overview" separator />

    <div class="grid grid-cols-4 gap-4">
        <x-stat title="Total Clients"  value="{{ $totalClients }}"      icon="o-building-office-2" color="text-primary" />
        <x-stat title="Universities"   value="{{ $totalUniversities }}"  icon="o-academic-cap"      color="text-secondary" />
        <x-stat title="Faculties"      value="{{ $totalFaculties }}"     icon="o-building-library"  color="text-accent" />
        <x-stat title="Users"          value="{{ $totalUsers }}"         icon="o-users"             color="text-info" />
    </div>
</div>

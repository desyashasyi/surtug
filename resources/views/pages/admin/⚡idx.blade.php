<?php

use App\Models\FetNet\Client;
use App\Models\FetNet\Program;
use App\Models\FetNet\Semester;
use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.admin')] class extends Component
{
    public function with(): array
    {
        $client = Client::with(['university', 'faculty', 'level', 'config'])
            ->where('user_id', auth()->id())->first();

        return [
            'client'         => $client,
            'totalPrograms'  => $client ? Program::where('client_id', $client->id)->count() : 0,
            'totalSemesters' => $client ? Semester::where('client_id', $client->id)->count() : 0,
        ];
    }
}; ?>

<div>
    <x-header title="Admin Dashboard" separator />

    @if($client)
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3 mb-6">
            <x-stat title="Total Programs"  value="{{ $totalPrograms }}"  icon="o-academic-cap"  color="text-primary" />
            <x-stat title="Total Semesters" value="{{ $totalSemesters }}" icon="o-calendar-days" color="text-secondary" />
            <x-stat title="Level"           value="{{ $client->level?->code ?? '-' }}" icon="o-tag" color="text-accent" />
        </div>

        <x-card title="Client Information" shadow separator>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-input label="University"   value="{{ $client->university?->code }} | {{ $client->university?->name }}" readonly />
                <x-input label="Faculty"      value="{{ $client->faculty?->code }} | {{ $client->faculty?->name }}" readonly />
                <x-input label="Description"  value="{{ $client->description }}" readonly />
                <x-input label="Level"        value="{{ $client->level?->code }} | {{ $client->level?->level }}" readonly />
            </div>
        </x-card>
    @else
        <x-alert icon="o-exclamation-triangle" class="alert-warning">
            This account is not registered as a client. Please contact the Super Admin.
        </x-alert>
    @endif
</div>

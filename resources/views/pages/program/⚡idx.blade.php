<?php

use App\Models\FetNet\Program;
use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.program')] class extends Component
{
    public function with(): array
    {
        return [
            'program' => Program::with(['client.university', 'client.faculty'])
                ->where('user_id', auth()->id())->first(),
        ];
    }
}; ?>

<div>
    <x-header title="Program Dashboard" separator />

    @if($program)
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3 mb-6">
            <x-stat title="University" value="{{ $program->client?->university?->code ?? '-' }}" icon="o-academic-cap"    color="text-primary" />
            <x-stat title="Faculty"    value="{{ $program->client?->faculty?->code ?? '-' }}"    icon="o-building-library" color="text-secondary" />
            <x-stat title="Program"    value="{{ $program->abbrev ?? '-' }}"                      icon="o-tag"             color="text-accent" />
        </div>

        <x-card title="Program Information" shadow separator>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-input label="Program Name" value="{{ $program->name }}"               readonly />
                <x-input label="Code"         value="{{ $program->code }}"               readonly />
                <x-input label="Abbreviation" value="{{ $program->abbrev }}"             readonly />
                <x-input label="Faculty"      value="{{ $program->client?->faculty?->name }}" readonly />
            </div>
        </x-card>
    @else
        <x-alert icon="o-exclamation-triangle" class="alert-warning">
            This account is not registered as a study program. Please contact your administrator.
        </x-alert>
    @endif
</div>

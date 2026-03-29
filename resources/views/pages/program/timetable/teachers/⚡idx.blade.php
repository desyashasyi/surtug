<?php

use App\Livewire\Concerns\HasProgramSemester;
use App\Models\FetNet\Program;
use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.program')] class extends Component
{
    use HasProgramSemester;

    private function program(): ?Program
    {
        return Program::where('user_id', auth()->id())->first();
    }

    public function mount(): void
    {
        $program = $this->program();
        if ($program) $this->mountSemesterContext($program->client_id);
    }
}; ?>

<div>
    <x-header title="Timetable — Teachers" separator>
        <x-slot:actions>
            @if(count($academicYearOptions))
            <x-select wire:model.live="academicYearId" :options="$academicYearOptions"
                      placeholder="Academic Year" class="w-36" />
            @endif
            @if(count($semesterOptions))
            <x-select wire:model.live="semesterId" :options="$semesterOptions"
                      placeholder="Semester" class="w-48" />
            @endif
        </x-slot:actions>
    </x-header>

    <x-card>
        <div class="flex flex-col items-center justify-center py-20 text-base-content/30">
            <x-icon name="o-table-cells" class="w-16 h-16 mb-4" />
            <p class="text-lg font-semibold">Coming Soon</p>
            <p class="text-sm mt-1">Teacher timetable view will be available here.</p>
        </div>
    </x-card>
</div>

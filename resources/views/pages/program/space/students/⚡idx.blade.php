<?php

use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.program')] class extends Component
{
    public function render(): string
    {
        return <<<'BLADE'
        <div>
            <x-header title="Student Space Constraints" subtitle="Set room preferences for students" separator />
            <x-card>
                <p class="text-center text-base-content/40 py-8 text-sm">Coming soon.</p>
            </x-card>
        </div>
        BLADE;
    }
}; ?>

<?php

use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.program')] class extends Component
{
    public function render(): string
    {
        return <<<'BLADE'
        <div>
            <x-header title="Activity Space Constraints" subtitle="Set room constraints for activities" separator />
            <x-card>
                <p class="text-center text-base-content/40 py-8 text-sm">Coming soon.</p>
            </x-card>
        </div>
        BLADE;
    }
}; ?>

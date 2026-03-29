<?php

use App\Models\FetNet\Client;
use App\Models\FetNet\ClientConfig;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;

new #[Layout('layouts.admin')] class extends Component
{
    use Toast;

    public int    $numberOfDays   = 0;
    public int    $numberOfHours  = 0;
    public string $startTime      = '07:00';
    public int    $slotDuration   = 50;
    public bool   $noBreak        = false;
    public string $breakStart     = '12:00';
    public string $breakEnd       = '13:00';

    public function mount(): void
    {
        $client = Client::where('user_id', auth()->id())->first();
        if ($client?->config) {
            $cfg = $client->config;
            $this->numberOfDays  = $cfg->number_of_days;
            $this->numberOfHours = $cfg->number_of_hours;
            $this->startTime     = $cfg->start_time    ?? '07:00';
            $this->slotDuration  = $cfg->slot_duration ?? 50;
            $this->noBreak       = (bool) ($cfg->no_break ?? false);
            $this->breakStart    = $cfg->break_start   ?? '12:00';
            $this->breakEnd      = $cfg->break_end     ?? '13:00';
        }
    }

    private function config(): ?ClientConfig
    {
        $client = Client::where('user_id', auth()->id())->first();
        return $client ? ClientConfig::where('client_id', $client->id)->first() : null;
    }

    private function updateConfig(string $field, mixed $value): void
    {
        $client = Client::where('user_id', auth()->id())->first();
        if ($client) {
            ClientConfig::where('client_id', $client->id)->update([$field => $value]);
        }
    }

    public function daysDecrement(): void
    {
        if ($this->numberOfDays > 1) {
            $this->numberOfDays--;
            $this->updateConfig('number_of_days', $this->numberOfDays);
        }
    }

    public function daysIncrement(): void
    {
        if ($this->numberOfDays < 7) {
            $this->numberOfDays++;
            $this->updateConfig('number_of_days', $this->numberOfDays);
        }
    }

    public function hoursDecrement(): void
    {
        if ($this->numberOfHours > 1) {
            $this->numberOfHours--;
            $this->updateConfig('number_of_hours', $this->numberOfHours);
        }
    }

    public function hoursIncrement(): void
    {
        if ($this->numberOfHours < 16) {
            $this->numberOfHours++;
            $this->updateConfig('number_of_hours', $this->numberOfHours);
        }
    }

    public function slotDurationDecrement(): void
    {
        if ($this->slotDuration > 10) {
            $this->slotDuration -= 5;
            $this->updateConfig('slot_duration', $this->slotDuration);
        }
    }

    public function slotDurationIncrement(): void
    {
        if ($this->slotDuration < 120) {
            $this->slotDuration += 5;
            $this->updateConfig('slot_duration', $this->slotDuration);
        }
    }

    public function saveTimings(): void
    {
        $rules = ['startTime' => 'required|date_format:H:i'];

        if (! $this->noBreak) {
            $rules['breakStart'] = 'required|date_format:H:i|after:startTime';
            $rules['breakEnd']   = 'required|date_format:H:i|after:breakStart';
        }

        $this->validate($rules);

        $client = Client::where('user_id', auth()->id())->first();
        if ($client) {
            ClientConfig::where('client_id', $client->id)->update([
                'start_time'  => $this->startTime,
                'no_break'    => $this->noBreak,
                'break_start' => $this->noBreak ? null : $this->breakStart,
                'break_end'   => $this->noBreak ? null : $this->breakEnd,
            ]);
            $this->success('Schedule timings saved.', position: 'toast-top toast-center');
        }
    }

    public function with(): array
    {
        $client    = Client::with(['university', 'faculty'])->where('user_id', auth()->id())->first();
        $timeSlots = $client?->config ? $client->config->generateSlots()  : [];
        $dayLabels = $client?->config ? $client->config->dayLabels()       : [];

        return compact('client', 'timeSlots', 'dayLabels');
    }
}; ?>

<div>
    <x-header title="Basic Data" subtitle="Basic configuration for the study program" separator />

    @if($client)
        <div class="space-y-6">

            <x-card title="Institution Info" shadow separator>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-input label="University"  value="{{ $client->university?->code }} | {{ $client->university?->name }}" readonly />
                    <x-input label="Faculty"     value="{{ $client->faculty?->code }} | {{ $client->faculty?->name }}" readonly />
                    <x-input label="Description" value="{{ $client->description }}" readonly class="md:col-span-2" />
                </div>
            </x-card>

            <x-card title="Academic Year & Semester" shadow separator>
                <p class="text-sm text-base-content/60 mb-3">Configure academic years and semesters from the dedicated page.</p>
                <x-button label="Manage Academic Years" icon="o-calendar-days" class="btn-primary btn-sm"
                          link="{{ route('admin.data.academic-year') }}" wire:navigate />
            </x-card>

            <x-card title="Schedule Configuration" shadow separator>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    {{-- ── Day column ── --}}
                    <div class="space-y-4">
                        <p class="text-xs font-semibold text-base-content/40 uppercase tracking-widest">Days</p>

                        <x-input label="Days per Week" wire:model="numberOfDays" class="text-center" readonly
                                 hint="Starts from Monday">
                            <x-slot:prepend>
                                <x-button label="-" wire:click="daysDecrement" class="btn-primary rounded-e-none" />
                            </x-slot:prepend>
                            <x-slot:append>
                                <x-button label="+" wire:click="daysIncrement" class="btn-primary rounded-s-none" />
                            </x-slot:append>
                        </x-input>

                        @if(count($dayLabels) > 0)
                            <div>
                                <p class="text-xs text-base-content/40 mb-2">Preview</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($dayLabels as $i => $day)
                                        <span class="badge badge-ghost font-mono text-xs">{{ $i + 1 }}. {{ $day }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- ── Hour column ── --}}
                    <div class="space-y-4">
                        <p class="text-xs font-semibold text-base-content/40 uppercase tracking-widest">Hours / Slots</p>

                        <x-input label="Slots per Day" wire:model="numberOfHours" class="text-center" readonly
                                 hint="Number of teaching slots">
                            <x-slot:prepend>
                                <x-button label="-" wire:click="hoursDecrement" class="btn-primary rounded-e-none" />
                            </x-slot:prepend>
                            <x-slot:append>
                                <x-button label="+" wire:click="hoursIncrement" class="btn-primary rounded-s-none" />
                            </x-slot:append>
                        </x-input>

                        <x-input label="Slot Duration (minutes)" wire:model="slotDuration" class="text-center" readonly>
                            <x-slot:prepend>
                                <x-button label="-" wire:click="slotDurationDecrement" class="btn-primary rounded-e-none" />
                            </x-slot:prepend>
                            <x-slot:append>
                                <x-button label="+" wire:click="slotDurationIncrement" class="btn-primary rounded-s-none" />
                            </x-slot:append>
                        </x-input>

                        <x-form wire:submit="saveTimings" class="space-y-3">
                            <div class="flex items-center gap-3">
                                <x-input label="Start" wire:model="startTime" type="time" required class="w-32" />
                                <x-checkbox label="No break" wire:model.live="noBreak" class="mt-5" />
                            </div>
                            @if(! $noBreak)
                                <div class="grid grid-cols-2 gap-2">
                                    <x-input label="Break Start" wire:model="breakStart" type="time" required />
                                    <x-input label="Break End"   wire:model="breakEnd"   type="time" required />
                                </div>
                            @endif
                            <x-button label="Save" icon="o-check-circle" type="submit"
                                      class="btn-primary btn-sm" spinner="saveTimings" />
                        </x-form>

                        @if(count($timeSlots) > 0)
                            <div>
                                <p class="text-xs text-base-content/40 mb-2">Preview</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($timeSlots as $entry)
                                        @if($entry['break'])
                                            <span class="badge badge-error badge-outline font-mono text-xs">
                                                Break {{ $entry['time'] }}
                                            </span>
                                        @else
                                            <span class="badge badge-ghost font-mono text-xs">
                                                {{ $entry['idx'] }}. {{ $entry['time'] }}
                                            </span>
                                        @endif
                                    @endforeach

                                </div>
                            </div>
                        @endif
                    </div>

                </div>
            </x-card>

        </div>
    @else
        <x-alert icon="o-exclamation-triangle" class="alert-warning">
            This account is not registered as a client. Please contact the Super Admin.
        </x-alert>
    @endif

    <x-toast />
</div>

<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Mary\Traits\Toast;

new #[Layout('layouts.guest')] class extends Component
{
    use Toast;

    public string $email    = '';
    public string $password = '';

    protected array $rules = [
        'email'    => 'required|email',
        'password' => 'required|min:6',
    ];

    public function login(): mixed
    {
        $this->validate();

        if (Auth::attempt(['email' => $this->email, 'password' => $this->password])) {
            $user = Auth::user();

            if ($user->hasRole('super-admin')) {
                return redirect()->route('super-admin.idx');
            }

            if ($user->hasRole('admin')) {
                return redirect()->route('admin.idx');
            }

            return redirect('/');
        }

        $this->error('Invalid email or password.', position: 'toast-top toast-center');
        return null;
    }

    public function redirectToSso(): mixed
    {
        return redirect()->route('auth.cas.redirect');
    }
}; ?>

<div class="flex min-h-screen items-center justify-center bg-base-200">
    <x-card class="w-full max-w-sm shadow-xl" title="FetNet" subtitle="Sign in to continue">

        <x-button
            label="Login via SSO UPI"
            icon="o-academic-cap"
            class="btn-primary w-full mb-4"
            wire:click="redirectToSso"
            spinner
        />

        <div class="divider text-xs text-base-content/50">or sign in with local account</div>

        <x-form wire:submit="login" class="mt-4 space-y-4">
            @error('sso')
                <x-alert icon="o-exclamation-triangle" class="alert-error">{{ $message }}</x-alert>
            @enderror

            <x-input
                label="Email"
                wire:model="email"
                placeholder="name@upi.edu"
                icon="o-envelope"
                type="email"
                required
            />

            <x-password
                label="Password"
                wire:model="password"
                icon="o-key"
                placeholder="••••••••"
                right
                required
            />

            <x-slot:actions>
                <x-button label="Sign In" type="submit" class="btn-ghost" spinner="login" />
            </x-slot:actions>
        </x-form>

    </x-card>
</div>

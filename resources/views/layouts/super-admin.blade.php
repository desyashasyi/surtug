<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' - '.config('app.name') : config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased bg-base-200 pb-20">

{{-- Top bar --}}
<div class="navbar bg-purple-700 text-white sticky top-0 z-30 px-6">
    <div class="flex-1">
        <x-app-brand class="text-white [&_*]:text-white" />
    </div>
    <div class="flex-none gap-2">
        @if($user = auth()->user())
            <span class="text-sm text-white/70">{{ $user->name }}</span>
            <x-button icon="o-power" class="btn-circle btn-ghost btn-sm text-white hover:bg-purple-800" tooltip="Sign Out"
                      no-wire-navigate link="{{ route('logout') }}" />
        @endif
    </div>
</div>

{{-- Content --}}
<div class="container mx-auto px-6 py-5 max-w-7xl">
    {{ $slot }}
</div>

{{-- Bottom nav --}}
<nav class="fixed bottom-0 left-0 right-0 z-50 bg-purple-700 shadow-[0_-2px_10px_rgba(0,0,0,0.2)]">
    <div class="flex justify-center items-center h-16 gap-1">

        <a href="{{ route('super-admin.idx') }}" wire:navigate
           class="flex flex-col items-center justify-center gap-1 w-20 h-full transition-all
                  {{ request()->routeIs('super-admin.idx') ? 'text-white' : 'text-white/50 hover:text-white/80' }}">
            <x-icon name="o-squares-2x2" class="w-6 h-6" />
            <span class="text-[10px] font-medium">Dashboard</span>
        </a>

        <a href="{{ route('super-admin.university') }}" wire:navigate
           class="flex flex-col items-center justify-center gap-1 w-20 h-full transition-all
                  {{ request()->routeIs('super-admin.university') ? 'text-white' : 'text-white/50 hover:text-white/80' }}">
            <x-icon name="o-academic-cap" class="w-6 h-6" />
            <span class="text-[10px] font-medium">Universities</span>
        </a>

        <a href="{{ route('super-admin.faculty') }}" wire:navigate
           class="flex flex-col items-center justify-center gap-1 w-20 h-full transition-all
                  {{ request()->routeIs('super-admin.faculty') ? 'text-white' : 'text-white/50 hover:text-white/80' }}">
            <x-icon name="o-building-library" class="w-6 h-6" />
            <span class="text-[10px] font-medium">Faculties</span>
        </a>

        <a href="{{ route('super-admin.client') }}" wire:navigate
           class="flex flex-col items-center justify-center gap-1 w-20 h-full transition-all
                  {{ request()->routeIs('super-admin.client') ? 'text-white' : 'text-white/50 hover:text-white/80' }}">
            <x-icon name="o-building-office-2" class="w-6 h-6" />
            <span class="text-[10px] font-medium">Clients</span>
        </a>

        <a href="{{ route('super-admin.user') }}" wire:navigate
           class="flex flex-col items-center justify-center gap-1 w-20 h-full transition-all
                  {{ request()->routeIs('super-admin.user') ? 'text-white' : 'text-white/50 hover:text-white/80' }}">
            <x-icon name="o-users" class="w-6 h-6" />
            <span class="text-[10px] font-medium">Users</span>
        </a>

    </div>
</nav>

<x-toast />
</body>
</html>

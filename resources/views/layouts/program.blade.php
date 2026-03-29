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
<nav class="fixed bottom-0 left-0 right-0 z-50 bg-purple-900 shadow-[0_-2px_10px_rgba(0,0,0,0.2)] flex h-16 justify-center items-center gap-0">

    @php
        $item    = 'flex flex-col items-center justify-center gap-1 w-14 h-full transition-all';
        $active  = 'text-white';
        $dim     = 'text-purple-300 hover:text-purple-100';
        $btn     = 'flex flex-col items-center justify-center gap-0.5 px-2 py-1 rounded-lg bg-purple-700 text-purple-200 cursor-default select-none pointer-events-none mx-1';
        $divider = 'w-px h-8 bg-purple-700 self-center mx-0.5';
    @endphp

    {{-- Home --}}
    <a href="{{ route('program.idx') }}" wire:navigate
       class="flex flex-col items-center justify-center gap-0.5 px-2 py-1 rounded-lg mx-1 transition-all
              {{ request()->routeIs('program.idx') ? 'bg-purple-600 text-white' : 'bg-purple-700 text-purple-200 hover:bg-purple-600 hover:text-white' }}">
        <x-icon name="o-home" class="w-4 h-4" />
        <span class="text-[9px] font-semibold">Home</span>
    </a>

    <div class="{{ $divider }}"></div>

    {{-- Data --}}
    <span class="{{ $btn }}">
        <x-icon name="o-circle-stack" class="w-4 h-4" />
        <span class="text-[9px] font-semibold">Data</span>
    </span>
    <a href="{{ route('program.data.subjects') }}" wire:navigate
       class="{{ $item }} {{ request()->routeIs('program.data.subjects') ? $active : $dim }}">
        <x-icon name="o-book-open" class="w-5 h-5" />
        <span class="text-[9px] font-medium">Subjects</span>
    </a>
    <a href="{{ route('program.data.teachers') }}" wire:navigate
       class="{{ $item }} {{ request()->routeIs('program.data.teachers') ? $active : $dim }}">
        <x-icon name="o-academic-cap" class="w-5 h-5" />
        <span class="text-[9px] font-medium">Teachers</span>
    </a>
    <a href="{{ route('program.data.students') }}" wire:navigate
       class="{{ $item }} {{ request()->routeIs('program.data.students') ? $active : $dim }}">
        <x-icon name="o-user-group" class="w-5 h-5" />
        <span class="text-[9px] font-medium">Students</span>
    </a>
    <a href="{{ route('program.data.activities') }}" wire:navigate
       class="{{ $item }} {{ request()->routeIs('program.data.activities') ? $active : $dim }}">
        <x-icon name="o-calendar-days" class="w-5 h-5" />
        <span class="text-[9px] font-medium">Activities</span>
    </a>

    <div class="{{ $divider }}"></div>

    {{-- Time --}}
    <span class="{{ $btn }}">
        <x-icon name="o-clock" class="w-4 h-4" />
        <span class="text-[9px] font-semibold">Time</span>
    </span>
    <a href="{{ route('program.time.teachers') }}" wire:navigate
       class="{{ $item }} {{ request()->routeIs('program.time.teachers') ? $active : $dim }}">
        <x-icon name="o-academic-cap" class="w-5 h-5" />
        <span class="text-[9px] font-medium">Teachers</span>
    </a>
    <a href="{{ route('program.time.students') }}" wire:navigate
       class="{{ $item }} {{ request()->routeIs('program.time.students') ? $active : $dim }}">
        <x-icon name="o-users" class="w-5 h-5" />
        <span class="text-[9px] font-medium">Students</span>
    </a>
    <a href="{{ route('program.time.activities') }}" wire:navigate
       class="{{ $item }} {{ request()->routeIs('program.time.activities') ? $active : $dim }}">
        <x-icon name="o-calendar-days" class="w-5 h-5" />
        <span class="text-[9px] font-medium">Activities</span>
    </a>

    <div class="{{ $divider }}"></div>

    {{-- Space --}}
    <span class="{{ $btn }}">
        <x-icon name="o-building-office" class="w-4 h-4" />
        <span class="text-[9px] font-semibold">Space</span>
    </span>
    <a href="{{ route('program.space.teachers') }}" wire:navigate
       class="{{ $item }} {{ request()->routeIs('program.space.teachers') ? $active : $dim }}">
        <x-icon name="o-academic-cap" class="w-5 h-5" />
        <span class="text-[9px] font-medium">Teachers</span>
    </a>
    <a href="{{ route('program.space.students') }}" wire:navigate
       class="{{ $item }} {{ request()->routeIs('program.space.students') ? $active : $dim }}">
        <x-icon name="o-users" class="w-5 h-5" />
        <span class="text-[9px] font-medium">Students</span>
    </a>
    <a href="{{ route('program.space.activities') }}" wire:navigate
       class="{{ $item }} {{ request()->routeIs('program.space.activities') ? $active : $dim }}">
        <x-icon name="o-calendar-days" class="w-5 h-5" />
        <span class="text-[9px] font-medium">Activities</span>
    </a>

    <div class="{{ $divider }}"></div>

    {{-- Timetable --}}
    <span class="{{ $btn }}">
        <x-icon name="o-table-cells" class="w-4 h-4" />
        <span class="text-[9px] font-semibold">Timetable</span>
    </span>
    <a href="{{ route('program.timetable.teachers') }}" wire:navigate
       class="{{ $item }} {{ request()->routeIs('program.timetable.teachers') ? $active : $dim }}">
        <x-icon name="o-academic-cap" class="w-5 h-5" />
        <span class="text-[9px] font-medium">Teachers</span>
    </a>
    <a href="{{ route('program.timetable.students') }}" wire:navigate
       class="{{ $item }} {{ request()->routeIs('program.timetable.students') ? $active : $dim }}">
        <x-icon name="o-user-group" class="w-5 h-5" />
        <span class="text-[9px] font-medium">Students</span>
    </a>

</nav>

<x-toast />
</body>
</html>

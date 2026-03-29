<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' - '.config('app.name') : config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased bg-base-200">

{{-- NAVBAR mobile --}}
<x-nav sticky class="lg:hidden">
    <x-slot:brand>
        <x-app-brand />
    </x-slot:brand>
    <x-slot:actions>
        <label for="main-drawer" class="lg:hidden me-3">
            <x-icon name="o-bars-3" class="cursor-pointer" />
        </label>
    </x-slot:actions>
</x-nav>

<x-main>
    <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-100 lg:bg-inherit">

        <x-app-brand class="px-5 pt-4" />

        <x-menu activate-by-route>

            @if($user = auth()->user())
                <x-menu-separator />
                <x-list-item :item="$user" value="name" sub-value="email" no-separator no-hover class="-mx-2 !-my-2 rounded">
                    <x-slot:actions>
                        <x-button icon="o-power" class="btn-circle btn-ghost btn-xs" tooltip-left="Sign Out"
                                  no-wire-navigate link="{{ route('logout') }}" />
                    </x-slot:actions>
                </x-list-item>
                <x-menu-separator />

                {{-- Super Admin --}}
                @role('super-admin')
                    <x-menu-sub title="Super Admin" icon="o-cog-8-tooth">
                        <x-menu-item title="Dashboard"    icon="o-squares-2x2"       link="{{ route('super-admin.idx') }}" />
                        <x-menu-item title="Universities" icon="o-academic-cap"       link="{{ route('super-admin.university') }}" />
                        <x-menu-item title="Faculties"    icon="o-building-library"   link="{{ route('super-admin.faculty') }}" />
                        <x-menu-item title="Clients"      icon="o-building-office-2"  link="{{ route('super-admin.client') }}" />
                        <x-menu-item title="Users"        icon="o-users"              link="{{ route('super-admin.user') }}" />
                    </x-menu-sub>
                @endrole

                {{-- Admin --}}
                @role('admin')
                    <x-menu-sub title="Admin" icon="o-wrench-screwdriver">
                        <x-menu-item title="Dashboard"      icon="o-squares-2x2"  link="{{ route('admin.idx') }}" />
                        <x-menu-item title="Study Programs" icon="o-academic-cap" link="{{ route('admin.program') }}" />
                        <x-menu-item title="Basic Data"     icon="o-cog-6-tooth"  link="{{ route('admin.data.basic') }}" />
                    </x-menu-sub>
                @endrole

            @endif

        </x-menu>
    </x-slot:sidebar>

    <x-slot:content>
        {{ $slot }}
    </x-slot:content>
</x-main>

<x-toast />
</body>
</html>

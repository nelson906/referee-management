<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Area Arbitro') - {{ config('app.name', 'Laravel') }}</title>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-100">
    <div class="min-h-screen">
        <!-- Navigation -->
        <nav class="bg-white shadow-lg">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <!-- Logo -->
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <h1 class="text-xl font-bold text-gray-900">Area Arbitro</h1>
                        </div>
                    </div>

<!-- Desktop Navigation Links -->
<div class="hidden sm:ml-6 sm:flex sm:space-x-8">
    <a href="{{ route('referee.dashboard') }}"
       class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition duration-150 ease-in-out
       {{ request()->routeIs('referee.dashboard') ? 'border-white text-white' : 'border-transparent text-blue-100 hover:text-white hover:border-blue-300' }}">
        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2V7"></path>
        </svg>
        Dashboard
    </a>

    <a href="{{ route('referee.availability.index') }}"
       class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition duration-150 ease-in-out
       {{ request()->routeIs('referee.availability.index') ? 'border-white text-white' : 'border-transparent text-blue-100 hover:text-white hover:border-blue-300' }}">
        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
        </svg>
        Disponibilità
    </a>

    {{-- CALENDAR LINK - STANDARDIZED --}}
    <a href="{{ route('referee.availability.calendar') }}"
       class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition duration-150 ease-in-out
       {{ request()->routeIs('referee.availability.calendar') ? 'border-white text-white' : 'border-transparent text-blue-100 hover:text-white hover:border-blue-300' }}">
        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2-2v16a2 2 0 002 2z"></path>
        </svg>
        Il Mio Calendario
    </a>

    <a href="{{ route('referee.assignments.index') }}"
       class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition duration-150 ease-in-out
       {{ request()->routeIs('referee.assignments.*') ? 'border-white text-white' : 'border-transparent text-blue-100 hover:text-white hover:border-blue-300' }}">
        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
        </svg>
        Le Mie Assegnazioni
    </a>

    <a href="{{ route('referee.profile.edit') }}"
       class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition duration-150 ease-in-out
       {{ request()->routeIs('referee.profile.*') ? 'border-white text-white' : 'border-transparent text-blue-100 hover:text-white hover:border-blue-300' }}">
        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
        </svg>
        Profilo
    </a>

    {{-- PUBLIC LINK (can access public views) --}}
    <a href="{{ route('tournaments.calendar') }}"
       class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition duration-150 ease-in-out
       {{ request()->routeIs('tournaments.calendar') ? 'border-white text-white' : 'border-transparent text-blue-100 hover:text-white hover:border-blue-300' }}">
        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9v-9m0-9v9"></path>
        </svg>
        Tornei Pubblici
    </a>
</div>
                    <!-- User dropdown -->
                    <div class="flex items-center">
                        <div class="ml-3 relative" x-data="{ open: false }">
                            <button @click="open = !open" class="bg-white rounded-full flex text-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <span class="sr-only">Apri menu utente</span>
                                <div class="h-8 w-8 rounded-full bg-gray-300 flex items-center justify-center">
                                    <span class="text-sm font-medium text-gray-700">{{ substr(auth()->user()->name, 0, 1) }}</span>
                                </div>
                            </button>

                            <div x-show="open" @click.away="open = false" class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5" style="display: none;">
                                <div class="py-1">
                                    <div class="px-4 py-2 text-sm text-gray-700 border-b">
                                        <div class="font-medium">{{ auth()->user()->name }}</div>
                                        <div class="text-gray-500">{{ auth()->user()->email }}</div>
                                    </div>

                                    @if(auth()->user()->user_type !== 'referee')
                                        <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            Area Admin
                                        </a>
                                    @endif

                                    @if(auth()->user()->user_type === 'super_admin')
                                        <a href="{{ route('super-admin.dashboard') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            Super Admin
                                        </a>
                                    @endif

                                    <div class="border-t border-gray-100"></div>

                                    <a href="{{ route('referee.profile.edit') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        Impostazioni Profilo
                                    </a>

                                    <form method="POST" action="{{ route('logout') }}" class="block">
                                        @csrf
                                        <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            Logout
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

{{-- Mobile Navigation --}}
<div :class="{'block': open, 'hidden': !open}" class="hidden sm:hidden bg-blue-700">
    <div class="pt-2 pb-3 space-y-1">
        <a href="{{ route('referee.dashboard') }}"
           class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium transition duration-150 ease-in-out
           {{ request()->routeIs('referee.dashboard') ? 'border-white text-white bg-blue-800' : 'border-transparent text-blue-100 hover:text-white hover:bg-blue-800 hover:border-blue-300' }}">
            Dashboard
        </a>

        <a href="{{ route('referee.availability.index') }}"
           class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium transition duration-150 ease-in-out
           {{ request()->routeIs('referee.availability.index') ? 'border-white text-white bg-blue-800' : 'border-transparent text-blue-100 hover:text-white hover:bg-blue-800 hover:border-blue-300' }}">
            Disponibilità
        </a>

        {{-- MOBILE CALENDAR LINK --}}
        <a href="{{ route('referee.availability.calendar') }}"
           class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium transition duration-150 ease-in-out
           {{ request()->routeIs('referee.availability.calendar') ? 'border-white text-white bg-blue-800' : 'border-transparent text-blue-100 hover:text-white hover:bg-blue-800 hover:border-blue-300' }}">
            📅 Il Mio Calendario
        </a>

        <a href="{{ route('referee.assignments.index') }}"
           class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium transition duration-150 ease-in-out
           {{ request()->routeIs('referee.assignments.*') ? 'border-white text-white bg-blue-800' : 'border-transparent text-blue-100 hover:text-white hover:bg-blue-800 hover:border-blue-300' }}">
            Le Mie Assegnazioni
        </a>

        <a href="{{ route('referee.profile.edit') }}"
           class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium transition duration-150 ease-in-out
           {{ request()->routeIs('referee.profile.*') ? 'border-white text-white bg-blue-800' : 'border-transparent text-blue-100 hover:text-white hover:bg-blue-800 hover:border-blue-300' }}">
            Profilo
        </a>

        <a href="{{ route('tournaments.calendar') }}"
           class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium transition duration-150 ease-in-out
           {{ request()->routeIs('tournaments.calendar') ? 'border-white text-white bg-blue-800' : 'border-transparent text-blue-100 hover:text-white hover:bg-blue-800 hover:border-blue-300' }}">
            🌐 Tornei Pubblici
        </a>
    </div>
</div>
<!-- Page Content -->
        <main>
            @if (session('success'))
                <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                        {{ session('success') }}
                    </div>
                </div>
            @endif

            @if (session('error'))
                <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                        {{ session('error') }}
                    </div>
                </div>
            @endif

            @if (session('info'))
                <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
                    <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded">
                        {{ session('info') }}
                    </div>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>

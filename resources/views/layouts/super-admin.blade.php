<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Super Admin') - {{ config('app.name', 'Golf Referee System') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Additional CSS -->
    @stack('styles')
</head>
<body class="font-sans antialiased bg-gray-100" x-data="{ sidebarOpen: true }">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <div class="bg-indigo-800 text-white min-h-screen transition-all duration-300"
             :class="sidebarOpen ? 'w-64' : 'w-20'">

            <!-- Logo -->
            <div class="p-4 border-b border-indigo-700">
                <div class="flex items-center justify-between">
                    <h1 class="text-xl font-bold flex items-center" x-show="sidebarOpen">
                        <span class="mr-2">⚙️</span> Super Admin
                    </h1>
                    <span class="text-2xl" x-show="!sidebarOpen">⚙️</span>
                    <button @click="sidebarOpen = !sidebarOpen"
                            class="text-white hover:text-indigo-200 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  :d="sidebarOpen ? 'M6 18L18 6M6 6l12 12' : 'M4 6h16M4 12h16M4 18h16'"></path>
                        </svg>
                    </button>
                </div>
            </div>

<!-- Navigation Section per super-admin.blade.php -->
<!-- Sostituire la sezione <nav class="p-4"> esistente con questa: -->

<nav class="p-4">
    <div class="space-y-2">

        {{-- GESTIONE ISTITUZIONALE --}}
        <div class="mb-6">
            <h3 class="text-xs uppercase text-indigo-300 font-semibold px-3 mb-2"
                x-show="sidebarOpen">Gestione Istituzionale</h3>

            {{-- Email Istituzionali - NUOVO --}}
            <a href="{{ route('super-admin.institutional-emails.index') }}"
               class="flex items-center space-x-3 p-3 rounded-lg transition duration-200 group
               {{ request()->routeIs('super-admin.institutional-emails.*') ? 'bg-indigo-700 text-white' : 'text-indigo-200 hover:bg-indigo-700 hover:text-white' }}"
               title="Email Istituzionali">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
                <span x-show="sidebarOpen">Email Istituzionali</span>
                <span x-show="!sidebarOpen" class="absolute left-full ml-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">Email Istituzionali</span>
            </a>

            {{-- Categorie Tornei --}}
            <a href="{{ route('super-admin.tournament-types.index') }}"
               class="flex items-center space-x-3 p-3 rounded-lg transition duration-200 group
               {{ request()->routeIs('super-admin.tournament-types.*') ? 'bg-indigo-700 text-white' : 'text-indigo-200 hover:bg-indigo-700 hover:text-white' }}"
               title="Categorie Tornei">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                </svg>
                <span x-show="sidebarOpen">Categorie Tornei</span>
                <span x-show="!sidebarOpen" class="absolute left-full ml-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">Categorie Tornei</span>
            </a>
        </div>

        {{-- GESTIONE UTENTI E ZONE --}}
        <div class="mb-6">
            <h3 class="text-xs uppercase text-indigo-300 font-semibold px-3 mb-2"
                x-show="sidebarOpen">Gestione Sistema</h3>

            {{-- Gestione Utenti --}}
            <a href="{{ route('super-admin.users.index') }}"
               class="flex items-center space-x-3 p-3 rounded-lg transition duration-200 group
               {{ request()->routeIs('super-admin.users.*') ? 'bg-indigo-700 text-white' : 'text-indigo-200 hover:bg-indigo-700 hover:text-white' }}"
               title="Gestione Utenti">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                </svg>
                <span x-show="sidebarOpen">Gestione Utenti</span>
                <span x-show="!sidebarOpen" class="absolute left-full ml-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">Gestione Utenti</span>
            </a>

            {{-- Gestione Zone --}}
            <a href="{{ route('super-admin.zones.index') }}"
               class="flex items-center space-x-3 p-3 rounded-lg transition duration-200 group
               {{ request()->routeIs('super-admin.zones.*') ? 'bg-indigo-700 text-white' : 'text-indigo-200 hover:bg-indigo-700 hover:text-white' }}"
               title="Gestione Zone">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                <span x-show="sidebarOpen">Gestione Zone</span>
                <span x-show="!sidebarOpen" class="absolute left-full ml-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">Gestione Zone</span>
            </a>
        </div>

        {{-- SUPERVISIONE (SOLO VISUALIZZAZIONE) --}}
        <div class="mb-6">
            <h3 class="text-xs uppercase text-indigo-300 font-semibold px-3 mb-2"
                x-show="sidebarOpen">Supervisione</h3>

            {{-- Tutti gli Arbitri - Solo visualizzazione --}}
            {{-- TEMPORANEAMENTE COMMENTATO - Route da creare --}}
            <div class="flex items-center space-x-3 p-3 rounded-lg text-indigo-300 opacity-50"
                 title="Tutti gli Arbitri (In sviluppo)">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
                <span x-show="sidebarOpen">Tutti gli Arbitri</span>
                <div x-show="sidebarOpen" class="ml-auto">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-600 text-gray-200">
                        In sviluppo
                    </span>
                </div>
                <span x-show="!sidebarOpen" class="absolute left-full ml-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">Tutti gli Arbitri (In sviluppo)</span>
            </div>

            {{-- Tutti i Tornei - Solo visualizzazione --}}
            {{-- TEMPORANEAMENTE COMMENTATO - Route da creare --}}
            <div class="flex items-center space-x-3 p-3 rounded-lg text-indigo-300 opacity-50"
                 title="Tutti i Tornei (In sviluppo)">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <span x-show="sidebarOpen">Tutti i Tornei</span>
                <div x-show="sidebarOpen" class="ml-auto">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-600 text-gray-200">
                        In sviluppo
                    </span>
                </div>
                <span x-show="!sidebarOpen" class="absolute left-full ml-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">Tutti i Tornei (In sviluppo)</span>
            </div>

            {{-- Tutti i Clubs - Solo visualizzazione --}}
            {{-- TEMPORANEAMENTE COMMENTATO - Route da creare --}}
            <div class="flex items-center space-x-3 p-3 rounded-lg text-indigo-300 opacity-50"
                 title="Tutti i Clubs (In sviluppo)">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
                <span x-show="sidebarOpen">Tutti i Clubs</span>
                <div x-show="sidebarOpen" class="ml-auto">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-600 text-gray-200">
                        In sviluppo
                    </span>
                </div>
                <span x-show="!sidebarOpen" class="absolute left-full ml-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">Tutti i Clubs (In sviluppo)</span>
            </div>

            {{-- Tutte le Assegnazioni - Solo visualizzazione --}}
            {{-- TEMPORANEAMENTE COMMENTATO - Route da creare --}}
            <div class="flex items-center space-x-3 p-3 rounded-lg text-indigo-300 opacity-50"
                 title="Tutte le Assegnazioni (In sviluppo)">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                </svg>
                <span x-show="sidebarOpen">Tutte le Assegnazioni</span>
                <div x-show="sidebarOpen" class="ml-auto">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-600 text-gray-200">
                        In sviluppo
                    </span>
                </div>
                <span x-show="!sidebarOpen" class="absolute left-full ml-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">Tutte le Assegnazioni (In sviluppo)</span>
            </div>
        </div>

        {{-- REPORT E ANALYTICS --}}
        <div class="mb-6">
            <h3 class="text-xs uppercase text-indigo-300 font-semibold px-3 mb-2"
                x-show="sidebarOpen">Report e Analytics</h3>

            {{-- Dashboard Analytics - IN SVILUPPO --}}
            <div class="flex items-center space-x-3 p-3 rounded-lg text-indigo-300 opacity-50"
                 title="Dashboard Analytics (In sviluppo)">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                <span x-show="sidebarOpen">Dashboard Analytics</span>
                <div x-show="sidebarOpen" class="ml-auto">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-600 text-gray-200">
                        In sviluppo
                    </span>
                </div>
                <span x-show="!sidebarOpen" class="absolute left-full ml-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">Dashboard Analytics (In sviluppo)</span>
            </div>

            {{-- Report per Zone - IN SVILUPPO --}}
            <div class="flex items-center space-x-3 p-3 rounded-lg text-indigo-300 opacity-50"
                 title="Report per Zone (In sviluppo)">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span x-show="sidebarOpen">Report per Zone</span>
                <div x-show="sidebarOpen" class="ml-auto">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-600 text-gray-200">
                        In sviluppo
                    </span>
                </div>
                <span x-show="!sidebarOpen" class="absolute left-full ml-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">Report per Zone (In sviluppo)</span>
            </div>

            {{-- Report per Categorie - IN SVILUPPO --}}
            <div class="flex items-center space-x-3 p-3 rounded-lg text-indigo-300 opacity-50"
                 title="Report per Categorie (In sviluppo)">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <span x-show="sidebarOpen">Report per Categorie</span>
                <div x-show="sidebarOpen" class="ml-auto">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-600 text-gray-200">
                        In sviluppo
                    </span>
                </div>
                <span x-show="!sidebarOpen" class="absolute left-full ml-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">Report per Categorie (In sviluppo)</span>
            </div>
        </div>

        {{-- SEPARATOR --}}
        <div class="border-t border-indigo-700 pt-4 mt-6">
            {{-- Link Rapidi --}}
            <h3 class="text-xs uppercase text-indigo-300 font-semibold px-3 mb-2"
                x-show="sidebarOpen">Accesso Rapido</h3>

            {{-- Calendario Tornei Pubblico --}}
            <a href="{{ route('tournaments.calendar') }}"
               class="flex items-center space-x-3 p-3 rounded-lg transition duration-200 group text-indigo-200 hover:bg-indigo-700 hover:text-white"
               title="Calendario">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                </svg>
                <span x-show="sidebarOpen">Calendario</span>
                <span x-show="!sidebarOpen" class="absolute left-full ml-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">Calendario</span>
            </a>
        </div>
    </div>
</nav>

</div>

        <!-- Main Content -->
        <div class="flex-1">
            <!-- Top Bar -->
            <header class="bg-white shadow-sm border-b">
                <div class="flex justify-between items-center px-6 py-4">
                    <div class="flex items-center space-x-4">
                        <h2 class="text-xl font-semibold text-gray-800">
                            @yield('header', 'Super Admin Dashboard')
                        </h2>

                        {{-- Breadcrumb --}}
                        @if(isset($breadcrumbs) && count($breadcrumbs) > 0)
                        <nav class="flex" aria-label="Breadcrumb">
                            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                                @foreach($breadcrumbs as $crumb)
                                <li class="inline-flex items-center">
                                    @if(!$loop->last)
                                        <a href="{{ $crumb['url'] }}" class="text-gray-500 hover:text-gray-700">
                                            {{ $crumb['label'] }}
                                        </a>
                                        <svg class="w-6 h-6 text-gray-400 mx-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                    @else
                                        <span class="text-gray-400">{{ $crumb['label'] }}</span>
                                    @endif
                                </li>
                                @endforeach
                            </ol>
                        </nav>
                        @endif
                    </div>

                    <!-- User Menu -->
                    <div class="flex items-center space-x-4">
                        {{-- System Status --}}
                        <div class="flex items-center space-x-2 text-sm text-gray-600">
                            <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                            <span>Sistema Attivo</span>
                        </div>

                        {{-- User Dropdown --}}
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open"
                                    class="flex items-center text-sm font-medium text-gray-500 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <div class="w-8 h-8 bg-indigo-600 rounded-full flex items-center justify-center mr-2">
                                    <span class="text-white font-medium text-sm">
                                        {{ substr(auth()->user()->name, 0, 1) }}
                                    </span>
                                </div>
                                <span>{{ auth()->user()->name }}</span>
                                <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>

                            <div x-show="open" @click.away="open = false" x-transition
                                 class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50">

                                <div class="px-4 py-2 border-b border-gray-100">
                                    <p class="text-sm font-medium text-gray-900">{{ auth()->user()->name }}</p>
                                    <p class="text-xs text-gray-500">Super Administrator</p>
                                </div>

                                <a href="{{ route('admin.dashboard') }}"
                                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path>
                                    </svg>
                                    Admin Dashboard
                                </a>

                                <a href="{{ route('referee.dashboard') }}"
                                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                    Area Arbitro
                                </a>

                                <div class="border-t border-gray-100"></div>

                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit"
                                            class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                        </svg>
                                        Logout
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Flash Messages -->
            @if (session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mx-6 mt-4" role="alert">
                    <div class="flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="block sm:inline">{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if (session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mx-6 mt-4" role="alert">
                    <div class="flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="block sm:inline">{{ session('error') }}</span>
                    </div>
                </div>
            @endif

            @if (session('warning'))
                <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mx-6 mt-4" role="alert">
                    <div class="flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="block sm:inline">{{ session('warning') }}</span>
                    </div>
                </div>
            @endif

            <!-- Page Content -->
            <main class="p-6">
                @yield('content')
            </main>
        </div>
    </div>

    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Additional Scripts -->
    @stack('scripts')
</body>
</html>

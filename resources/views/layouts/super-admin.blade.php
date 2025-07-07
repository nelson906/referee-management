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

            <!-- Navigation -->
            <nav class="p-4">
                <div class="space-y-2">
                    {{-- Sistema --}}
                    <div class="mb-6">
                        <h3 class="text-xs uppercase text-indigo-300 font-semibold px-3 mb-2"
                            x-show="sidebarOpen">Sistema</h3>

                        <a href="{{ route('super-admin.tournament-categories.index') }}"
                           class="flex items-center space-x-3 p-3 rounded-lg transition duration-200 group
                           {{ request()->routeIs('super-admin.tournament-categories.*') ? 'bg-indigo-900' : 'hover:bg-indigo-700' }}"
                           title="Categorie Tornei">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                            </svg>
                            <span x-show="sidebarOpen">Categorie Tornei</span>
                        </a>

                        <a href="{{ route('super-admin.settings.index') }}"
                           class="flex items-center space-x-3 p-3 rounded-lg transition duration-200 group
                           {{ request()->routeIs('super-admin.settings.*') ? 'bg-indigo-900' : 'hover:bg-indigo-700' }}"
                           title="Impostazioni Sistema">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <span x-show="sidebarOpen">Impostazioni Sistema</span>
                        </a>
                    </div>

                    {{-- Gestione Globale --}}
                    <div class="pt-4 mt-4 border-t border-indigo-700">
                        <h3 class="text-xs uppercase text-indigo-300 font-semibold px-3 mb-2"
                            x-show="sidebarOpen">Gestione Globale</h3>

                        <a href="{{ route('admin.referees.index') }}"
                           class="flex items-center space-x-3 p-3 rounded-lg transition duration-200 group
                           {{ request()->routeIs('admin.referees.*') ? 'bg-indigo-900' : 'hover:bg-indigo-700' }}"
                           title="Tutti gli Arbitri">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            <span x-show="sidebarOpen">Tutti gli Arbitri</span>
                            <span x-show="!sidebarOpen" class="absolute left-full ml-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">Tutti gli Arbitri</span>
                        </a>

                        <a href="{{ route('admin.tournaments.admin-index') }}"
                           class="flex items-center space-x-3 p-3 rounded-lg transition duration-200 group
                           {{ request()->routeIs('admin.tournaments.*') ? 'bg-indigo-900' : 'hover:bg-indigo-700' }}"
                           title="Tutti i Tornei">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <span x-show="sidebarOpen">Tutti i Tornei</span>
                            <span x-show="!sidebarOpen" class="absolute left-full ml-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">Tutti i Tornei</span>
                        </a>

                        <a href="{{ route('admin.clubs.index') }}"
                           class="flex items-center space-x-3 p-3 rounded-lg transition duration-200 group
                           {{ request()->routeIs('admin.clubs.*') ? 'bg-indigo-900' : 'hover:bg-indigo-700' }}"
                           title="Tutti i Clubs">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                            <span x-show="sidebarOpen">Tutti i Clubs</span>
                            <span x-show="!sidebarOpen" class="absolute left-full ml-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">Tutti i Clubs</span>
                        </a>

                        <a href="{{ route('admin.assignments.index') }}"
                           class="flex items-center space-x-3 p-3 rounded-lg transition duration-200 group
                           {{ request()->routeIs('admin.assignments.*') ? 'bg-indigo-900' : 'hover:bg-indigo-700' }}"
                           title="Tutte le Assegnazioni">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                            </svg>
                            <span x-show="sidebarOpen">Tutte le Assegnazioni</span>
                            <span x-show="!sidebarOpen" class="absolute left-full ml-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">Tutte le Assegnazioni</span>
                        </a>
                    </div>

                    {{-- Report e Analytics --}}
                    <div class="pt-4 mt-4 border-t border-indigo-700">
                        <h3 class="text-xs uppercase text-indigo-300 font-semibold px-3 mb-2"
                            x-show="sidebarOpen">Report e Analytics</h3>

                        <a href="{{ route('reports.dashboard') }}"
                           class="flex items-center space-x-3 p-3 rounded-lg transition duration-200 group
                           {{ request()->routeIs('reports.dashboard') ? 'bg-indigo-900' : 'hover:bg-indigo-700' }}"
                           title="Dashboard Analytics">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                            <span x-show="sidebarOpen">Dashboard Analytics</span>
                            <span x-show="!sidebarOpen" class="absolute left-full ml-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">Dashboard Analytics</span>
                        </a>

                        <div class="relative" x-data="{ reportOpen: false }">
                            <button @click="reportOpen = !reportOpen"
                                    class="w-full flex items-center justify-between space-x-3 p-3 rounded-lg transition duration-200 group hover:bg-indigo-700"
                                    title="Report Globali">
                                <div class="flex items-center space-x-3">
                                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <span x-show="sidebarOpen">Report Globali</span>
                                </div>
                                <svg x-show="sidebarOpen" class="w-4 h-4 transition-transform"
                                     :class="reportOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>

                            <div x-show="reportOpen && sidebarOpen" x-transition class="ml-8 mt-2 space-y-1">
                                <a href="{{ route('reports.category.index') }}"
                                   class="block px-3 py-2 text-sm text-indigo-200 hover:text-white hover:bg-indigo-700 rounded">
                                    Report per Categoria
                                </a>
                                <a href="{{ route('reports.zone.index') }}"
                                   class="block px-3 py-2 text-sm text-indigo-200 hover:text-white hover:bg-indigo-700 rounded">
                                    Report per Zona
                                </a>
                                <a href="{{ route('reports.referee.index') }}"
                                   class="block px-3 py-2 text-sm text-indigo-200 hover:text-white hover:bg-indigo-700 rounded">
                                    Report Arbitri
                                </a>
                                <a href="{{ route('reports.tournament.index') }}"
                                   class="block px-3 py-2 text-sm text-indigo-200 hover:text-white hover:bg-indigo-700 rounded">
                                    Report Tornei
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- Accesso Rapido --}}
                    <div class="pt-4 mt-4 border-t border-indigo-700">
                        <h3 class="text-xs uppercase text-indigo-300 font-semibold px-3 mb-2"
                            x-show="sidebarOpen">Accesso Rapido</h3>

                        <a href="{{ route('admin.dashboard') }}"
                           class="flex items-center space-x-3 p-3 rounded-lg transition duration-200 group hover:bg-indigo-700"
                           title="Area Admin">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path>
                            </svg>
                            <span x-show="sidebarOpen">Area Admin</span>
                            <span x-show="!sidebarOpen" class="absolute left-full ml-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">Area Admin</span>
                        </a>

                        <a href="{{ route('referee.dashboard') }}"
                           class="flex items-center space-x-3 p-3 rounded-lg transition duration-200 group hover:bg-indigo-700"
                           title="Area Arbitro">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            <span x-show="sidebarOpen">Area Arbitro</span>
                            <span x-show="!sidebarOpen" class="absolute left-full ml-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">Area Arbitro</span>
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

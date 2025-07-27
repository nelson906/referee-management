<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Golf Referee Management') }} - Admin</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Alpine.js for interactive components -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="font-sans antialiased bg-gray-100">
    <div x-data="{ open: false }" class="min-h-screen">

        {{-- ============================================
             🏗️ ADMIN NAVIGATION BAR
             ============================================ --}}
        <nav class="bg-blue-900 border-b border-blue-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">

                    {{-- Logo & Brand --}}
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <h1 class="text-white text-xl font-bold">
                                🏌️ Golf Admin
                            </h1>
                        </div>

                        {{-- Desktop Navigation Menu --}}
                        <div class="hidden md:ml-6 md:flex md:space-x-4">

                            {{-- Dashboard --}}
                            <a href="{{ route('admin.dashboard') }}"
                                class="px-3 py-2 rounded-md text-sm font-medium transition duration-150 ease-in-out
                                {{ request()->routeIs('admin.dashboard') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-800 hover:text-white' }}">
                                🏠 Dashboard
                            </a>

                            {{-- Tournaments --}}
                            <a href="{{ route('admin.tournaments.index') }}"
                                class="px-3 py-2 rounded-md text-sm font-medium transition duration-150 ease-in-out
                                {{ request()->routeIs('admin.tournaments.*') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-800 hover:text-white' }}">
                                📋 Tornei
                            </a>

                            {{-- Calendar --}}
                            <a href="{{ route('tournaments.calendar') }}"
                                class="px-3 py-2 rounded-md text-sm font-medium transition duration-150 ease-in-out
                                {{ request()->routeIs('tournaments.calendar', 'admin.assignments.calendar') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-800 hover:text-white' }}">
                                📅 Calendario
                            </a>

                            {{-- Referees --}}
                            <a href="{{ route('admin.referees.index') }}"
                                class="px-3 py-2 rounded-md text-sm font-medium transition duration-150 ease-in-out
                                {{ request()->routeIs('admin.referees.*') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-800 hover:text-white' }}">
                                👨‍💼 Arbitri
                            </a>

                            {{-- Assignments --}}
                            <a href="{{ route('admin.assignments.index') }}"
                                class="px-3 py-2 rounded-md text-sm font-medium transition duration-150 ease-in-out
                                {{ request()->routeIs('admin.assignments.*') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-800 hover:text-white' }}">
                                📝 Assegnazioni
                            </a>

                            {{-- Clubs --}}
                            <a href="{{ route('admin.clubs.index') }}"
                                class="px-3 py-2 rounded-md text-sm font-medium transition duration-150 ease-in-out
                                {{ request()->routeIs('admin.clubs.*') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-800 hover:text-white' }}">
                                🏌️ Circoli
                            </a>

                            {{-- ✅ LETTERHEADS MENU - AGGIUNTO --}}
                            <a href="{{ route('admin.letterheads.index') }}"
                                class="px-3 py-2 rounded-md text-sm font-medium transition duration-150 ease-in-out
                                {{ request()->routeIs('admin.letterheads.*') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-800 hover:text-white' }}">
                                📄 Letterheads
                            </a>

                            {{-- ✅ STATISTICS MENU - AGGIUNTO --}}
                            <a href="{{ route('admin.statistics.dashboard') }}"
                                class="px-3 py-2 rounded-md text-sm font-medium transition duration-150 ease-in-out
                                {{ request()->routeIs('admin.statistics.*') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-800 hover:text-white' }}">
                                📊 Statistiche
                            </a>

                            {{-- Dropdown Menu for More Options --}}
                            <div class="relative" x-data="{ dropdownOpen: false }">
                                <button @click="dropdownOpen = !dropdownOpen"
                                    class="px-3 py-2 rounded-md text-sm font-medium text-blue-100 hover:bg-blue-800 hover:text-white transition duration-150 ease-in-out flex items-center">
                                    ⚙️ Gestione
                                    <svg class="ml-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>

                                <div x-show="dropdownOpen"
                                     x-transition:enter="transition ease-out duration-100"
                                     x-transition:enter-start="transform opacity-0 scale-95"
                                     x-transition:enter-end="transform opacity-100 scale-100"
                                     x-transition:leave="transition ease-in duration-75"
                                     x-transition:leave-start="transform opacity-100 scale-100"
                                     x-transition:leave-end="transform opacity-0 scale-95"
                                     @click.away="dropdownOpen = false"
                                     class="absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
                                    <div class="py-1">

                                        {{-- Communications --}}
                                        <a href="{{ route('admin.communications.index') }}"
                                            class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100
                                            {{ request()->routeIs('admin.communications.*') ? 'bg-gray-50 text-gray-900' : '' }}">
                                            📢 Comunicazioni
                                        </a>

                                        {{-- Letter Templates --}}
                                        <a href="{{ route('admin.letter-templates.index') }}"
                                            class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100
                                            {{ request()->routeIs('admin.letter-templates.*') ? 'bg-gray-50 text-gray-900' : '' }}">
                                            📝 Template Lettere
                                        </a>

                                        {{-- ✅ NOTIFICATIONS - ROUTE FIXATA --}}
                                        <a href="{{ route('admin.notifications.index') }}"
                                            class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100
                                            {{ request()->routeIs('admin.notifications.*') ? 'bg-gray-50 text-gray-900' : '' }}">
                                            🔔 Notifiche
                                        </a>

                                        <div class="border-t border-gray-100 my-1"></div>

                                        {{-- ✅ MONITORING - AGGIUNTO --}}
                                        <a href="{{ route('admin.monitoring.dashboard') }}"
                                            class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100
                                            {{ request()->routeIs('admin.monitoring.*') ? 'bg-gray-50 text-gray-900' : '' }}">
                                            🖥️ Monitoraggio
                                        </a>

                                        {{-- Reports --}}
                                        <a href="{{ route('reports.dashboard') }}"
                                            class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100
                                            {{ request()->routeIs('reports.*') ? 'bg-gray-50 text-gray-900' : '' }}">
                                            📈 Report
                                        </a>

                                        {{-- Documents --}}
                                        <a href="{{ route('admin.documents.index') }}"
                                            class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100
                                            {{ request()->routeIs('admin.documents.*') ? 'bg-gray-50 text-gray-900' : '' }}">
                                            📁 Documenti
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- User Menu --}}
                    <div class="flex items-center">
                        <div class="relative" x-data="{ userMenuOpen: false }">
                            <button @click="userMenuOpen = !userMenuOpen"
                                class="flex items-center max-w-xs text-sm text-white focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-blue-900">
                                <span class="mr-2">{{ Auth::user()->name ?? 'Admin' }}</span>
                                <div class="h-8 w-8 rounded-full bg-blue-700 flex items-center justify-center">
                                    {{ strtoupper(substr(Auth::user()->name ?? 'A', 0, 1)) }}
                                </div>
                                <svg class="ml-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>

                            <div x-show="userMenuOpen"
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="transform opacity-0 scale-95"
                                 x-transition:enter-end="transform opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75"
                                 x-transition:leave-start="transform opacity-100 scale-100"
                                 x-transition:leave-end="transform opacity-0 scale-95"
                                 @click.away="userMenuOpen = false"
                                 class="absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
                                <div class="py-1">
                                    <a href="{{ route('profile.edit') }}"
                                        class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        👤 Profilo
                                    </a>
                                    <a href="{{ route('admin.settings') }}"
                                        class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        ⚙️ Impostazioni
                                    </a>

                                    {{-- Super Admin Link --}}
                                    @if(auth()->user()->user_type === 'super_admin')
                                        <div class="border-t border-gray-100 my-1"></div>
                                        <a href="{{ route('super-admin.institutional-emails.index') }}"
                                            class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            🛡️ Super Admin
                                        </a>
                                    @endif

                                    <div class="border-t border-gray-100 my-1"></div>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit"
                                            class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            🚪 Logout
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        {{-- Mobile menu button --}}
                        <button @click="open = !open" class="md:hidden ml-4 inline-flex items-center justify-center p-2 rounded-md text-blue-100 hover:text-white hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-blue-900">
                            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                <path :class="{'hidden': open, 'inline-flex': !open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                <path :class="{'hidden': !open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            {{-- ============================================
                 📱 MOBILE NAVIGATION MENU
                 ============================================ --}}
            <div x-show="open" class="md:hidden bg-blue-800">
                <div class="px-2 pt-2 pb-3 space-y-1">

                    {{-- Dashboard --}}
                    <a href="{{ route('admin.dashboard') }}"
                        class="block px-3 py-2 rounded-md text-base font-medium transition duration-150 ease-in-out
                        {{ request()->routeIs('admin.dashboard') ? 'bg-blue-900 text-white' : 'text-blue-100 hover:bg-blue-900 hover:text-white' }}">
                        🏠 Dashboard
                    </a>

                    {{-- Tournaments --}}
                    <a href="{{ route('admin.tournaments.index') }}"
                        class="block px-3 py-2 rounded-md text-base font-medium transition duration-150 ease-in-out
                        {{ request()->routeIs('admin.tournaments.*') ? 'bg-blue-900 text-white' : 'text-blue-100 hover:bg-blue-900 hover:text-white' }}">
                        📋 Tornei
                    </a>

                    {{-- Calendar --}}
                    <a href="{{ route('tournaments.calendar') }}"
                        class="block px-3 py-2 rounded-md text-base font-medium transition duration-150 ease-in-out
                        {{ request()->routeIs('tournaments.calendar', 'admin.assignments.calendar') ? 'bg-blue-900 text-white' : 'text-blue-100 hover:bg-blue-900 hover:text-white' }}">
                        📅 Calendario
                    </a>

                    {{-- Referees --}}
                    <a href="{{ route('admin.referees.index') }}"
                        class="block px-3 py-2 rounded-md text-base font-medium transition duration-150 ease-in-out
                        {{ request()->routeIs('admin.referees.*') ? 'bg-blue-900 text-white' : 'text-blue-100 hover:bg-blue-900 hover:text-white' }}">
                        👨‍💼 Arbitri
                    </a>

                    {{-- Assignments --}}
                    <a href="{{ route('admin.assignments.index') }}"
                        class="block px-3 py-2 rounded-md text-base font-medium transition duration-150 ease-in-out
                        {{ request()->routeIs('admin.assignments.*') ? 'bg-blue-900 text-white' : 'text-blue-100 hover:bg-blue-900 hover:text-white' }}">
                        📝 Assegnazioni
                    </a>

                    {{-- Clubs --}}
                    <a href="{{ route('admin.clubs.index') }}"
                        class="block px-3 py-2 rounded-md text-base font-medium transition duration-150 ease-in-out
                        {{ request()->routeIs('admin.clubs.*') ? 'bg-blue-900 text-white' : 'text-blue-100 hover:bg-blue-900 hover:text-white' }}">
                        🏌️ Circoli
                    </a>

                    {{-- ✅ LETTERHEADS MOBILE MENU --}}
                    <a href="{{ route('admin.letterheads.index') }}"
                        class="block px-3 py-2 rounded-md text-base font-medium transition duration-150 ease-in-out
                        {{ request()->routeIs('admin.letterheads.*') ? 'bg-blue-900 text-white' : 'text-blue-100 hover:bg-blue-900 hover:text-white' }}">
                        📄 Letterheads
                    </a>

                    {{-- ✅ STATISTICS MOBILE MENU --}}
                    <a href="{{ route('admin.statistics.dashboard') }}"
                        class="block px-3 py-2 rounded-md text-base font-medium transition duration-150 ease-in-out
                        {{ request()->routeIs('admin.statistics.*') ? 'bg-blue-900 text-white' : 'text-blue-100 hover:bg-blue-900 hover:text-white' }}">
                        📊 Statistiche
                    </a>

                    {{-- ✅ NOTIFICATIONS MOBILE - ROUTE FIXATA --}}
                    <a href="{{ route('admin.notifications.index') }}"
                        class="block px-3 py-2 rounded-md text-base font-medium transition duration-150 ease-in-out
                        {{ request()->routeIs('admin.notifications.*') ? 'bg-blue-900 text-white' : 'text-blue-100 hover:bg-blue-900 hover:text-white' }}">
                        🔔 Notifiche
                    </a>

                    {{-- ✅ MONITORING MOBILE MENU --}}
                    <a href="{{ route('admin.monitoring.dashboard') }}"
                        class="block px-3 py-2 rounded-md text-base font-medium transition duration-150 ease-in-out
                        {{ request()->routeIs('admin.monitoring.*') ? 'bg-blue-900 text-white' : 'text-blue-100 hover:bg-blue-900 hover:text-white' }}">
                        🖥️ Monitoraggio
                    </a>

                    {{-- Communications --}}
                    <a href="{{ route('admin.communications.index') }}"
                        class="block px-3 py-2 rounded-md text-base font-medium transition duration-150 ease-in-out
                        {{ request()->routeIs('admin.communications.*') ? 'bg-blue-900 text-white' : 'text-blue-100 hover:bg-blue-900 hover:text-white' }}">
                        📢 Comunicazioni
                    </a>

                    {{-- Letter Templates --}}
                    <a href="{{ route('admin.letter-templates.index') }}"
                        class="block px-3 py-2 rounded-md text-base font-medium transition duration-150 ease-in-out
                        {{ request()->routeIs('admin.letter-templates.*') ? 'bg-blue-900 text-white' : 'text-blue-100 hover:bg-blue-900 hover:text-white' }}">
                        📝 Template Lettere
                    </a>

                    {{-- Reports --}}
                    <a href="{{ route('reports.dashboard') }}"
                        class="block px-3 py-2 rounded-md text-base font-medium transition duration-150 ease-in-out
                        {{ request()->routeIs('reports.*') ? 'bg-blue-900 text-white' : 'text-blue-100 hover:bg-blue-900 hover:text-white' }}">
                        📈 Report
                    </a>

                    {{-- Documents --}}
                    <a href="{{ route('admin.documents.index') }}"
                        class="block px-3 py-2 rounded-md text-base font-medium transition duration-150 ease-in-out
                        {{ request()->routeIs('admin.documents.*') ? 'bg-blue-900 text-white' : 'text-blue-100 hover:bg-blue-900 hover:text-white' }}">
                        📁 Documenti
                    </a>
                </div>
            </div>
        </nav>

        {{-- ============================================
             📄 MAIN CONTENT AREA
             ============================================ --}}
        <main class="flex-1">
            {{-- Page Header --}}
            @hasSection('header')
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        @yield('header')
                    </div>
                </header>
            @endif

            {{-- Flash Messages --}}
            @if(session('success'))
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4">
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                        <span class="block sm:inline">{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4">
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                        <span class="block sm:inline">{{ session('error') }}</span>
                    </div>
                </div>
            @endif

            @if(session('warning'))
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4">
                    <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative" role="alert">
                        <span class="block sm:inline">{{ session('warning') }}</span>
                    </div>
                </div>
            @endif

            {{-- Page Content --}}
            <div class="py-6">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    @yield('content')
                </div>
            </div>
        </main>
    </div>

    {{-- Page-specific scripts --}}
    @stack('scripts')
</body>
</html>

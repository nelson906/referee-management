{{-- UPDATED REFEREE LAYOUT - Fix background colors to match admin --}}

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Area Arbitro') - {{ config('app.name', 'Golf Referee System') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Styles -->
    @vite(['resources/css/app.css'])
    @stack('styles')
</head>
<body class="font-sans antialiased bg-gray-100">
    <div class="min-h-screen">
        <!-- Navigation - SAME COLORS AS ADMIN -->
        <nav class="bg-blue-800 border-b border-blue-700" x-data="{ open: false }">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <!-- Logo -->
                        <div class="flex-shrink-0 flex items-center">
                            <h1 class="text-white text-xl font-bold">
                                Area Arbitro
                            </h1>
                        </div>

                        <!-- Navigation Links (Desktop) -->
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

                            <a href="{{ route('tournaments.calendar') }}"
                               class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition duration-150 ease-in-out
                               {{ request()->routeIs('tournaments.calendar') ? 'border-white text-white' : 'border-transparent text-blue-100 hover:text-white hover:border-blue-300' }}">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9v-9m0-9v9"></path>
                                </svg>
                                Tornei Pubblici
                            </a>
                        </div>
                    </div>

                    <!-- User Menu -->
                    <div class="hidden sm:ml-6 sm:flex sm:items-center">
                        <div class="ml-3 relative" x-data="{ open: false }">
                            <button @click="open = !open" class="flex text-sm rounded-full text-white hover:text-blue-200 focus:outline-none focus:text-blue-200 transition duration-150 ease-in-out">
                                <span class="sr-only">Open user menu</span>
                                <div class="flex items-center space-x-2">
                                    <span>{{ auth()->user()->name }}</span>
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </button>

                            <div x-show="open" @click.away="open = false" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="transform opacity-0 scale-95" x-transition:enter-end="transform opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="transform opacity-100 scale-100" x-transition:leave-end="transform opacity-0 scale-95" class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none" style="display: none;">
                                <div class="py-1">
                                    <a href="{{ route('referee.profile.edit') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Profilo</a>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            Logout
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Mobile menu button -->
                    <div class="-mr-2 flex items-center sm:hidden">
                        <button @click="open = !open" class="inline-flex items-center justify-center p-2 rounded-md text-blue-100 hover:text-white hover:bg-blue-700 focus:outline-none focus:bg-blue-700 focus:text-white transition duration-150 ease-in-out">
                            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                <path :class="{'hidden': open, 'inline-flex': !open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                <path :class="{'hidden': !open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile Navigation - SAME COLORS AS ADMIN -->
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

                    <a href="{{ route('tournaments.calendar') }}"
                       class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium transition duration-150 ease-in-out
                       {{ request()->routeIs('tournaments.calendar') ? 'border-white text-white bg-blue-800' : 'border-transparent text-blue-100 hover:text-white hover:bg-blue-800 hover:border-blue-300' }}">
                        🌐 Tornei Pubblici
                    </a>
                </div>

                <!-- Mobile User Menu -->
                <div class="pt-4 pb-3 border-t border-blue-700">
                    <div class="flex items-center px-4">
                        <div class="text-base font-medium text-white">{{ auth()->user()->name }}</div>
                        <div class="text-sm text-blue-300 ml-2">({{ auth()->user()->email }})</div>
                    </div>
                    <div class="mt-3 space-y-1">
                        <a href="{{ route('referee.profile.edit') }}" class="block px-3 py-2 text-base font-medium text-blue-100 hover:text-white hover:bg-blue-700">Profilo</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block w-full text-left px-3 py-2 text-base font-medium text-blue-100 hover:text-white hover:bg-blue-700">
                                Logout
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Flash Messages -->
        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mx-4 mt-4" role="alert">
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mx-4 mt-4" role="alert">
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        @endif

        @if(session('warning'))
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mx-4 mt-4" role="alert">
                <span class="block sm:inline">{{ session('warning') }}</span>
            </div>
        @endif

        <!-- Main Content -->
        <main>
            @yield('content')
        </main>
    </div>

    <!-- Scripts -->
    @vite(['resources/js/app.js'])
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @stack('scripts')
</body>
</html>

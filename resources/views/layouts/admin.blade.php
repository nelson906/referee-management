<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Admin Panel') - {{ config('app.name', 'Golf Referee System') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Additional CSS -->
    @stack('styles')
</head>
<body class="font-sans antialiased bg-gray-100">
    <div class="min-h-screen">
        <!-- Navigation -->
        <nav class="bg-gradient-to-r from-blue-600 to-blue-800 shadow-lg">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <!-- Logo -->
                        <div class="flex-shrink-0 flex items-center">
                            <a href="{{ route('admin.dashboard') }}" class="text-xl font-bold text-white">
                                🏌️ Admin Panel
                            </a>
                            @if(auth()->user()->user_type == 'national_admin')
                                <span class="ml-2 px-2 py-1 text-xs bg-yellow-500 text-white rounded">CRC</span>
                            @else
                                <span class="ml-2 px-2 py-1 text-xs bg-green-500 text-white rounded">
                                    {{ auth()->user()->zone->name ?? 'Zona' }}
                                </span>
                            @endif
                        </div>

                        <!-- Navigation Links -->
                        <div class="hidden space-x-8 sm:-my-px sm:ml-10 sm:flex">
                            <a href="{{ route('admin.dashboard') }}"
                               class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out
                               {{ request()->routeIs('admin.dashboard') ? 'border-white text-white' : 'border-transparent text-blue-100 hover:text-white hover:border-blue-300' }}">
                                Dashboard
                            </a>

                            <a href="{{ route('admin.tournaments.index') }}"
                               class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out
                               {{ request()->routeIs('admin.tournaments.*') ? 'border-white text-white' : 'border-transparent text-blue-100 hover:text-white hover:border-blue-300' }}">
                                Tornei
                            </a>

                            <a href="{{ route('admin.referees.index') }}"
                               class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out
                               {{ request()->routeIs('admin.referees.*') ? 'border-white text-white' : 'border-transparent text-blue-100 hover:text-white hover:border-blue-300' }}">
                                Arbitri
                            </a>

                            <a href="{{ route('admin.circles.index') }}"
                               class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out
                               {{ request()->routeIs('admin.circles.*') ? 'border-white text-white' : 'border-transparent text-blue-100 hover:text-white hover:border-blue-300' }}">
                                Circoli
                            </a>

                            <a href="{{ route('admin.assignments.index') }}"
                               class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out
                               {{ request()->routeIs('admin.assignments.*') ? 'border-white text-white' : 'border-transparent text-blue-100 hover:text-white hover:border-blue-300' }}">
                                Assegnazioni
                            </a>

                            {{-- Dropdown Reports --}}
                            <div class="relative" x-data="{ open: false }">
                                <button @click="open = !open"
                                        class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out
                                        {{ request()->routeIs('reports.*') ? 'border-white text-white' : 'border-transparent text-blue-100 hover:text-white hover:border-blue-300' }}">
                                    Report
                                    <svg class="ml-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>

                                <div x-show="open"
                                     @click.away="open = false"
                                     x-transition:enter="transition ease-out duration-200"
                                     x-transition:enter-start="transform opacity-0 scale-95"
                                     x-transition:enter-end="transform opacity-100 scale-100"
                                     x-transition:leave="transition ease-in duration-75"
                                     x-transition:leave-start="transform opacity-100 scale-100"
                                     x-transition:leave-end="transform opacity-0 scale-95"
                                     class="absolute left-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5"
                                     style="display: none;">
                                    <div class="py-1">
                                        <a href="{{ route('reports.zone.show', auth()->user()->zone_id ?? 1) }}"
                                           class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            Report Zona
                                        </a>
                                        <a href="{{ route('reports.zone.referees', auth()->user()->zone_id ?? 1) }}"
                                           class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            Report Arbitri
                                        </a>
                                        <a href="{{ route('reports.zone.tournaments', auth()->user()->zone_id ?? 1) }}"
                                           class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            Report Tornei
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Side Of Navbar -->
                    <div class="hidden sm:flex sm:items-center sm:ml-6">
                        <!-- Notifications -->
                        <div class="mr-4">
                            <a href="{{ route('admin.notifications.index') }}"
                               class="relative text-blue-100 hover:text-white transition duration-150 ease-in-out">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                                </svg>
                                @php
                                    $pendingNotifications = \App\Models\Notification::where('status', 'pending')->count();
                                @endphp
                                @if($pendingNotifications > 0)
                                    <span class="absolute -top-1 -right-1 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-red-600 rounded-full">
                                        {{ $pendingNotifications }}
                                    </span>
                                @endif
                            </a>
                        </div>

                        <!-- User Info -->
                        <div class="ml-3 relative">
                            <div class="flex items-center text-sm">
                                <span class="text-white mr-4">
                                    {{ auth()->user()->name }}
                                    <span class="text-xs text-blue-200">
                                        ({{ auth()->user()->user_type == 'national_admin' ? 'CRC Admin' : 'Zone Admin' }})
                                    </span>
                                </span>

                                <!-- Dropdown -->
                                <div class="relative" x-data="{ open: false }">
                                    <button @click="open = !open"
                                            class="flex items-center text-sm font-medium text-blue-100 hover:text-white focus:outline-none focus:text-white transition duration-150 ease-in-out">
                                        <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </button>

                                    <div x-show="open"
                                         @click.away="open = false"
                                         x-transition:enter="transition ease-out duration-200"
                                         x-transition:enter-start="transform opacity-0 scale-95"
                                         x-transition:enter-end="transform opacity-100 scale-100"
                                         x-transition:leave="transition ease-in duration-75"
                                         x-transition:leave-start="transform opacity-100 scale-100"
                                         x-transition:leave-end="transform opacity-0 scale-95"
                                         class="absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5"
                                         style="display: none;">
                                        <div class="py-1">
                                            <a href="{{ route('referee.dashboard') }}"
                                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                Area Arbitro
                                            </a>

                                            @if(auth()->user()->user_type == 'super_admin')
                                            <a href="{{ route('super-admin.dashboard') }}"
                                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                Super Admin
                                            </a>
                                            @endif

                                            <div class="border-t border-gray-100"></div>

                                            <a href="{{ route('referee.profile.edit') }}"
                                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                Profilo
                                            </a>

                                            <form method="POST" action="{{ route('logout') }}">
                                                @csrf
                                                <button type="submit"
                                                        class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                    Logout
                                                </button>
                                            </form>
                                        </div>
                                    </div>
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

            <!-- Mobile Navigation -->
            <div :class="{'block': open, 'hidden': !open}" class="hidden sm:hidden bg-blue-700">
                <div class="pt-2 pb-3 space-y-1">
                    <a href="{{ route('admin.dashboard') }}"
                       class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium transition duration-150 ease-in-out
                       {{ request()->routeIs('admin.dashboard') ? 'border-white text-white bg-blue-800' : 'border-transparent text-blue-100 hover:text-white hover:bg-blue-800 hover:border-blue-300' }}">
                        Dashboard
                    </a>

                    <a href="{{ route('admin.tournaments.index') }}"
                       class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium transition duration-150 ease-in-out
                       {{ request()->routeIs('admin.tournaments.*') ? 'border-white text-white bg-blue-800' : 'border-transparent text-blue-100 hover:text-white hover:bg-blue-800 hover:border-blue-300' }}">
                        Tornei
                    </a>

                    <a href="{{ route('admin.referees.index') }}"
                       class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium transition duration-150 ease-in-out
                       {{ request()->routeIs('admin.referees.*') ? 'border-white text-white bg-blue-800' : 'border-transparent text-blue-100 hover:text-white hover:bg-blue-800 hover:border-blue-300' }}">
                        Arbitri
                    </a>

                    <a href="{{ route('admin.circles.index') }}"
                       class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium transition duration-150 ease-in-out
                       {{ request()->routeIs('admin.circles.*') ? 'border-white text-white bg-blue-800' : 'border-transparent text-blue-100 hover:text-white hover:bg-blue-800 hover:border-blue-300' }}">
                        Circoli
                    </a>

                    <a href="{{ route('admin.assignments.index') }}"
                       class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium transition duration-150 ease-in-out
                       {{ request()->routeIs('admin.assignments.*') ? 'border-white text-white bg-blue-800' : 'border-transparent text-blue-100 hover:text-white hover:bg-blue-800 hover:border-blue-300' }}">
                        Assegnazioni
                    </a>
                </div>

                <!-- Mobile User Info -->
                <div class="pt-4 pb-3 border-t border-blue-600">
                    <div class="flex items-center px-4">
                        <div>
                            <div class="text-base font-medium text-white">{{ auth()->user()->name }}</div>
                            <div class="text-sm font-medium text-blue-200">{{ auth()->user()->email }}</div>
                        </div>
                    </div>
                    <div class="mt-3 space-y-1">
                        <a href="{{ route('referee.dashboard') }}"
                           class="block px-4 py-2 text-base font-medium text-blue-100 hover:text-white hover:bg-blue-800">
                            Area Arbitro
                        </a>

                        <a href="{{ route('referee.profile.edit') }}"
                           class="block px-4 py-2 text-base font-medium text-blue-100 hover:text-white hover:bg-blue-800">
                            Profilo
                        </a>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                    class="block w-full text-left px-4 py-2 text-base font-medium text-blue-100 hover:text-white hover:bg-blue-800">
                                Logout
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page Content -->
        <main>
            @yield('content')
        </main>
    </div>

    <!-- Footer -->
    <footer class="bg-white shadow-lg mt-auto">
        <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
            <div class="text-center text-sm text-gray-500">
                © {{ date('Y') }} {{ config('app.name', 'Golf Referee System') }}. Sistema di gestione arbitri golf.
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    @stack('scripts')
</body>
</html>

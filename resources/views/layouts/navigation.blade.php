{{-- =================================================================
   🧭 NAVIGATION MENU UNIFICATO - resources/views/layouts/navigation.blade.php
   ================================================================= --}}

<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ml-10 sm:flex">

                    {{-- ✅ DASHBOARD per tutti --}}
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        🏠 {{ __('Dashboard') }}
                    </x-nav-link>

                    @auth
                        {{-- ============================================
                             📅 CALENDARIO UNIFICATO (tutti gli utenti)
                             Smart behavior: stesso link, funzioni diverse
                             ============================================ --}}

                        <x-nav-link :href="route('tournaments.index')" :active="request()->routeIs('tournaments.index')">
                            <svg class="w-4 h-4 mr-1 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                            Lista Tornei
                        </x-nav-link>

                        {{-- ✅ CALENDARIO UNIFICATO per tutti --}}
                        <x-nav-link :href="route('tournaments.calendar')" :active="request()->routeIs('tournaments.calendar')">
                            <svg class="w-4 h-4 mr-1 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            📅 Calendario Tornei
                            @if(in_array(auth()->user()->user_type ?? '', ['admin', 'national_admin', 'super_admin']))
                                <span class="text-xs text-blue-600 ml-1">(Admin)</span>
                            @endif
                        </x-nav-link>

                        {{-- ============================================
                             ⚽ MENU SPECIFICO ARBITRI
                             ============================================ --}}
                        @if(auth()->user()->user_type === 'referee')
                            <x-dropdown align="left" width="48">
                                <x-slot name="trigger">
                                    <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                                        <div>⚽ Le Mie Attività</div>
                                        <div class="ml-1">
                                            <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    </button>
                                </x-slot>
                                <x-slot name="content">
                                    <x-dropdown-link :href="route('referee.availability.index')">
                                        📝 Le Mie Disponibilità
                                    </x-dropdown-link>
                                    <x-dropdown-link :href="route('referee.availability.calendar')">
                                        📅 Mio Calendario Personale
                                    </x-dropdown-link>
                                    <x-dropdown-link :href="route('referee.assignments.index')">
                                        📋 Le Mie Assegnazioni
                                    </x-dropdown-link>
                                    <div class="border-t border-gray-100"></div>
                                    <x-dropdown-link :href="route('referee.profile.show')">
                                        👤 Il Mio Profilo
                                    </x-dropdown-link>
                                </x-slot>
                            </x-dropdown>
                        @endif

                        {{-- ============================================
                             🔧 MENU AMMINISTRATORI
                             ============================================ --}}
                        @if(in_array(auth()->user()->user_type ?? '', ['admin', 'national_admin', 'super_admin']))
                            <x-dropdown align="left" width="52">
                                <x-slot name="trigger">
                                    <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                                        <div>⚙️ Gestione Admin</div>
                                        <div class="ml-1">
                                            <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    </button>
                                </x-slot>
                                <x-slot name="content">
                                    {{-- Tornei Management --}}
                                    <div class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase">Tornei</div>
                                    <x-dropdown-link :href="route('admin.tournaments.create')">
                                        ➕ Nuovo Torneo
                                    </x-dropdown-link>
                                    <x-dropdown-link :href="route('admin.tournaments.index')">
                                        🏆 Gestione Tornei
                                    </x-dropdown-link>

                                    {{-- Arbitri Management --}}
                                    <div class="border-t border-gray-100 mt-2 pt-2">
                                        <div class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase">Arbitri</div>
                                    </div>
                                    <x-dropdown-link :href="route('admin.referees.index')">
                                        👥 Gestione Arbitri
                                    </x-dropdown-link>
                                    <x-dropdown-link :href="route('admin.assignments.index')">
                                        📋 Gestione Assegnazioni
                                    </x-dropdown-link>

                                    {{-- Comunicazioni --}}
                                    <div class="border-t border-gray-100 mt-2 pt-2">
                                        <div class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase">Comunicazioni</div>
                                    </div>
                                    <x-dropdown-link :href="route('admin.communications.index')">
                                        📢 Comunicazioni
                                    </x-dropdown-link>
                                    <x-dropdown-link :href="route('notifications.index')">
                                        📬 Notifiche
                                    </x-dropdown-link>

                                    {{-- Reports --}}
                                    <div class="border-t border-gray-100 mt-2 pt-2">
                                        <div class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase">Reports</div>
                                    </div>
                                    <x-dropdown-link :href="route('reports.dashboard')">
                                        📊 Statistiche e Report
                                    </x-dropdown-link>
                                </x-slot>
                            </x-dropdown>
                        @endif

                        {{-- ============================================
                             🛡️ MENU SUPER ADMIN
                             ============================================ --}}
                        @if(auth()->user()->user_type === 'super_admin')
                            <x-dropdown align="left" width="56">
                                <x-slot name="trigger">
                                    <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-red-500 bg-red-50 hover:text-red-700 hover:bg-red-100 focus:outline-none transition ease-in-out duration-150">
                                        <div>🛡️ Super Admin</div>
                                        <div class="ml-1">
                                            <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    </button>
                                </x-slot>
                                <x-slot name="content">
                                    {{-- Sistema --}}
                                    <div class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase">Sistema</div>
                                    <x-dropdown-link :href="route('super-admin.users.index')">
                                        👤 Gestione Utenti
                                    </x-dropdown-link>
                                    <x-dropdown-link :href="route('super-admin.zones.index')">
                                        🌍 Gestione Zone
                                    </x-dropdown-link>

                                    {{-- Configurazione --}}
                                    <div class="border-t border-gray-100 mt-2 pt-2">
                                        <div class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase">Configurazione</div>
                                    </div>
                                    <x-dropdown-link :href="route('super-admin.tournament-types.index')">
                                        🏆 Categorie Tornei
                                    </x-dropdown-link>
                                    <x-dropdown-link :href="route('super-admin.institutional-emails.index')">
                                        📧 Email Istituzionali
                                    </x-dropdown-link>
                                    <x-dropdown-link :href="route('super-admin.settings.index')">
                                        ⚙️ Impostazioni Sistema
                                    </x-dropdown-link>
                                </x-slot>
                            </x-dropdown>
                        @endif
                    @endauth
                </div>
            </div>

            {{-- ============================================
                 👤 USER SETTINGS DROPDOWN
                 ============================================ --}}
            @auth
                <div class="hidden sm:flex sm:items-center sm:ml-6">
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                                <div class="flex items-center">
                                    {{-- User Type Badge --}}
                                    @if(auth()->user()->user_type === 'super_admin')
                                        <span class="w-2 h-2 bg-red-500 rounded-full mr-2"></span>
                                    @elseif(in_array(auth()->user()->user_type ?? '', ['admin', 'national_admin']))
                                        <span class="w-2 h-2 bg-blue-500 rounded-full mr-2"></span>
                                    @else
                                        <span class="w-2 h-2 bg-green-500 rounded-full mr-2"></span>
                                    @endif

                                    <div class="text-right">
                                        <div class="font-medium">{{ Auth::user()->name }}</div>
                                        <div class="text-xs text-gray-400">
                                            @switch(auth()->user()->user_type)
                                                @case('super_admin') Super Admin @break
                                                @case('national_admin') Admin Nazionale @break
                                                @case('admin') Admin Zona @break
                                                @case('referee') Arbitro @break
                                                @default Utente @break
                                            @endswitch
                                            @if(auth()->user()->user_type !== 'super_admin' && auth()->user()->zone)
                                                - {{ auth()->user()->zone->name }}
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="ml-1">
                                    <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </button>
                        </x-slot>
                        <x-slot name="content">
                            <x-dropdown-link :href="route('profile.edit')">
                                👤 {{ __('Profile') }}
                            </x-dropdown-link>

                            @if(auth()->user()->user_type === 'referee')
                                <x-dropdown-link :href="route('referee.profile.show')">
                                    ⚽ Profilo Arbitro
                                </x-dropdown-link>
                            @endif

                            <div class="border-t border-gray-100"></div>

                            <!-- Authentication -->
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')"
                                        onclick="event.preventDefault();
                                                    this.closest('form').submit();">
                                    🚪 {{ __('Log Out') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </div>
            @else
                {{-- Guest Links --}}
                <div class="hidden sm:flex sm:items-center sm:ml-6">
                    <a href="{{ route('login') }}" class="text-sm text-gray-700 underline">Log in</a>
                </div>
            @endauth

            {{-- ============================================
                 📱 HAMBURGER MENU (Mobile)
                 ============================================ --}}
            <div class="-mr-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- ============================================
         📱 RESPONSIVE NAVIGATION MENU (Mobile)
         ============================================ --}}
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                🏠 {{ __('Dashboard') }}
            </x-responsive-nav-link>

            @auth
                {{-- Mobile: Menu unificato --}}
                <x-responsive-nav-link :href="route('tournaments.index')" :active="request()->routeIs('tournaments.index')">
                    📋 Lista Tornei
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('tournaments.calendar')" :active="request()->routeIs('tournaments.calendar')">
                    📅 Calendario Tornei
                    @if(in_array(auth()->user()->user_type ?? '', ['admin', 'national_admin', 'super_admin']))
                        <span class="text-xs text-blue-600 ml-1">(Admin)</span>
                    @endif
                </x-responsive-nav-link>

                @if(auth()->user()->user_type === 'referee')
                    {{-- Mobile: Referee Links --}}
                    <div class="border-t border-gray-200 mt-2 pt-2">
                        <div class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase">Le Mie Attività</div>
                    </div>
                    <x-responsive-nav-link :href="route('referee.availability.index')" :active="request()->routeIs('referee.availability.*')">
                        📝 Le Mie Disponibilità
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('referee.availability.calendar')" :active="request()->routeIs('referee.availability.calendar')">
                        📅 Mio Calendario Personale
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('referee.assignments.index')" :active="request()->routeIs('referee.assignments.*')">
                        📋 Le Mie Assegnazioni
                    </x-responsive-nav-link>
                @endif

                @if(in_array(auth()->user()->user_type ?? '', ['admin', 'national_admin', 'super_admin']))
                    {{-- Mobile: Admin Links --}}
                    <div class="border-t border-gray-200 mt-2 pt-2">
                        <div class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase">Gestione Admin</div>
                    </div>
                    <x-responsive-nav-link :href="route('admin.tournaments.create')" :active="request()->routeIs('admin.tournaments.create')">
                        ➕ Nuovo Torneo
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.tournaments.index')" :active="request()->routeIs('admin.tournaments.*')">
                        🏆 Gestione Tornei
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.referees.index')" :active="request()->routeIs('admin.referees.*')">
                        👥 Gestione Arbitri
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('notifications.index')" :active="request()->routeIs('notifications.*')">
                        📬 Notifiche
                    </x-responsive-nav-link>
                @endif

                @if(auth()->user()->user_type === 'super_admin')
                    {{-- Mobile: Super Admin Links --}}
                    <div class="border-t border-gray-200 mt-2 pt-2">
                        <div class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase">Super Admin</div>
                    </div>
                    <x-responsive-nav-link :href="route('super-admin.users.index')" :active="request()->routeIs('super-admin.users.*')">
                        👤 Gestione Utenti
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('super-admin.zones.index')" :active="request()->routeIs('super-admin.zones.*')">
                        🌍 Gestione Zone
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('super-admin.institutional-emails.index')" :active="request()->routeIs('super-admin.institutional-emails.*')">
                        📧 Email Istituzionali
                    </x-responsive-nav-link>
                @endif
            @endauth
        </div>

        {{-- Responsive Settings Options --}}
        @auth
            <div class="pt-4 pb-1 border-t border-gray-200">
                <div class="px-4">
                    <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                    <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
                </div>

                <div class="mt-3 space-y-1">
                    <x-responsive-nav-link :href="route('profile.edit')">
                        👤 {{ __('Profile') }}
                    </x-responsive-nav-link>

                    <!-- Authentication -->
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-responsive-nav-link :href="route('logout')"
                                onclick="event.preventDefault();
                                            this.closest('form').submit();">
                            🚪 {{ __('Log Out') }}
                        </x-responsive-nav-link>
                    </form>
                </div>
            </div>
        @endauth
    </div>
</nav>

{{-- =================================================================
   📋 MENU NAVIGATION SUMMARY
   ================================================================= --}}

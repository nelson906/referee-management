{{-- ============================================
     🧭 MAIN NAVIGATION MENU
     ============================================ --}}

{{-- Super Admin Menu Items --}}
@if(auth()->user()->user_type === 'super_admin')
    <div class="hidden space-x-8 sm:-my-px sm:ml-10 sm:flex">

        {{-- Dashboard --}}
        <x-nav-link :href="route('super-admin.institutional-emails.index')" :active="request()->routeIs('super-admin.*')">
            🏠 Dashboard SuperAdmin
        </x-nav-link>

        {{-- Gestione Utenti --}}
        <x-nav-link :href="route('super-admin.users.index')" :active="request()->routeIs('super-admin.users.*')">
            👥 Gestione Utenti
        </x-nav-link>

        {{-- Zone Management --}}
        <x-nav-link :href="route('super-admin.zones.index')" :active="request()->routeIs('super-admin.zones.*')">
            🌍 Gestione Zone
        </x-nav-link>

        {{-- Tournament Types --}}
        <x-nav-link :href="route('super-admin.tournament-types.index')" :active="request()->routeIs('super-admin.tournament-types.*')">
            🏆 Categorie Tornei
        </x-nav-link>

        {{-- Institutional Emails --}}
        <x-nav-link :href="route('super-admin.institutional-emails.index')" :active="request()->routeIs('super-admin.institutional-emails.*')">
            📧 Email Istituzionali
        </x-nav-link>

        {{-- System Settings --}}
        <x-nav-link :href="route('super-admin.settings.index')" :active="request()->routeIs('super-admin.settings.*')">
            ⚙️ Impostazioni Sistema
        </x-nav-link>

        {{-- System Monitoring --}}
        <x-nav-link :href="route('super-admin.system.logs')" :active="request()->routeIs('super-admin.system.*')">
            📊 Monitoraggio Sistema
        </x-nav-link>
    </div>
@endif

{{-- Admin Menu Items --}}
@if(in_array(auth()->user()->user_type ?? '', ['admin', 'national_admin', 'super_admin']))
    <div class="hidden space-x-8 sm:-my-px sm:ml-10 sm:flex">

        {{-- Dashboard Admin --}}
        <x-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
            🏠 Dashboard Admin
        </x-nav-link>

        {{-- Tournament Management --}}
        <x-nav-link :href="route('admin.tournaments.index')" :active="request()->routeIs('admin.tournaments.*')">
            📋 Gestione Tornei
        </x-nav-link>

        {{-- Calendar --}}
        <x-nav-link :href="route('tournaments.calendar')" :active="request()->routeIs('tournaments.calendar', 'admin.assignments.calendar')">
            📅 Calendario
        </x-nav-link>

        {{-- Referee Management --}}
        <x-nav-link :href="route('admin.referees.index')" :active="request()->routeIs('admin.referees.*')">
            👨‍💼 Gestione Arbitri
        </x-nav-link>

        {{-- Assignments --}}
        <x-nav-link :href="route('admin.assignments.index')" :active="request()->routeIs('admin.assignments.*')">
            📝 Assegnazioni
        </x-nav-link>

        {{-- Clubs Management --}}
        <x-nav-link :href="route('admin.clubs.index')" :active="request()->routeIs('admin.clubs.*')">
            🏌️ Gestione Circoli
        </x-nav-link>

        {{-- Communications --}}
        <x-nav-link :href="route('admin.communications.index')" :active="request()->routeIs('admin.communications.*')">
            📢 Comunicazioni
        </x-nav-link>

        {{-- ✅ LETTERHEAD MENU - AGGIUNTO --}}
        <x-nav-link :href="route('admin.letterheads.index')" :active="request()->routeIs('admin.letterheads.*')">
            📄 Carta Intestata
        </x-nav-link>

        {{-- Letter Templates --}}
        <x-nav-link :href="route('admin.letter-templates.index')" :active="request()->routeIs('admin.letter-templates.*')">
            📝 Template Lettere
        </x-nav-link>

        {{-- Notifications --}}
        <x-nav-link :href="route('admin.tournament-notifications.index')" :active="request()->routeIs('admin.notifications.*')">
            🔔 Notifiche
        </x-nav-link>

        {{-- ✅ STATISTICS MENU - AGGIUNTO --}}
        <x-nav-link :href="route('admin.statistics.dashboard')" :active="request()->routeIs('admin.statistics.*')">
            📊 Statistiche
        </x-nav-link>

        {{-- Reports --}}
        <x-nav-link :href="route('reports.dashboard')" :active="request()->routeIs('reports.*')">
            📈 Report
        </x-nav-link>

        {{-- ✅ MONITORING MENU - AGGIUNTO --}}
        <x-nav-link :href="route('admin.monitoring.dashboard')" :active="request()->routeIs('admin.monitoring.*')">
            🖥️ Monitoraggio
        </x-nav-link>

        {{-- Documents --}}
        <x-nav-link :href="route('admin.documents.index')" :active="request()->routeIs('admin.documents.*')">
            📁 Documenti
        </x-nav-link>

        {{-- Settings --}}
        <x-nav-link :href="route('admin.settings')" :active="request()->routeIs('admin.settings')">
            ⚙️ Impostazioni
        </x-nav-link>
    </div>
@endif

{{-- Referee Menu Items --}}
@if(auth()->user()->user_type === 'referee')
    <div class="hidden space-x-8 sm:-my-px sm:ml-10 sm:flex">

        {{-- Referee Dashboard --}}
        <x-nav-link :href="route('referee.dashboard')" :active="request()->routeIs('referee.dashboard')">
            🏠 La Mia Dashboard
        </x-nav-link>

        {{-- Tournaments --}}
        <x-nav-link :href="route('tournaments.index')" :active="request()->routeIs('tournaments.index')">
            📋 Lista Tornei
        </x-nav-link>

        {{-- Availability --}}
        <x-nav-link :href="route('referee.availability.index')" :active="request()->routeIs('referee.availability.*')">
            📝 Le Mie Disponibilità
        </x-nav-link>

        {{-- Personal Calendar --}}
        <x-nav-link :href="route('referee.availability.calendar')" :active="request()->routeIs('referee.availability.calendar')">
            📅 Mio Calendario
        </x-nav-link>

        {{-- Assignments --}}
        <x-nav-link :href="route('referee.assignments.index')" :active="request()->routeIs('referee.assignments.*')">
            📋 Le Mie Assegnazioni
        </x-nav-link>

        {{-- Applications --}}
        <x-nav-link :href="route('referee.applications.index')" :active="request()->routeIs('referee.applications.*')">
            📋 Le Mie Candidature
        </x-nav-link>

        {{-- Documents --}}
        <x-nav-link :href="route('referee.documents.index')" :active="request()->routeIs('referee.documents.*')">
            📁 I Miei Documenti
        </x-nav-link>
    </div>
@endif

{{-- ============================================
     📱 RESPONSIVE NAVIGATION MENU (Mobile)
     ============================================ --}}
<div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
    <div class="pt-2 pb-3 space-y-1">

        {{-- Universal Dashboard Link --}}
        <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
            🏠 {{ __('Dashboard') }}
        </x-responsive-nav-link>

        @auth
            {{-- Common Tournament Links --}}
            <x-responsive-nav-link :href="route('tournaments.index')" :active="request()->routeIs('tournaments.index')">
                📋 Lista Tornei
            </x-responsive-nav-link>

            <x-responsive-nav-link :href="route('tournaments.calendar')" :active="request()->routeIs('tournaments.calendar')">
                📅 Calendario Tornei
                @if(in_array(auth()->user()->user_type ?? '', ['admin', 'national_admin', 'super_admin']))
                    <span class="text-xs text-blue-600 ml-1">(Admin)</span>
                @endif
            </x-responsive-nav-link>

            {{-- Super Admin Mobile Links --}}
            @if(auth()->user()->user_type === 'super_admin')
                <div class="border-t border-gray-200 mt-2 pt-2">
                    <div class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase">Super Admin</div>
                </div>
                <x-responsive-nav-link :href="route('super-admin.users.index')" :active="request()->routeIs('super-admin.users.*')">
                    👥 Gestione Utenti
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('super-admin.zones.index')" :active="request()->routeIs('super-admin.zones.*')">
                    🌍 Gestione Zone
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('super-admin.tournament-types.index')" :active="request()->routeIs('super-admin.tournament-types.*')">
                    🏆 Categorie Tornei
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('super-admin.institutional-emails.index')" :active="request()->routeIs('super-admin.institutional-emails.*')">
                    📧 Email Istituzionali
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('super-admin.settings.index')" :active="request()->routeIs('super-admin.settings.*')">
                    ⚙️ Impostazioni Sistema
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('super-admin.system.logs')" :active="request()->routeIs('super-admin.system.*')">
                    📊 Monitoraggio Sistema
                </x-responsive-nav-link>
            @endif

            {{-- Admin Mobile Links --}}
            @if(in_array(auth()->user()->user_type ?? '', ['admin', 'national_admin', 'super_admin']))
                <div class="border-t border-gray-200 mt-2 pt-2">
                    <div class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase">Amministrazione</div>
                </div>
                <x-responsive-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                    🏠 Dashboard Admin
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.tournaments.index')" :active="request()->routeIs('admin.tournaments.*')">
                    📋 Gestione Tornei
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.referees.index')" :active="request()->routeIs('admin.referees.*')">
                    👨‍💼 Gestione Arbitri
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.assignments.index')" :active="request()->routeIs('admin.assignments.*')">
                    📝 Assegnazioni
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.clubs.index')" :active="request()->routeIs('admin.clubs.*')">
                    🏌️ Gestione Circoli
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.communications.index')" :active="request()->routeIs('admin.communications.*')">
                    📢 Comunicazioni
                </x-responsive-nav-link>

                {{-- ✅ LETTERHEAD MOBILE MENU - AGGIUNTO --}}
                <x-responsive-nav-link :href="route('admin.letterheads.index')" :active="request()->routeIs('admin.letterheads.*')">
                    📄 Carta Intestata
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('admin.letter-templates.index')" :active="request()->routeIs('admin.letter-templates.*')">
                    📝 Template Lettere
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.tournament-notifications.index')" :active="request()->routeIs('admin.notifications.*')">
                    🔔 Notifiche
                </x-responsive-nav-link>

                {{-- ✅ STATISTICS MOBILE MENU - AGGIUNTO --}}
                <x-responsive-nav-link :href="route('admin.statistics.dashboard')" :active="request()->routeIs('admin.statistics.*')">
                    📊 Statistiche
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('reports.dashboard')" :active="request()->routeIs('reports.*')">
                    📈 Report
                </x-responsive-nav-link>

                {{-- ✅ MONITORING MOBILE MENU - AGGIUNTO --}}
                <x-responsive-nav-link :href="route('admin.monitoring.dashboard')" :active="request()->routeIs('admin.monitoring.*')">
                    🖥️ Monitoraggio
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('admin.documents.index')" :active="request()->routeIs('admin.documents.*')">
                    📁 Documenti
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.settings')" :active="request()->routeIs('admin.settings')">
                    ⚙️ Impostazioni
                </x-responsive-nav-link>
            @endif

            {{-- Referee Mobile Links --}}
            @if(auth()->user()->user_type === 'referee')
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
                <x-responsive-nav-link :href="route('referee.applications.index')" :active="request()->routeIs('referee.applications.*')">
                    📋 Le Mie Candidature
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('referee.documents.index')" :active="request()->routeIs('referee.documents.*')">
                    📁 I Miei Documenti
                </x-responsive-nav-link>
            @endif
        @endauth
    </div>

    {{-- User Profile Mobile Section --}}
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

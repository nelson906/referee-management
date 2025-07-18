@extends('layouts.admin')

@section('title', 'Gestione Arbitri')

@section('content')
    <div class="container mx-auto px-4 py-6">
        {{-- Header con componente --}}
        <x-table-header title="Gestione Arbitri" description="Gestisci gli arbitri della tua zona" :create-route="route('admin.referees.create')"
            create-text="Nuovo Arbitro" />

        {{-- Filters COMPATTI con DEFAULT ATTIVI --}}
        <div class="bg-white rounded-lg shadow p-4 mb-4">
            <form method="GET">
                {{-- Prima riga: Ricerca e Filtri principali --}}
                <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-5 gap-3 mb-3">
                    {{-- Ricerca (più largo) --}}
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">🔍 Ricerca</label>
                        <input type="text" name="search" value="{{ request('search') }}"
                            placeholder="Nome, email, codice..."
                            class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    {{-- Livello --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">🏆 Livello</label>
                        <select name="level"
                            class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Tutti</option>
                            @foreach (referee_levels() as $key => $label)
                                <option value="{{ $key }}" {{ request('level') == $key ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Zona --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">📍 Zona</label>
                        <select name="zone_id"
                            class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Tutte</option>
                            @foreach ($zones as $zone)
                                <option value="{{ $zone->id }}" {{ request('zone_id') == $zone->id ? 'selected' : '' }}>
                                    {{ $zone->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Stato con DEFAULT ATTIVI --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">⚡ Stato</label>
                        <select name="status"
                            class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="active" {{ request('status', 'active') == 'active' ? 'selected' : '' }}>✅ Attivi
                            </option>
                            <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>❌ Non Attivi
                            </option>
                            <option value="all" {{ request('status') == 'all' ? 'selected' : '' }}>👥 Tutti</option>
                        </select>
                    </div>
                </div>

                {{-- Seconda riga: Ordinamento e Azioni --}}
                <div class="flex flex-wrap items-end gap-3">
                    {{-- Ordinamento --}}
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-xs font-medium text-gray-600 mb-1">📊 Ordina per</label>
                        <div class="flex gap-2">
                            <select name="sort"
                                class="flex-1 text-sm border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="name" {{ request('sort', 'name') === 'name' ? 'selected' : '' }}>
                                    👤 Nome
                                </option>
                                <option value="last_name" {{ request('sort') === 'last_name' ? 'selected' : '' }}>
                                    📝 Cognome
                                </option>
                                <option value="level" {{ request('sort') === 'level' ? 'selected' : '' }}>
                                    🏆 Livello
                                </option>
                                <option value="zone_name" {{ request('sort') === 'zone_name' ? 'selected' : '' }}>
                                    📍 Zona
                                </option>
                                <option value="is_active" {{ request('sort') === 'is_active' ? 'selected' : '' }}>
                                    ⚡ Stato
                                </option>
                                <option value="created_at" {{ request('sort') === 'created_at' ? 'selected' : '' }}>
                                    📅 Data creazione
                                </option>
                            </select>

                            {{-- Toggle direzione ordinamento --}}
                            <button type="button" onclick="toggleSortDirection()"
                                class="px-3 py-2 text-sm border border-gray-300 rounded-md hover:bg-gray-50 focus:ring-blue-500 focus:border-blue-500"
                                title="Inverti ordinamento">
                                @if (request('direction', 'asc') === 'asc')
                                    ⬆️ A-Z
                                @else
                                    ⬇️ Z-A
                                @endif
                            </button>
                            <input type="hidden" name="direction" value="{{ request('direction', 'asc') }}"
                                id="direction">
                        </div>
                    </div>

                    {{-- Pulsanti azione --}}
                    <div class="flex gap-2">
                        <button type="submit"
                            class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 text-sm font-medium">
                            🔍 Filtra
                        </button>

                        {{-- ✅ Reset torna al default "solo attivi" --}}
                        <a href="{{ route('admin.referees.index', ['status' => 'active']) }}"
                            class="bg-gray-100 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-200 focus:ring-2 focus:ring-gray-500 text-sm font-medium">
                            🔄 Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"
                            clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-700">
                        <strong>{{ $referees->total() }} arbitri</strong> trovati
                        @if (request('sort'))
                            • Ordinati per:
                            <span class="font-medium">
                                @switch(request('sort'))
                                    @case('name')
                                        Nome
                                    @break

                                    @case('last_name')
                                        Cognome
                                    @break

                                    @case('level')
                                        Livello
                                    @break

                                    @case('zone_name')
                                        Zona
                                    @break

                                    @case('is_active')
                                        Stato
                                    @break

                                    @case('created_at')
                                        Data creazione
                                    @break

                                    @default
                                        {{ request('sort') }}
                                @endswitch
                            </span>
                            ({{ request('direction', 'asc') === 'asc' ? 'crescente' : 'decrescente' }})
                        @endif

                        {{-- Link per invertire ordinamento --}}
                        @if (request('sort'))
                            <a href="{{ request()->fullUrlWithQuery(['direction' => request('direction', 'asc') === 'asc' ? 'desc' : 'asc']) }}"
                                class="ml-2 text-blue-600 hover:text-blue-800 underline text-xs">
                                Inverti ordine
                            </a>
                        @endif
                    </p>
                </div>
            </div>
        </div>
        {{-- Table --}}
        <div class="bg-white rounded-lg shadow overflow-hidden">
            @if ($referees->count() > 0)
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            {{-- Colonna Nome/Cognome --}}
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                <div class="flex items-center space-x-1">
                                    <span>Arbitro</span>
                                    <div class="flex flex-col">
                                        {{-- Ordinamento per Nome --}}
                                        <a href="{{ request()->fullUrlWithQuery(['sort' => 'name', 'direction' => request('sort') === 'name' && request('direction') === 'asc' ? 'desc' : 'asc']) }}"
                                            class="text-gray-400 hover:text-gray-600" title="Ordina per nome">
                                            <svg class="w-3 h-3 {{ request('sort') === 'name' && request('direction') === 'asc' ? 'text-blue-600' : '' }}"
                                                fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </a>
                                        {{-- Ordinamento per Cognome --}}
                                        <a href="{{ request()->fullUrlWithQuery(['sort' => 'last_name', 'direction' => request('sort') === 'last_name' && request('direction') === 'asc' ? 'desc' : 'asc']) }}"
                                            class="text-gray-400 hover:text-gray-600" title="Ordina per cognome">
                                            <svg class="w-3 h-3 {{ request('sort') === 'last_name' && request('direction') === 'desc' ? 'text-blue-600' : '' }}"
                                                fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                            </th>

                            {{-- Colonna Livello --}}
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'level', 'direction' => request('sort') === 'level' && request('direction') === 'asc' ? 'desc' : 'asc']) }}"
                                    class="flex items-center space-x-1 hover:text-gray-700">
                                    <span>Livello</span>
                                    <svg class="w-4 h-4 {{ request('sort') === 'level' ? 'text-blue-600' : 'text-gray-400' }}"
                                        fill="currentColor" viewBox="0 0 20 20">
                                        @if (request('sort') === 'level')
                                            @if (request('direction') === 'asc')
                                                <path fill-rule="evenodd"
                                                    d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z"
                                                    clip-rule="evenodd" />
                                            @else
                                                <path fill-rule="evenodd"
                                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                    clip-rule="evenodd" />
                                            @endif
                                        @else
                                            <path d="M5 12l5-5 5 5H5z" />
                                            <path d="M5 8l5 5 5-5H5z" />
                                        @endif
                                    </svg>
                                </a>
                            </th>

                            {{-- Colonna Zona --}}
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'zone_name', 'direction' => request('sort') === 'zone_name' && request('direction') === 'asc' ? 'desc' : 'asc']) }}"
                                    class="flex items-center space-x-1 hover:text-gray-700">
                                    <span>Zona</span>
                                    <svg class="w-4 h-4 {{ request('sort') === 'zone_name' ? 'text-blue-600' : 'text-gray-400' }}"
                                        fill="currentColor" viewBox="0 0 20 20">
                                        @if (request('sort') === 'zone_name')
                                            @if (request('direction') === 'asc')
                                                <path fill-rule="evenodd"
                                                    d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z"
                                                    clip-rule="evenodd" />
                                            @else
                                                <path fill-rule="evenodd"
                                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                    clip-rule="evenodd" />
                                            @endif
                                        @else
                                            <path d="M5 12l5-5 5 5H5z" />
                                            <path d="M5 8l5 5 5-5H5z" />
                                        @endif
                                    </svg>
                                </a>
                            </th>

                            {{-- Colonna Stato --}}
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'is_active', 'direction' => request('sort') === 'is_active' && request('direction') === 'asc' ? 'desc' : 'asc']) }}"
                                    class="flex items-center space-x-1 hover:text-gray-700">
                                    <span>Stato</span>
                                    <svg class="w-4 h-4 {{ request('sort') === 'is_active' ? 'text-blue-600' : 'text-gray-400' }}"
                                        fill="currentColor" viewBox="0 0 20 20">
                                        @if (request('sort') === 'is_active')
                                            @if (request('direction') === 'asc')
                                                <path fill-rule="evenodd"
                                                    d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z"
                                                    clip-rule="evenodd" />
                                            @else
                                                <path fill-rule="evenodd"
                                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                    clip-rule="evenodd" />
                                            @endif
                                        @else
                                            <path d="M5 12l5-5 5 5H5z" />
                                            <path d="M5 8l5 5 5-5H5z" />
                                        @endif
                                    </svg>
                                </a>
                            </th>

                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                                Azioni
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($referees as $referee)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{-- Info arbitro --}}
                                    <div class="text-sm font-medium text-gray-900">{{ $referee->name }}</div>
                                    <div class="text-sm text-gray-500">{{ $referee->email }}</div>
                                    @if ($referee->referee_code)
                                        <div class="text-xs text-gray-400">{{ $referee->referee_code }}</div>
                                    @endif
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        {{ referee_level_label($referee->level) }}
                                    </span>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $referee->zone->name ?? 'N/A' }}
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{-- Status con componente --}}
                                    <x-status-badge :status="$referee->is_active" />
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <x-table-actions-referee :referee="$referee" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                {{-- Paginazione --}}
                <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    <div class="flex justify-between items-center">
                        <div class="text-sm text-gray-700">
                            Mostrando
                            <span class="font-medium">{{ $referees->firstItem() ?: 0 }}</span>
                            -
                            <span class="font-medium">{{ $referees->lastItem() ?: 0 }}</span>
                            di
                            <span class="font-medium">{{ $referees->total() }}</span>
                            arbitri
                        </div>

                        {{ $referees->links() }}
                    </div>
                </div>
            @else
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM9 9a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">Nessun arbitro trovato</h3>
                    <p class="mt-1 text-sm text-gray-500">Inizia creando un nuovo arbitro.</p>
                    <div class="mt-6">
                        <a href="{{ route('admin.referees.create') }}"
                            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                            <svg class="-ml-1 mr-2 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                                    clip-rule="evenodd" />
                            </svg>
                            Nuovo Arbitro
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
@extends('layouts.admin')

@section('title', 'Gestione Arbitri')

@section('content')
    <div class="container mx-auto px-4 py-6">
        {{-- Header con componente --}}
        <x-table-header title="Gestione Arbitri" description="Gestisci gli arbitri della tua zona" :create-route="route('admin.referees.create')"
            create-text="Nuovo Arbitro" />

        {{-- Filters --}}
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cerca</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Nome, email, codice..." class="w-full border border-gray-300 rounded-md px-3 py-2">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Livello</label>
                    <select name="level" class="w-full border border-gray-300 rounded-md px-3 py-2">
                        <option value="">Tutti i livelli</option>
                        @foreach (referee_levels() as $key => $label)
                            <option value="{{ $key }}" {{ request('level') == $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Zona</label>
                    <select name="zone_id" class="w-full border border-gray-300 rounded-md px-3 py-2">
                        <option value="">Tutte le zone</option>
                        @foreach ($zones as $zone)
                            <option value="{{ $zone->id }}" {{ request('zone_id') == $zone->id ? 'selected' : '' }}>
                                {{ $zone->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Stato</label>
                    <select name="status" class="w-full border border-gray-300 rounded-md px-3 py-2">
                        <option value="">Tutti</option>
                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Attivi</option>
                        <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Non Attivi
                        </option>
                    </select>
                </div>

                {{-- ✅ ORDINAMENTO RAPIDO --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ordina per</label>
                    <select name="sort" class="w-full border border-gray-300 rounded-md px-3 py-2"
                        onchange="this.form.submit()">
                        <option value="name" {{ request('sort', 'name') === 'name' ? 'selected' : '' }}>
                            Nome (A-Z)
                        </option>
                        <option value="last_name" {{ request('sort') === 'last_name' ? 'selected' : '' }}>
                            Cognome (A-Z)
                        </option>
                        <option value="level" {{ request('sort') === 'level' ? 'selected' : '' }}>
                            Livello
                        </option>
                        <option value="zone_name" {{ request('sort') === 'zone_name' ? 'selected' : '' }}>
                            Zona
                        </option>
                        <option value="is_active" {{ request('sort') === 'is_active' ? 'selected' : '' }}>
                            Stato
                        </option>
                        <option value="created_at" {{ request('sort') === 'created_at' ? 'selected' : '' }}>
                            Data creazione
                        </option>
                    </select>
                    <input type="hidden" name="direction" value="{{ request('direction', 'asc') }}">
                </div>

                <div class="flex items-end space-x-2">
                    <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                        Filtra
                    </button>
                    <a href="{{ route('admin.referees.index') }}"
                        class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400">
                        Reset
                    </a>
                </div>
            </form>
        </div>

{{-- ✅ INDICATORE STATO INTELLIGENTE --}}
@if($referees->total() > 0)
    <div class="bg-blue-50 border-l-4 border-blue-400 p-3 mb-4 rounded-r-md">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                {{-- Contatore --}}
                <div class="flex items-center text-blue-700">
                    <svg class="h-4 w-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
                    </svg>
                    <span class="text-sm font-medium">{{ $referees->total() }} arbitri</span>

                    {{-- ✅ Mostra stato filtro solo se non è il default --}}
                    @if(request('status') == 'inactive')
                        <span class="ml-1 text-red-600 font-medium">(Non Attivi)</span>
                    @elseif(request('status') == 'all')
                        <span class="ml-1 text-gray-600 font-medium">(Tutti)</span>
                    @else
                        <span class="ml-1 text-green-600 font-medium">(Attivi)</span>
                    @endif
                </div>

                {{-- Ordinamento corrente --}}
                @if(request('sort'))
                    <div class="flex items-center text-blue-600 text-sm">
                        <span class="mr-1">📊</span>
                        <span>
                            @switch(request('sort'))
                                @case('name') Nome @break
                                @case('last_name') Cognome @break
                                @case('level') Livello @break
                                @case('zone_name') Zona @break
                                @case('is_active') Stato @break
                                @case('created_at') Data @break
                                @default {{ request('sort') }}
                            @endswitch
                        </span>
                        <span class="ml-1">
                            {{ request('direction', 'asc') === 'asc' ? '⬆️' : '⬇️' }}
                        </span>
                    </div>
                @endif

                {{-- ✅ Filtri attivi (escluso status=active che è default) --}}
                @php
                    $activeFilters = collect([
                        'search' => request('search'),
                        'level' => request('level'),
                        'zone_id' => request('zone_id'),
                        // Solo conta status se NON è il default 'active'
                        'status' => request('status') && request('status') !== 'active' ? request('status') : null
                    ])->filter()->count();
                @endphp

                @if($activeFilters > 0)
                    <div class="flex items-center text-amber-600 text-sm">
                        <span class="mr-1">🔍</span>
                        <span>{{ $activeFilters }} filtro{{ $activeFilters > 1 ? 'i' : '' }} attivo{{ $activeFilters > 1 ? 'i' : '' }}</span>
                    </div>
                @endif
            </div>

            {{-- Azioni rapide --}}
            <div class="flex items-center space-x-2">
                @if(request('sort'))
                    <a href="{{ request()->fullUrlWithQuery(['direction' => request('direction', 'asc') === 'asc' ? 'desc' : 'asc']) }}"
                       class="text-xs text-blue-600 hover:text-blue-800 px-2 py-1 rounded border border-blue-200 hover:bg-blue-100">
                        🔄 Inverti
                    </a>
                @endif

                {{-- ✅ Mostra "Vedi tutti" se è applicato solo il default attivi --}}
                @if(request('status', 'active') == 'active' && $activeFilters == 0)
                    <a href="{{ route('admin.referees.index', ['status' => 'all'] + request()->except(['status'])) }}"
                       class="text-xs text-gray-600 hover:text-gray-800 px-2 py-1 rounded border border-gray-200 hover:bg-gray-100">
                        👥 Vedi tutti
                    </a>
                @endif

                {{-- ✅ Mostra "Pulisci filtri" solo se ci sono filtri oltre al default --}}
                @if($activeFilters > 0 || (request('status') && request('status') !== 'active'))
                    <a href="{{ route('admin.referees.index', ['status' => 'active'] + (request('sort') ? ['sort' => request('sort'), 'direction' => request('direction')] : [])) }}"
                       class="text-xs text-gray-600 hover:text-gray-800 px-2 py-1 rounded border border-gray-200 hover:bg-gray-100">
                        ✖️ Pulisci filtri
                    </a>
                @endif

                {{-- Link rapido per vedere non attivi --}}
                @if(request('status', 'active') == 'active')
                    <a href="{{ route('admin.referees.index', ['status' => 'inactive'] + request()->except(['status'])) }}"
                       class="text-xs text-red-600 hover:text-red-800 px-2 py-1 rounded border border-red-200 hover:bg-red-50">
                        ❌ Non Attivi
                    </a>
                @endif
            </div>
        </div>
    </div>
@else
    {{-- ✅ Messaggio vuoto più specifico in base al filtro --}}
    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-3 mb-4 rounded-r-md">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-yellow-700">
                    @if(request('status') == 'inactive')
                        <strong>Nessun arbitro non attivo</strong> trovato.
                    @elseif(request('status') == 'all')
                        <strong>Nessun arbitro</strong> trovato.
                    @else
                        <strong>Nessun arbitro attivo</strong> trovato.
                    @endif

                    @if($activeFilters > 0)
                        Prova a <a href="{{ route('admin.referees.index', ['status' => request('status', 'active')]) }}" class="underline hover:no-underline">rimuovere i filtri</a>.
                    @endif
                </p>
            </div>
        </div>
    </div>
@endif

        {{-- Table --}}
        <div class="bg-white rounded-lg shadow overflow-hidden">
            @if ($referees->count() > 0)
                <table class="min-w-full divide-y divide-gray-200">
                    {{-- ✅ HEADER CON ORDINAMENTO CLICKABILE --}}
                    <thead class="bg-gray-50">
                        <tr>
                            {{-- Nome (semplificato) --}}
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <div class="flex items-center space-x-1">
                                    <span>👤 Arbitro</span>
                                    <div class="flex space-x-1">
                                        <a href="{{ request()->fullUrlWithQuery(['sort' => 'name', 'direction' => 'asc']) }}"
                                            class="text-gray-400 hover:text-blue-600 {{ request('sort') === 'name' && request('direction') === 'asc' ? 'text-blue-600' : '' }}"
                                            title="Nome A-Z">
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </a>
                                        <a href="{{ request()->fullUrlWithQuery(['sort' => 'last_name', 'direction' => 'asc']) }}"
                                            class="text-gray-400 hover:text-blue-600 {{ request('sort') === 'last_name' ? 'text-blue-600' : '' }}"
                                            title="Cognome A-Z">
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                            </th>

                            {{-- Livello --}}
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'level', 'direction' => request('sort') === 'level' && request('direction') === 'asc' ? 'desc' : 'asc']) }}"
                                    class="flex items-center space-x-1 hover:text-gray-700">
                                    <span>🏆 Livello</span>
                                    @if (request('sort') === 'level')
                                        <span
                                            class="text-blue-600">{{ request('direction') === 'asc' ? '⬆️' : '⬇️' }}</span>
                                    @endif
                                </a>
                            </th>

                            {{-- Zona --}}
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'zone_name', 'direction' => request('sort') === 'zone_name' && request('direction') === 'asc' ? 'desc' : 'asc']) }}"
                                    class="flex items-center space-x-1 hover:text-gray-700">
                                    <span>📍 Zona</span>
                                    @if (request('sort') === 'zone_name')
                                        <span
                                            class="text-blue-600">{{ request('direction') === 'asc' ? '⬆️' : '⬇️' }}</span>
                                    @endif
                                </a>
                            </th>

                            {{-- Stato --}}
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'is_active', 'direction' => request('sort') === 'is_active' && request('direction') === 'asc' ? 'desc' : 'asc']) }}"
                                    class="flex items-center space-x-1 hover:text-gray-700">
                                    <span>⚡ Stato</span>
                                    @if (request('sort') === 'is_active')
                                        <span
                                            class="text-blue-600">{{ request('direction') === 'asc' ? '⬆️' : '⬇️' }}</span>
                                    @endif
                                </a>
                            </th>

                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Azioni
                            </th>
                        </tr>
                    </thead>

                    {{-- Body tabella più compatto --}}
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($referees as $referee)
                            <tr class="hover:bg-gray-50">
                                {{-- Info arbitro (più compatta) --}}
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-8 w-8">
                                            <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                                                <span class="text-xs font-medium text-blue-600">
                                                    {{ strtoupper(substr($referee->name, 0, 2)) }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-medium text-gray-900">{{ $referee->name }}</div>
                                            <div class="text-xs text-gray-500">{{ $referee->email }}</div>
                                            @if ($referee->referee_code)
                                                <div class="text-xs text-gray-400">{{ $referee->referee_code }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                {{-- Livello (badge più piccolo) --}}
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                    @switch(request('sort') === 'level' ? normalize_referee_level($referee->level) : $referee->level)
                        @case('Aspirante') bg-gray-100 text-gray-800 @break
                        @case('1_livello') bg-green-100 text-green-800 @break
                        @case('Regionale') bg-blue-100 text-blue-800 @break
                        @case('Nazionale') bg-purple-100 text-purple-800 @break
                        @case('Internazionale') bg-yellow-100 text-yellow-800 @break
                        @default bg-gray-100 text-gray-800
                    @endswitch">
                                        {{ referee_level_label($referee->level) }}
                                    </span>
                                </td>

                                {{-- Zona --}}
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                    {{ $referee->zone->name ?? '❌ N/A' }}
                                </td>

                                {{-- Stato (più compatto) --}}
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if ($referee->is_active)
                                        <span
                                            class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            ✅ Attivo
                                        </span>
                                    @else
                                        <span
                                            class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            ❌ Inattivo
                                        </span>
                                    @endif
                                </td>

                                {{-- Azioni (più compatte) --}}
                                <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-1">
                                        <a href="{{ route('admin.referees.show', $referee) }}"
                                            class="text-blue-600 hover:text-blue-900 px-2 py-1 rounded hover:bg-blue-50"
                                            title="Visualizza">
                                            👁️
                                        </a>
                                        <a href="{{ route('admin.referees.edit', $referee) }}"
                                            class="text-green-600 hover:text-green-900 px-2 py-1 rounded hover:bg-green-50"
                                            title="Modifica">
                                            ✏️
                                        </a>
                                        @if ($referee->assignments_count == 0)
                                            <form method="POST" action="{{ route('admin.referees.destroy', $referee) }}"
                                                class="inline"
                                                onsubmit="return confirm('Sei sicuro di voler eliminare questo arbitro?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="text-red-600 hover:text-red-900 px-2 py-1 rounded hover:bg-red-50"
                                                    title="Elimina">
                                                    🗑️
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                {{-- ✅ PAGINAZIONE --}}
                @if ($referees->hasPages())
                    <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                        <div class="flex items-center justify-between">
                            <div class="flex-1 flex justify-between sm:hidden">
                                {{-- Mobile pagination --}}
                                @if ($referees->onFirstPage())
                                    <span
                                        class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 cursor-default rounded-md">
                                        Precedente
                                    </span>
                                @else
                                    <a href="{{ $referees->previousPageUrl() }}"
                                        class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                        Precedente
                                    </a>
                                @endif

                                @if ($referees->hasMorePages())
                                    <a href="{{ $referees->nextPageUrl() }}"
                                        class="relative ml-3 inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                        Successivo
                                    </a>
                                @else
                                    <span
                                        class="relative ml-3 inline-flex items-center px-4 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 cursor-default rounded-md">
                                        Successivo
                                    </span>
                                @endif
                            </div>

                            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm text-gray-700">
                                        Mostra dal <span class="font-medium">{{ $referees->firstItem() }}</span>
                                        al <span class="font-medium">{{ $referees->lastItem() }}</span>
                                        di <span class="font-medium">{{ $referees->total() }}</span> arbitri
                                    </p>
                                </div>
                                <div>
                                    {{ $referees->links() }}
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            @else
                {{-- Messaggio quando non ci sono arbitri --}}
                <div class="text-center py-12">
                    <div class="text-gray-500 mb-4">
                        <svg class="mx-auto h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Nessun arbitro trovato</h3>
                    <p class="text-gray-500 mb-4">Non ci sono arbitri che corrispondono ai filtri selezionati.</p>
                    <a href="{{ route('admin.referees.create') }}"
                        class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        Crea il primo arbitro
                    </a>
                </div>
            @endif
        </div>
    </div>
    <script>
        function toggleSortDirection() {
            const directionInput = document.getElementById('direction');
            const button = event.target;

            if (directionInput.value === 'asc') {
                directionInput.value = 'desc';
                button.innerHTML = '⬇️ Z-A';
            } else {
                directionInput.value = 'asc';
                button.innerHTML = '⬆️ A-Z';
            }
        }
    </script>
@endsection

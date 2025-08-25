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
                <div class="grid grid-cols-1 md:grid-cols-{{ $isNationalAdmin ? '5' : '4' }} gap-3 mb-3">
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

                    {{-- Zona - SOLO per CRC e SuperAdmin --}}
                    @if ($isNationalAdmin)
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">🏢 Zona</label>
                            <select name="zone_id"
                                class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Tutte</option>
                                @foreach ($zones as $zone)
                                    <option value="{{ $zone->id }}"
                                        {{ request('zone_id') == $zone->id ? 'selected' : '' }}>
                                        {{ $zone->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

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

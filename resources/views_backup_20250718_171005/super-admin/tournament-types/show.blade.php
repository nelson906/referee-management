@extends('layouts.super-admin')

@section('title', 'Tipo: ' . $tournamentType->name)

@section('content')
<div class="container mx-auto px-4 py-8 max-w-6xl">
    {{-- Header --}}
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-4 h-4 rounded-full mr-4"
                     style="background-color: {{ $tournamentType->calendar_color }}"></div>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">{{ $tournamentType->name }}</h1>
                    <p class="mt-1 text-gray-600">Codice: {{ $tournamentType->code }}</p>
                </div>
            </div>
            <div class="flex space-x-4">
                <a href="{{ route('super-admin.tournament-types.edit', $tournamentType) }}"
                   class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition duration-200 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Modifica
                </a>
                <a href="{{ route('super-admin.tournament-types.index') }}"
                   class="text-gray-600 hover:text-gray-900 flex items-center px-4 py-2">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Torna all'elenco
                </a>
            </div>
        </div>
    </div>

    {{-- Status Badge --}}
    <div class="mb-6">
        <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full
            {{ $tournamentType->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
            {{ $tournamentType->is_active ? 'Attiva' : 'Non Attiva' }}
        </span>
        <span class="ml-2 px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full
            {{ $tournamentType->is_national ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800' }}">
            {{ $tournamentType->is_national ? 'Nazionale' : 'Zonale' }}
        </span>
    </div>

    {{-- Info Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        {{-- Dettagli Categoria --}}
        <div class="bg-white shadow rounded-lg p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">Dettagli Categoria</h2>
            <dl class="space-y-3">
                @if($tournamentType->description)
                <div>
                    <dt class="text-sm font-medium text-gray-500">Descrizione</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $tournamentType->description }}</dd>
                </div>
                @endif

                <div>
                    <dt class="text-sm font-medium text-gray-500">Livello Categoria</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ ucfirst($tournamentType->level) }}</dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500">Ordine Visualizzazione</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $tournamentType->sort_order }}</dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500">Visibilità</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        @if($tournamentType->is_national)
                            Tutte le zone
                        @else
                            @if(is_array($tournamentType->visibility_zones) && count($tournamentType->visibility_zones) > 0)
                                {{ count($tournamentType->visibility_zones) }} zone selezionate
                            @else
                                Solo zona proprietaria
                            @endif
                        @endif
                    </dd>
                </div>
            </dl>
        </div>

        {{-- Configurazione Arbitri --}}
        <div class="bg-white shadow rounded-lg p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">Configurazione Arbitri</h2>
            <dl class="space-y-3">
                <div>
                    <dt class="text-sm font-medium text-gray-500">Livello Arbitro Minimo</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        {{ App\Models\TournamentType::REFEREE_LEVELS[$tournamentType->required_referee_level] ?? 'Non specificato' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500">Numero Arbitri</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        Minimo: {{ $tournamentType->min_referees }} -
                        Massimo: {{ $tournamentType->max_referees }}
                    </dd>
                </div>

                @if($tournamentType->special_requirements)
                <div>
                    <dt class="text-sm font-medium text-gray-500">Requisiti Speciali</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $tournamentType->special_requirements }}</dd>
                </div>
                @endif
            </dl>
        </div>
    </div>

    {{-- Statistiche --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-lg shadow p-6 text-center">
            <div class="text-3xl font-bold text-indigo-600">{{ $tournamentType->tournaments_count }}</div>
            <div class="text-sm text-gray-600 mt-1">Tornei Totali</div>
        </div>
        <div class="bg-white rounded-lg shadow p-6 text-center">
            <div class="text-3xl font-bold text-green-600">
                {{ $tournamentType->tournaments()->where('status', 'open')->count() }}
            </div>
            <div class="text-sm text-gray-600 mt-1">Tornei Aperti</div>
        </div>
        <div class="bg-white rounded-lg shadow p-6 text-center">
            <div class="text-3xl font-bold text-blue-600">
                {{ $tournamentType->tournaments()->where('status', 'assigned')->count() }}
            </div>
            <div class="text-sm text-gray-600 mt-1">Con Arbitri Assegnati</div>
        </div>
        <div class="bg-white rounded-lg shadow p-6 text-center">
            <div class="text-3xl font-bold text-gray-600">
                {{ $tournamentType->tournaments()->where('status', 'completed')->count() }}
            </div>
            <div class="text-sm text-gray-600 mt-1">Completati</div>
        </div>
    </div>

    {{-- Tornei Recenti --}}
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-900">Tornei Recenti</h2>
        </div>

        @if($recentTournaments->count() > 0)
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Torneo
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Circolo
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Zona
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Date
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Stato
                        </th>
                        <th scope="col" class="relative px-6 py-3">
                            <span class="sr-only">Azioni</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($recentTournaments as $tournament)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">
                                {{ $tournament->name }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $tournament->club->name ?? 'N/A' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $tournament->zone->name ?? 'N/A' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ \Carbon\Carbon::parse($tournament->start_date)->format('d/m/Y') }} -
                            {{ \Carbon\Carbon::parse($tournament->end_date)->format('d/m/Y') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                @switch($tournament->status)
                                    @case('open') bg-green-100 text-green-800 @break
                                    @case('closed') bg-yellow-100 text-yellow-800 @break
                                    @case('assigned') bg-blue-100 text-blue-800 @break
                                    @case('completed') bg-gray-100 text-gray-800 @break
                                    @default bg-gray-100 text-gray-800
                                @endswitch">
                                {{ ucfirst($tournament->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <a href="{{ route('tournaments.show', $tournament) }}"
                               class="text-indigo-600 hover:text-indigo-900">
                                Visualizza
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($tournamentType->tournaments_count > 10)
        <div class="bg-gray-50 px-6 py-3 text-center">
            <a href="{{ route('admin.tournaments.index', ['tournament_type_id' => $tournamentType->id]) }}"
               class="text-sm text-indigo-600 hover:text-indigo-900">
                Vedi tutti i {{ $tournamentType->tournaments_count }} tornei →
            </a>
        </div>
        @endif

        @else
        <div class="px-6 py-12 text-center text-gray-500">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
            </svg>
            <p class="mt-2">Nessun torneo trovato per questa categoria</p>
        </div>
        @endif
    </div>
</div>
@endsection

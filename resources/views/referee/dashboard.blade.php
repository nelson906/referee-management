@extends('layouts.admin')

@section('title', 'Dashboard Admin')

@section('content')
<div class="container mx-auto px-4 py-8">
    {{-- Header --}}
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Dashboard Amministratore</h1>
        <p class="mt-2 text-gray-600">
            Benvenuto {{ auth()->user()->name }} -
            @if($isNationalAdmin)
                Amministratore CRC (Comitato Regionale Campania)
            @else
                Amministratore Zona {{ auth()->user()->zone->name }}
            @endif
        </p>
    </div>

    {{-- Alerts --}}
    @if(count($alerts) > 0)
        <div class="mb-6 space-y-3">
            @foreach($alerts as $alert)
                <div class="bg-{{ $alert['type'] === 'warning' ? 'yellow' : 'blue' }}-50 border-l-4 border-{{ $alert['type'] === 'warning' ? 'yellow' : 'blue' }}-400 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-{{ $alert['type'] === 'warning' ? 'yellow' : 'blue' }}-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-{{ $alert['type'] === 'warning' ? 'yellow' : 'blue' }}-700">
                                {{ $alert['message'] }}
                                @if(isset($alert['link']))
                                    <a href="{{ $alert['link'] }}" class="font-medium underline">Visualizza →</a>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Statistics Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        {{-- Total Tournaments --}}
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-indigo-500 rounded-md p-3">
                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Tornei Totali</dt>
                        <dd class="flex items-baseline">
                            <div class="text-2xl font-semibold text-gray-900">{{ $stats['total_tournaments'] }}</div>
                            <div class="ml-2 flex items-baseline text-sm font-semibold text-green-600">
                                {{ $stats['active_tournaments'] }} attivi
                            </div>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        {{-- Total Referees --}}
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Arbitri Totali</dt>
                        <dd class="flex items-baseline">
                            <div class="text-2xl font-semibold text-gray-900">{{ $stats['total_referees'] }}</div>
                            <div class="ml-2 flex items-baseline text-sm font-semibold text-green-600">
                                {{ $stats['active_referees'] }} attivi
                            </div>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        {{-- Total Assignments --}}
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Assegnazioni</dt>
                        <dd class="flex items-baseline">
                            <div class="text-2xl font-semibold text-gray-900">{{ $stats['total_assignments'] }}</div>
                            @if($stats['pending_confirmations'] > 0)
                                <div class="ml-2 flex items-baseline text-sm font-semibold text-yellow-600">
                                    {{ $stats['pending_confirmations'] }} da confermare
                                </div>
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        {{-- Zones (for national admin) --}}
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-purple-500 rounded-md p-3">
                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">
                            @if($isNationalAdmin)
                                Zone Gestite
                            @else
                                Tornei Futuri
                            @endif
                        </dt>
                        <dd class="flex items-baseline">
                            <div class="text-2xl font-semibold text-gray-900">
                                @if($isNationalAdmin)
                                    {{ $stats['zones_count'] }}
                                @else
                                    {{ $stats['upcoming_tournaments'] }}
                                @endif
                            </div>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left Column --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Tournaments Needing Referees --}}
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">Tornei che Necessitano Arbitri</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Torneo
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Date
                                </th>
                                <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Arbitri
                                </th>
                                <th scope="col" class="relative px-6 py-3">
                                    <span class="sr-only">Azioni</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($tournamentsNeedingReferees as $tournament)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $tournament->name }}</div>
                                    <div class="text-sm text-gray-500">{{ $tournament->club->name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $tournament->start_date->format('d/m/Y') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="text-sm {{ $tournament->assignments()->count() < $tournament->required_referees ? 'text-red-600 font-semibold' : 'text-gray-900' }}">
                                        {{ $tournament->assignments()->count() }} / {{ $tournament->required_referees }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="{{ route('admin.tournaments.show', $tournament) }}" class="text-indigo-600 hover:text-indigo-900">
                                        Gestisci
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">
                                    Tutti i tornei hanno arbitri sufficienti
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Recent Assignments --}}
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h2 class="text-lg font-medium text-gray-900">Assegnazioni Recenti</h2>
                    <a href="{{ route('admin.assignments.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">
                        Vedi tutte →
                    </a>
                </div>
                <div class="divide-y divide-gray-200">
                    @forelse($recentAssignments as $assignment)
                    <div class="px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    {{ $assignment->user->name }}
                                    <span class="text-gray-500">→</span>
                                    {{ $assignment->tournament->name }}
                                </p>
                                <p class="text-sm text-gray-500">
                                    {{ $assignment->tournament->club->name }} -
                                    {{ $assignment->tournament->start_date->format('d/m/Y') }}
                                </p>
                            </div>
                            <div>
                                @if($assignment->is_confirmed)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Confermato
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                        Da confermare
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="px-6 py-4 text-center text-sm text-gray-500">
                        Nessuna assegnazione recente
                    </div>
                    @endforelse
                </div>
            </div>

            {{-- Monthly Trend Chart --}}
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Andamento Tornei (ultimi 6 mesi)</h2>
                <canvas id="monthlyChart" height="100"></canvas>
            </div>
        </div>

        {{-- Right Column --}}
        <div class="space-y-6">
            {{-- Deadlines Approaching --}}
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">Scadenze Imminenti</h2>
                </div>
                <div class="divide-y divide-gray-200">
                    @forelse($deadlinesApproaching as $tournament)
                    <div class="px-6 py-4">
                        <p class="text-sm font-medium text-gray-900">{{ $tournament->name }}</p>
                        <p class="text-sm text-gray-500">
                            Scadenza: {{ $tournament->availability_deadline->format('d/m/Y') }}
                            <span class="text-xs {{ $tournament->days_until_deadline <= 3 ? 'text-red-600 font-semibold' : '' }}">
                                ({{ $tournament->days_until_deadline }} giorni)
                            </span>
                        </p>
                    </div>
                    @empty
                    <div class="px-6 py-4 text-center text-sm text-gray-500">
                        Nessuna scadenza nei prossimi 7 giorni
                    </div>
                    @endforelse
                </div>
            </div>

            {{-- Referees by Level --}}
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Arbitri per Livello</h2>
                <div class="space-y-3">
                    @foreach(['aspirante' => 'Aspirante', 'primo_livello' => '1° Livello', 'regionale' => 'Regionale', 'nazionale' => 'Nazionale', 'internazionale' => 'Internazionale'] as $level => $label)
                        @if(isset($refereesByLevel[$level]))
                        <div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">{{ $label }}</span>
                                <span class="font-medium">{{ $refereesByLevel[$level] }}</span>
                            </div>
                            <div class="mt-1 bg-gray-200 rounded-full h-2">
                                <div class="bg-indigo-600 h-2 rounded-full"
                                     style="width: {{ ($refereesByLevel[$level] / max($refereesByLevel)) * 100 }}%"></div>
                            </div>
                        </div>
                        @endif
                    @endforeach
                </div>
            </div>

            {{-- Top Referees --}}
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">Top Arbitri {{ date('Y') }}</h2>
                </div>
                <div class="divide-y divide-gray-200">
                    @forelse($topReferees as $index => $referee)
                    <div class="px-6 py-4 flex items-center justify-between">
                        <div class="flex items-center">
                            <span class="text-lg font-bold text-gray-400 mr-3">{{ $index + 1 }}</span>
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $referee->name }}</p>
                                <p class="text-xs text-gray-500">{{ ucfirst($referee->level) }}</p>
                            </div>
                        </div>
                        <span class="text-sm font-semibold text-indigo-600">
                            {{ $referee->assignments_count }} tornei
                        </span>
                    </div>
                    @empty
                    <div class="px-6 py-4 text-center text-sm text-gray-500">
                        Nessun dato disponibile
                    </div>
                    @endforelse
                </div>
            </div>

            {{-- Zone Statistics (for national admin) --}}
            @if($isNationalAdmin && count($zoneStats) > 0)
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">Statistiche per Zona</h2>
                </div>
                <div class="divide-y divide-gray-200">
                    @foreach($zoneStats as $zoneName => $stats)
                    <div class="px-6 py-4">
                        <p class="text-sm font-medium text-gray-900 mb-2">{{ $zoneName }}</p>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div>
                                <span class="text-gray-500">Tornei:</span>
                                <span class="font-medium">{{ $stats['tournaments'] }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500">Attivi:</span>
                                <span class="font-medium">{{ $stats['active_tournaments'] }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500">Arbitri:</span>
                                <span class="font-medium">{{ $stats['referees'] }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500">Circoli:</span>
                                <span class="font-medium">{{ $stats['clubs'] }}</span>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Monthly Trend Chart
const ctx = document.getElementById('monthlyChart').getContext('2d');
const monthlyData = @json($monthlyTrend);
const labels = Object.keys(monthlyData).map(month => {
    const [year, monthNum] = month.split('-');
    const date = new Date(year, monthNum - 1);
    return date.toLocaleDateString('it-IT', { month: 'short', year: 'numeric' });
});

new Chart(ctx, {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: 'Tornei',
            data: Object.values(monthlyData),
            borderColor: 'rgb(79, 70, 229)',
            backgroundColor: 'rgba(79, 70, 229, 0.1)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});
</script>
@endpush
@endsection

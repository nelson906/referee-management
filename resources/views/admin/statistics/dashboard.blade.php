@extends('layouts.admin')

@section('header')
    <div class="flex justify-between items-center">
        <h1 class="text-3xl font-bold text-gray-900">📊 Dashboard Statistiche</h1>
        <div class="flex space-x-3">
            <a href="{{ route('admin.statistics.export') }}"
               class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200">
                📥 Export CSV
            </a>
        </div>
    </div>
@endsection

@section('content')
<div class="space-y-6">

    {{-- Filtri Periodo --}}
    <div class="bg-white rounded-lg shadow p-6">
        <form method="GET" class="flex flex-wrap gap-4 items-end">
            <div>
                <label for="period" class="block text-sm font-medium text-gray-700 mb-1">
                    📅 Periodo
                </label>
                <select name="period" id="period"
                        class="block rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="7" {{ request('period', '30') == '7' ? 'selected' : '' }}>Ultimi 7 giorni</option>
                    <option value="30" {{ request('period', '30') == '30' ? 'selected' : '' }}>Ultimi 30 giorni</option>
                    <option value="90" {{ request('period', '30') == '90' ? 'selected' : '' }}>Ultimi 3 mesi</option>
                    <option value="365" {{ request('period', '30') == '365' ? 'selected' : '' }}>Ultimo anno</option>
                </select>
            </div>
            <div>
                <label for="zone_filter" class="block text-sm font-medium text-gray-700 mb-1">
                    🌍 Zona
                </label>
                <select name="zone_filter" id="zone_filter"
                        class="block rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Tutte le zone</option>
                    @foreach($zones as $zone)
                        <option value="{{ $zone->id }}"
                                {{ request('zone_filter') == $zone->id ? 'selected' : '' }}>
                            {{ $zone->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition duration-200">
                Aggiorna
            </button>
        </form>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

        {{-- Tornei Totali --}}
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Tornei Totali</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $stats['tournaments_total'] ?? 0 }}</p>
                </div>
                <div class="p-3 bg-blue-100 rounded-full">
                    🏆
                </div>
            </div>
            <div class="mt-2 flex items-center text-sm">
                <span class="text-green-600 font-medium">
                    +{{ $stats['tournaments_increase'] ?? 0 }}%
                </span>
                <span class="text-gray-500 ml-1">vs mese scorso</span>
            </div>
        </div>

        {{-- Arbitri Attivi --}}
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Arbitri Attivi</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $stats['referees_active'] ?? 0 }}</p>
                </div>
                <div class="p-3 bg-green-100 rounded-full">
                    👨‍💼
                </div>
            </div>
            <div class="mt-2 flex items-center text-sm">
                <span class="text-gray-600">
                    {{ $stats['referees_total'] ?? 0 }} totali
                </span>
            </div>
        </div>

        {{-- Disponibilità Media --}}
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Disponibilità Media</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $stats['availability_avg'] ?? 0 }}%</p>
                </div>
                <div class="p-3 bg-yellow-100 rounded-full">
                    📝
                </div>
            </div>
            <div class="mt-2 flex items-center text-sm">
                <span class="text-gray-600">
                    {{ $stats['availability_declarations'] ?? 0 }} dichiarazioni
                </span>
            </div>
        </div>

        {{-- Assegnazioni Completate --}}
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Assegnazioni</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $stats['assignments_completed'] ?? 0 }}</p>
                </div>
                <div class="p-3 bg-purple-100 rounded-full">
                    ✅
                </div>
            </div>
            <div class="mt-2 flex items-center text-sm">
                <span class="text-green-600 font-medium">
                    {{ $stats['assignments_completion_rate'] ?? 0 }}%
                </span>
                <span class="text-gray-500 ml-1">tasso completamento</span>
            </div>
        </div>
    </div>

    {{-- Charts Row --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Grafico Tornei per Mese --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                📈 Tornei per Mese
            </h3>
            <div class="h-64">
                <canvas id="tournamentsChart" width="400" height="200"></canvas>
            </div>
        </div>

        {{-- Grafico Disponibilità per Zona --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                🌍 Disponibilità per Zona
            </h3>
            <div class="h-64">
                <canvas id="availabilityChart" width="400" height="200"></canvas>
            </div>
        </div>
    </div>

    {{-- Statistiche Dettagliate --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Top Zone --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                🏆 Top Zone per Tornei
            </h3>
            <div class="space-y-3">
                @foreach($stats['top_zones'] ?? [] as $zone)
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-900">
                            {{ $zone['name'] }}
                        </span>
                        <div class="flex items-center space-x-2">
                            <div class="w-16 bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full"
                                     style="width: {{ $zone['percentage'] }}%"></div>
                            </div>
                            <span class="text-sm text-gray-600">{{ $zone['tournaments'] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Top Arbitri --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                ⭐ Top Arbitri per Assegnazioni
            </h3>
            <div class="space-y-3">
                @foreach($stats['top_referees'] ?? [] as $referee)
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <div class="h-8 w-8 bg-gray-200 rounded-full flex items-center justify-center text-xs">
                                {{ substr($referee['name'], 0, 2) }}
                            </div>
                            <span class="text-sm font-medium text-gray-900">
                                {{ $referee['name'] }}
                            </span>
                        </div>
                        <span class="text-sm text-gray-600 font-medium">
                            {{ $referee['assignments'] }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Statistiche Tempo Reale --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                ⚡ Tempo Reale
            </h3>
            <div class="space-y-4">
                <div>
                    <p class="text-sm text-gray-600">Tornei questa settimana</p>
                    <p class="text-2xl font-bold text-blue-600">
                        {{ $stats['tournaments_this_week'] ?? 0 }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Dichiarazioni oggi</p>
                    <p class="text-2xl font-bold text-green-600">
                        {{ $stats['declarations_today'] ?? 0 }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Notifiche inviate</p>
                    <p class="text-2xl font-bold text-purple-600">
                        {{ $stats['notifications_sent'] ?? 0 }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            🚀 Azioni Rapide
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <a href="{{ route('admin.statistics.disponibilita') }}"
               class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                <div class="text-center">
                    <div class="text-2xl mb-2">📊</div>
                    <h4 class="font-medium text-gray-900">Report Disponibilità</h4>
                    <p class="text-sm text-gray-600">Analisi dettagliata disponibilità arbitri</p>
                </div>
            </a>
            <a href="{{ route('admin.statistics.assegnazioni') }}"
               class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                <div class="text-center">
                    <div class="text-2xl mb-2">📋</div>
                    <h4 class="font-medium text-gray-900">Report Assegnazioni</h4>
                    <p class="text-sm text-gray-600">Statistiche su assegnazioni e performance</p>
                </div>
            </a>
            <a href="{{ route('admin.statistics.tornei') }}"
               class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                <div class="text-center">
                    <div class="text-2xl mb-2">🏆</div>
                    <h4 class="font-medium text-gray-900">Analisi Tornei</h4>
                    <p class="text-sm text-gray-600">Trend e statistiche dei tornei</p>
                </div>
            </a>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {

    // Grafico Tornei per Mese
    const tournamentsCtx = document.getElementById('tournamentsChart').getContext('2d');
    new Chart(tournamentsCtx, {
        type: 'line',
        data: {
            labels: @json($charts['tournaments']['labels'] ?? []),
            datasets: [{
                label: 'Tornei',
                data: @json($charts['tournaments']['data'] ?? []),
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Grafico Disponibilità per Zona
    const availabilityCtx = document.getElementById('availabilityChart').getContext('2d');
    new Chart(availabilityCtx, {
        type: 'doughnut',
        data: {
            labels: @json($charts['availability']['labels'] ?? []),
            datasets: [{
                data: @json($charts['availability']['data'] ?? []),
                backgroundColor: [
                    '#10B981',
                    '#F59E0B',
                    '#EF4444',
                    '#8B5CF6',
                    '#06B6D4',
                    '#F97316'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
});
</script>
@endpush

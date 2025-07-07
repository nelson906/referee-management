@extends('layouts.super-admin')

@section('title', 'Dashboard Analytics')

@section('content')
<div class="container mx-auto px-4 py-8">
    {{-- Header --}}
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Dashboard Analytics</h1>
            <p class="mt-2 text-gray-600">Panoramica completa delle statistiche e performance del sistema</p>
        </div>
        <div class="flex space-x-4">
            <select id="period-selector" onchange="changePeriod()"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <option value="7" {{ $period == '7' ? 'selected' : '' }}>Ultimi 7 giorni</option>
                <option value="30" {{ $period == '30' ? 'selected' : '' }}>Ultimi 30 giorni</option>
                <option value="90" {{ $period == '90' ? 'selected' : '' }}>Ultimi 90 giorni</option>
                <option value="365" {{ $period == '365' ? 'selected' : '' }}>Ultimo anno</option>
            </select>
            <button onclick="exportData()"
                    class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-200 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Esporta
            </button>
            <button onclick="refreshData()"
                    class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition duration-200 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Aggiorna
            </button>
        </div>
    </div>

    {{-- System Health Alert --}}
    @if($systemHealth['overall']['status'] !== 'healthy')
    <div class="bg-{{ $systemHealth['overall']['status'] === 'error' ? 'red' : 'yellow' }}-50 border-l-4 border-{{ $systemHealth['overall']['status'] === 'error' ? 'red' : 'yellow' }}-400 p-4 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-{{ $systemHealth['overall']['status'] === 'error' ? 'red' : 'yellow' }}-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-{{ $systemHealth['overall']['status'] === 'error' ? 'red' : 'yellow' }}-700">
                    <strong>Attenzione:</strong> {{ $systemHealth['overall']['message'] }}.
                    Controlla lo stato del sistema più in basso.
                </p>
            </div>
        </div>
    </div>
    @endif

    {{-- Main Statistics Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        {{-- Total Users --}}
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Utenti Totali</p>
                    <p class="text-3xl font-bold text-gray-900">{{ number_format($stats['total_users']) }}</p>
                </div>
                <div class="p-2 bg-blue-100 rounded-lg">
                    <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4 flex items-center">
                <span class="flex items-center text-sm font-medium {{ $growth['users']['growth'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    @if($growth['users']['growth'] >= 0)
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17l9.2-9.2M17 17V7m0 10H7"></path>
                        </svg>
                        +{{ number_format($growth['users']['growth'], 1) }}%
                    @else
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 7l-9.2 9.2M7 7v10m0-10h10"></path>
                        </svg>
                        {{ number_format($growth['users']['growth'], 1) }}%
                    @endif
                </span>
                <span class="ml-2 text-sm text-gray-500">vs periodo precedente</span>
            </div>
        </div>

        {{-- Active Tournaments --}}
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Tornei Attivi</p>
                    <p class="text-3xl font-bold text-gray-900">{{ number_format($stats['active_tournaments']) }}</p>
                </div>
                <div class="p-2 bg-green-100 rounded-lg">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4 flex items-center">
                <span class="flex items-center text-sm font-medium {{ $growth['tournaments']['growth'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    @if($growth['tournaments']['growth'] >= 0)
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17l9.2-9.2M17 17V7m0 10H7"></path>
                        </svg>
                        +{{ number_format($growth['tournaments']['growth'], 1) }}%
                    @else
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 7l-9.2 9.2M7 7v10m0-10h10"></path>
                        </svg>
                        {{ number_format($growth['tournaments']['growth'], 1) }}%
                    @endif
                </span>
                <span class="ml-2 text-sm text-gray-500">nuovi tornei</span>
            </div>
        </div>

        {{-- Active Referees --}}
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Arbitri Attivi</p>
                    <p class="text-3xl font-bold text-gray-900">{{ number_format($stats['active_referees']) }}</p>
                </div>
                <div class="p-2 bg-purple-100 rounded-lg">
                    <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4 flex items-center">
                <span class="text-sm text-gray-500">
                    {{ number_format(($stats['active_referees'] / max($stats['total_referees'], 1)) * 100, 1) }}% del totale
                </span>
            </div>
        </div>

        {{-- Assignment Rate --}}
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Tasso Assegnazioni</p>
                    <p class="text-3xl font-bold text-gray-900">
                        {{ number_format(($stats['accepted_assignments'] / max($stats['total_assignments'], 1)) * 100, 1) }}%
                    </p>
                </div>
                <div class="p-2 bg-yellow-100 rounded-lg">
                    <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4 flex items-center">
                <span class="flex items-center text-sm font-medium {{ $growth['assignments']['growth'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    @if($growth['assignments']['growth'] >= 0)
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17l9.2-9.2M17 17V7m0 10H7"></path>
                        </svg>
                        +{{ number_format($growth['assignments']['growth'], 1) }}%
                    @else
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 7l-9.2 9.2M7 7v10m0-10h10"></path>
                        </svg>
                        {{ number_format($growth['assignments']['growth'], 1) }}%
                    @endif
                </span>
                <span class="ml-2 text-sm text-gray-500">assegnazioni</span>
            </div>
        </div>
    </div>

    {{-- Charts Row --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        {{-- Activity Trends Chart --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Tendenze Attività</h3>
            <div class="h-64">
                <canvas id="activityChart"></canvas>
            </div>
        </div>

        {{-- Zone Performance Chart --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Performance Zone (Top 10)</h3>
            <div class="h-64">
                <canvas id="zoneChart"></canvas>
            </div>
        </div>
    </div>

    {{-- Data Tables Row --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        {{-- Zone Performance Table --}}
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Performance Zone</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Zona
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Tornei
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Utenti
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Clubs
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($zonePerformance->take(10) as $zone)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div>
                                    <div class="text-sm font-medium text-gray-900">{{ $zone['name'] }}</div>
                                    <div class="text-sm text-gray-500">{{ $zone['region'] }}</div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $zone['tournaments_count'] }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $zone['users_count'] }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $zone['clubs_count'] }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Category Usage Table --}}
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Utilizzo Categorie</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Categoria
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Tornei
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Tipo
                            </th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Stato
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($categoryUsage->take(10) as $category)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div>
                                    <div class="text-sm font-medium text-gray-900">{{ $category['name'] }}</div>
                                    <div class="text-sm text-gray-500">{{ $category['code'] }}</div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $category['tournaments_count'] }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                    {{ $category['is_national'] ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' }}">
                                    {{ $category['is_national'] ? 'Nazionale' : 'Zonale' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                    {{ $category['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $category['is_active'] ? 'Attiva' : 'Inattiva' }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- System Health and Recent Activity --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- System Health --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Stato Sistema</h3>

            {{-- Overall Health --}}
            <div class="mb-4 p-4 rounded-lg bg-{{ $systemHealth['overall']['status'] === 'healthy' ? 'green' : ($systemHealth['overall']['status'] === 'warning' ? 'yellow' : 'red') }}-50">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        @if($systemHealth['overall']['status'] === 'healthy')
                            <svg class="w-5 h-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                        @else
                            <svg class="w-5 h-5 text-{{ $systemHealth['overall']['status'] === 'warning' ? 'yellow' : 'red' }}-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                        @endif
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-{{ $systemHealth['overall']['status'] === 'healthy' ? 'green' : ($systemHealth['overall']['status'] === 'warning' ? 'yellow' : 'red') }}-800">
                            {{ $systemHealth['overall']['message'] }}
                        </p>
                        <p class="text-xs text-{{ $systemHealth['overall']['status'] === 'healthy' ? 'green' : ($systemHealth['overall']['status'] === 'warning' ? 'yellow' : 'red') }}-600">
                            Score: {{ number_format($systemHealth['overall']['score'], 1) }}/100
                        </p>
                    </div>
                </div>
            </div>

            {{-- Individual Health Metrics --}}
            <div class="space-y-3">
                @foreach(['database', 'user_activity', 'tournament_activity', 'assignment_rate'] as $metric)
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-3 h-3 rounded-full mr-3 bg-{{ $systemHealth[$metric]['status'] === 'healthy' ? 'green' : ($systemHealth[$metric]['status'] === 'warning' ? 'yellow' : 'red') }}-400"></div>
                        <span class="text-sm text-gray-700">
                            @switch($metric)
                                @case('database') Database @break
                                @case('user_activity') Attività Utenti @break
                                @case('tournament_activity') Attività Tornei @break
                                @case('assignment_rate') Tasso Assegnazioni @break
                            @endswitch
                        </span>
                    </div>
                    <span class="text-xs text-gray-500">{{ number_format($systemHealth[$metric]['score'], 1) }}%</span>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Recent Activity --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Attività Recenti</h3>
                </div>
                <div class="max-h-96 overflow-y-auto">
                    <div class="px-6 py-4 space-y-4">
                        @foreach($recentActivity as $activity)
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 rounded-full bg-{{ $activity['color'] }}-100 flex items-center justify-center">
                                    @switch($activity['icon'])
                                        @case('user-plus')
                                            <svg class="w-4 h-4 text-{{ $activity['color'] }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                            </svg>
                                            @break
                                        @case('calendar')
                                            <svg class="w-4 h-4 text-{{ $activity['color'] }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                            </svg>
                                            @break
                                        @case('check-circle')
                                            <svg class="w-4 h-4 text-{{ $activity['color'] }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            @break
                                        @default
                                            <svg class="w-4 h-4 text-{{ $activity['color'] }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                    @endswitch
                                </div>
                            </div>
                            <div class="ml-3 flex-1">
                                <p class="text-sm font-medium text-gray-900">{{ $activity['title'] }}</p>
                                <p class="text-sm text-gray-500">{{ $activity['description'] }}</p>
                                <p class="text-xs text-gray-400 mt-1">
                                    {{ \Carbon\Carbon::parse($activity['created_at'])->diffForHumans() }}
                                </p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Activity Trends Chart
const activityCtx = document.getElementById('activityChart').getContext('2d');
const activityChart = new Chart(activityCtx, {
    type: 'line',
    data: {
        labels: @json(array_column($trends, 'date')),
        datasets: [{
            label: 'Utenti',
            data: @json(array_column($trends, 'users')),
            borderColor: 'rgb(59, 130, 246)',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.1
        }, {
            label: 'Tornei',
            data: @json(array_column($trends, 'tournaments')),
            borderColor: 'rgb(16, 185, 129)',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            tension: 0.1
        }, {
            label: 'Assegnazioni',
            data: @json(array_column($trends, 'assignments')),
            borderColor: 'rgb(245, 158, 11)',
            backgroundColor: 'rgba(245, 158, 11, 0.1)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Zone Performance Chart
const zoneCtx = document.getElementById('zoneChart').getContext('2d');
const zoneChart = new Chart(zoneCtx, {
    type: 'bar',
    data: {
        labels: @json($zonePerformance->take(10)->pluck('name')),
        datasets: [{
            label: 'Tornei',
            data: @json($zonePerformance->take(10)->pluck('tournaments_count')),
            backgroundColor: 'rgba(59, 130, 246, 0.8)',
        }, {
            label: 'Utenti',
            data: @json($zonePerformance->take(10)->pluck('users_count')),
            backgroundColor: 'rgba(16, 185, 129, 0.8)',
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Functions
function changePeriod() {
    const period = document.getElementById('period-selector').value;
    window.location.href = `{{ route('reports.dashboard') }}?period=${period}`;
}

function refreshData() {
    window.location.reload();
}

function exportData() {
    const period = document.getElementById('period-selector').value;
    window.location.href = `{{ route('reports.dashboard.export') }}?period=${period}&format=csv`;
}

// Auto refresh every 5 minutes
setInterval(function() {
    refreshData();
}, 300000);
</script>
@endpush
@endsection

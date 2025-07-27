@extends('layouts.admin')

@section('header')
    <div class="flex justify-between items-center">
        <h1 class="text-3xl font-bold text-gray-900">🖥️ Monitoraggio Sistema</h1>
        <div class="flex space-x-3">
            <button onclick="refreshMetrics()"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200">
                🔄 Aggiorna
            </button>
            <a href="{{ route('admin.monitoring.logs') }}"
               class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200">
                📋 Log Sistema
            </a>
        </div>
    </div>
@endsection

@section('content')
<div class="space-y-6" x-data="monitoringDashboard()" x-init="init()">

    {{-- System Health Status --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">

        {{-- Overall Health --}}
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Stato Sistema</p>
                    <p class="text-2xl font-bold"
                       :class="systemHealth.status === 'healthy' ? 'text-green-600' : 'text-red-600'">
                        <span x-text="systemHealth.status === 'healthy' ? 'SANO' : 'PROBLEMI'"></span>
                    </p>
                </div>
                <div class="p-3 rounded-full"
                     :class="systemHealth.status === 'healthy' ? 'bg-green-100' : 'bg-red-100'">
                    <span x-text="systemHealth.status === 'healthy' ? '✅' : '❌'"></span>
                </div>
            </div>
            <div class="mt-2 text-sm text-gray-500">
                Ultimo check: <span x-text="systemHealth.lastCheck"></span>
            </div>
        </div>

        {{-- Database Status --}}
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Database</p>
                    <p class="text-2xl font-bold"
                       :class="database.status === 'connected' ? 'text-green-600' : 'text-red-600'">
                        <span x-text="database.responseTime + 'ms'"></span>
                    </p>
                </div>
                <div class="p-3 rounded-full"
                     :class="database.status === 'connected' ? 'bg-green-100' : 'bg-red-100'">
                    🗄️
                </div>
            </div>
            <div class="mt-2 text-sm text-gray-500">
                <span x-text="database.connections"></span> connessioni attive
            </div>
        </div>

        {{-- Cache Status --}}
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Cache</p>
                    <p class="text-2xl font-bold text-blue-600">
                        <span x-text="cache.hitRate + '%'"></span>
                    </p>
                </div>
                <div class="p-3 bg-blue-100 rounded-full">
                    ⚡
                </div>
            </div>
            <div class="mt-2 text-sm text-gray-500">
                Hit rate: <span x-text="cache.hits"></span>/<span x-text="cache.misses"></span>
            </div>
        </div>

        {{-- Queue Status --}}
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Code di Lavoro</p>
                    <p class="text-2xl font-bold"
                       :class="queue.pending > 100 ? 'text-orange-600' : 'text-green-600'">
                        <span x-text="queue.pending"></span>
                    </p>
                </div>
                <div class="p-3 rounded-full"
                     :class="queue.pending > 100 ? 'bg-orange-100' : 'bg-green-100'">
                    📤
                </div>
            </div>
            <div class="mt-2 text-sm text-gray-500">
                <span x-text="queue.failed"></span> falliti oggi
            </div>
        </div>
    </div>

    {{-- Performance Metrics --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- CPU & Memory --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                🖥️ Risorse Sistema
            </h3>
            <div class="space-y-4">
                <div>
                    <div class="flex justify-between text-sm">
                        <span>CPU Usage</span>
                        <span x-text="performance.cpu + '%'"></span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2 mt-1">
                        <div class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                             :style="'width: ' + performance.cpu + '%'"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm">
                        <span>Memoria</span>
                        <span x-text="performance.memory + '%'"></span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2 mt-1">
                        <div class="bg-green-600 h-2 rounded-full transition-all duration-300"
                             :style="'width: ' + performance.memory + '%'"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm">
                        <span>Disco</span>
                        <span x-text="performance.disk + '%'"></span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2 mt-1">
                        <div class="bg-yellow-600 h-2 rounded-full transition-all duration-300"
                             :style="'width: ' + performance.disk + '%'"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Response Times --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                ⏱️ Tempi di Risposta
            </h3>
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Pagine Web</span>
                    <span class="font-semibold" x-text="responseTimes.web + 'ms'"></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">API</span>
                    <span class="font-semibold" x-text="responseTimes.api + 'ms'"></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Database</span>
                    <span class="font-semibold" x-text="responseTimes.database + 'ms'"></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Email</span>
                    <span class="font-semibold" x-text="responseTimes.email + 'ms'"></span>
                </div>
            </div>
        </div>

        {{-- Error Rates --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                🚨 Errori e Alerts
            </h3>
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Errori HTTP (24h)</span>
                    <span class="font-semibold text-red-600" x-text="errors.http"></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Errori Database</span>
                    <span class="font-semibold text-orange-600" x-text="errors.database"></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Email Fallite</span>
                    <span class="font-semibold text-red-600" x-text="errors.email"></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Job Falliti</span>
                    <span class="font-semibold text-orange-600" x-text="errors.jobs"></span>
                </div>
            </div>
        </div>
    </div>

    {{-- Real-time Charts --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Performance Chart --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                📈 Performance Trend (Ultima Ora)
            </h3>
            <div class="h-64">
                <canvas id="performanceChart" width="400" height="200"></canvas>
            </div>
        </div>

        {{-- Traffic Chart --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                🌐 Traffico Web (Ultima Ora)
            </h3>
            <div class="h-64">
                <canvas id="trafficChart" width="400" height="200"></canvas>
            </div>
        </div>
    </div>

    {{-- Recent Alerts --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            🚨 Alert Recenti
        </h3>
        <div class="space-y-3">
            <template x-for="alert in recentAlerts" :key="alert.id">
                <div class="flex items-center justify-between p-3 rounded-lg"
                     :class="alert.level === 'critical' ? 'bg-red-50 border border-red-200' :
                             alert.level === 'warning' ? 'bg-yellow-50 border border-yellow-200' :
                             'bg-blue-50 border border-blue-200'">
                    <div class="flex items-center space-x-3">
                        <span :class="alert.level === 'critical' ? 'text-red-600' :
                                     alert.level === 'warning' ? 'text-yellow-600' :
                                     'text-blue-600'"
                              x-text="alert.level === 'critical' ? '🔴' :
                                     alert.level === 'warning' ? '🟡' : '🔵'"></span>
                        <div>
                            <p class="font-medium text-gray-900" x-text="alert.message"></p>
                            <p class="text-sm text-gray-500" x-text="alert.timestamp"></p>
                        </div>
                    </div>
                    <span class="px-2 py-1 text-xs font-semibold rounded-full"
                          :class="alert.level === 'critical' ? 'bg-red-100 text-red-800' :
                                 alert.level === 'warning' ? 'bg-yellow-100 text-yellow-800' :
                                 'bg-blue-100 text-blue-800'"
                          x-text="alert.level.toUpperCase()"></span>
                </div>
            </template>
        </div>
    </div>

    {{-- System Actions --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            🔧 Azioni Sistema
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <button onclick="clearCache()"
                    class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                <div class="text-center">
                    <div class="text-2xl mb-2">🧹</div>
                    <h4 class="font-medium text-gray-900">Clear Cache</h4>
                    <p class="text-sm text-gray-600">Svuota cache applicazione</p>
                </div>
            </button>
            <button onclick="optimizeSystem()"
                    class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                <div class="text-center">
                    <div class="text-2xl mb-2">⚡</div>
                    <h4 class="font-medium text-gray-900">Optimize</h4>
                    <p class="text-sm text-gray-600">Ottimizza prestazioni</p>
                </div>
            </button>
            <a href="{{ route('admin.monitoring.logs') }}"
               class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                <div class="text-center">
                    <div class="text-2xl mb-2">📋</div>
                    <h4 class="font-medium text-gray-900">View Logs</h4>
                    <p class="text-sm text-gray-600">Visualizza log sistema</p>
                </div>
            </a>
            <a href="{{ route('admin.monitoring.history') }}"
               class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                <div class="text-center">
                    <div class="text-2xl mb-2">📊</div>
                    <h4 class="font-medium text-gray-900">History</h4>
                    <p class="text-sm text-gray-600">Storico performance</p>
                </div>
            </a>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function monitoringDashboard() {
    return {
        systemHealth: {
            status: 'healthy',
            lastCheck: '12:34:56'
        },
        database: {
            status: 'connected',
            responseTime: 45,
            connections: 12
        },
        cache: {
            hitRate: 94,
            hits: 1847,
            misses: 98
        },
        queue: {
            pending: 23,
            failed: 2
        },
        performance: {
            cpu: 35,
            memory: 68,
            disk: 42
        },
        responseTimes: {
            web: 125,
            api: 89,
            database: 45,
            email: 342
        },
        errors: {
            http: 12,
            database: 0,
            email: 3,
            jobs: 1
        },
        recentAlerts: [
            {
                id: 1,
                level: 'warning',
                message: 'High memory usage detected',
                timestamp: '2 minutes ago'
            },
            {
                id: 2,
                level: 'info',
                message: 'Scheduled backup completed successfully',
                timestamp: '15 minutes ago'
            },
            {
                id: 3,
                level: 'critical',
                message: 'Database connection timeout',
                timestamp: '1 hour ago'
            }
        ],

        init() {
            this.initCharts();
            this.startRealTimeUpdates();
        },

        initCharts() {
            // Performance Chart
            const perfCtx = document.getElementById('performanceChart').getContext('2d');
            new Chart(perfCtx, {
                type: 'line',
                data: {
                    labels: Array.from({length: 12}, (_, i) => `${11-i}min`),
                    datasets: [{
                        label: 'CPU %',
                        data: [30, 35, 32, 40, 38, 35, 33, 37, 34, 36, 35, 35],
                        borderColor: 'rgb(59, 130, 246)',
                        tension: 0.1
                    }, {
                        label: 'Memory %',
                        data: [65, 68, 70, 69, 67, 68, 66, 65, 67, 68, 68, 68],
                        borderColor: 'rgb(16, 185, 129)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });

            // Traffic Chart
            const trafficCtx = document.getElementById('trafficChart').getContext('2d');
            new Chart(trafficCtx, {
                type: 'bar',
                data: {
                    labels: Array.from({length: 12}, (_, i) => `${11-i}min`),
                    datasets: [{
                        label: 'Requests/min',
                        data: [45, 52, 48, 61, 55, 49, 58, 63, 51, 56, 59, 54],
                        backgroundColor: 'rgba(139, 92, 246, 0.8)'
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
        },

        startRealTimeUpdates() {
            setInterval(() => {
                this.fetchMetrics();
            }, 30000); // Aggiorna ogni 30 secondi
        },

        async fetchMetrics() {
            try {
                const response = await fetch('{{ route("admin.monitoring.metrics") }}');
                const data = await response.json();

                // Aggiorna i dati
                Object.assign(this.systemHealth, data.systemHealth);
                Object.assign(this.database, data.database);
                Object.assign(this.cache, data.cache);
                Object.assign(this.queue, data.queue);
                Object.assign(this.performance, data.performance);
                Object.assign(this.responseTimes, data.responseTimes);
                Object.assign(this.errors, data.errors);

            } catch (error) {
                console.error('Error fetching metrics:', error);
            }
        }
    }
}

function refreshMetrics() {
    window.location.reload();
}

async function clearCache() {
    if (confirm('Sei sicuro di voler svuotare la cache?')) {
        try {
            const response = await fetch('{{ route("admin.monitoring.clear-cache") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            if (response.ok) {
                alert('Cache svuotata con successo');
                location.reload();
            }
        } catch (error) {
            alert('Errore nello svuotamento cache');
        }
    }
}

async function optimizeSystem() {
    if (confirm('Ottimizzare il sistema? Potrebbe richiedere alcuni minuti.')) {
        try {
            const response = await fetch('{{ route("admin.monitoring.optimize") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            if (response.ok) {
                alert('Sistema ottimizzato con successo');
                location.reload();
            }
        } catch (error) {
            alert('Errore nell\'ottimizzazione sistema');
        }
    }
}
</script>
@endpush

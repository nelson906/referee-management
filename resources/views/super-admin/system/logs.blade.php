@extends('layouts.super-admin')

@section('title', 'Log di Sistema')

@section('content')
<div class="container mx-auto px-4 py-8">
    {{-- Header --}}
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Log di Sistema</h1>
            <p class="mt-2 text-gray-600">Monitora le attività e gli errori del sistema</p>
        </div>
        <div class="flex space-x-4">
            <button onclick="refreshLogs()"
                    class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition duration-200 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Aggiorna
            </button>
            <button onclick="clearLogs()"
                    class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition duration-200 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
                Pulisci Log
            </button>
        </div>
    </div>

    {{-- File Selector --}}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex items-center space-x-4">
            <label for="log-file" class="text-sm font-medium text-gray-700">File di Log:</label>
            <select id="log-file" onchange="changeLogFile()"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                @foreach($availableFiles as $file)
                    <option value="{{ $file }}" {{ $file === $logFile ? 'selected' : '' }}>
                        {{ $file }}
                    </option>
                @endforeach
            </select>

            <div class="flex space-x-2 ml-auto">
                <label class="inline-flex items-center">
                    <input type="checkbox" id="auto-refresh" class="rounded border-gray-300 text-indigo-600">
                    <span class="ml-2 text-sm text-gray-700">Auto-aggiorna (30s)</span>
                </label>

                <select id="log-level" onchange="filterByLevel()"
                        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <option value="">Tutti i livelli</option>
                    <option value="ERROR">Solo Errori</option>
                    <option value="WARNING">Solo Warning</option>
                    <option value="INFO">Solo Info</option>
                    <option value="DEBUG">Solo Debug</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Log Statistics --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-2 bg-red-100 rounded-lg">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Errori</p>
                    <p class="text-2xl font-semibold text-gray-900" id="error-count">
                        {{ collect($logs)->where('level', 'ERROR')->count() }}
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-2 bg-yellow-100 rounded-lg">
                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Warning</p>
                    <p class="text-2xl font-semibold text-gray-900" id="warning-count">
                        {{ collect($logs)->where('level', 'WARNING')->count() }}
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-2 bg-blue-100 rounded-lg">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Info</p>
                    <p class="text-2xl font-semibold text-gray-900" id="info-count">
                        {{ collect($logs)->where('level', 'INFO')->count() }}
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-2 bg-green-100 rounded-lg">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Totale Voci</p>
                    <p class="text-2xl font-semibold text-gray-900" id="total-count">
                        {{ count($logs) }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Log Entries --}}
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Voci di Log</h3>
        </div>

        <div class="overflow-x-auto" style="max-height: 600px;">
            @if(count($logs) > 0)
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 sticky top-0">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Timestamp
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Livello
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Messaggio
                            </th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Azioni
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200" id="log-table-body">
                        @foreach($logs as $index => $log)
                        <tr class="log-entry hover:bg-gray-50" data-level="{{ $log['level'] }}">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ \Carbon\Carbon::parse($log['timestamp'])->format('d/m/Y H:i:s') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                    @switch($log['level'])
                                        @case('ERROR') bg-red-100 text-red-800 @break
                                        @case('WARNING') bg-yellow-100 text-yellow-800 @break
                                        @case('INFO') bg-blue-100 text-blue-800 @break
                                        @case('DEBUG') bg-gray-100 text-gray-800 @break
                                        @default bg-gray-100 text-gray-800
                                    @endswitch">
                                    {{ $log['level'] }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <div class="truncate max-w-md" title="{{ $log['message'] }}">
                                    {{ $log['message'] }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                <button onclick="showFullLog({{ $index }})"
                                        class="text-indigo-600 hover:text-indigo-900">
                                    Dettagli
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="px-6 py-12 text-center text-gray-500">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <p class="mt-2">Nessuna voce di log trovata nel file selezionato</p>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Log Detail Modal --}}
<div id="log-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Dettagli Log</h3>
                <button onclick="closeLogModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="bg-gray-100 p-4 rounded-lg">
                <pre id="log-content" class="text-sm text-gray-800 whitespace-pre-wrap break-words"></pre>
            </div>
            <div class="mt-4 flex justify-end">
                <button onclick="closeLogModal()"
                        class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                    Chiudi
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
const logs = @json($logs);
let autoRefreshInterval;

// Change log file
function changeLogFile() {
    const selectedFile = document.getElementById('log-file').value;
    window.location.href = `{{ route('super-admin.system.logs') }}?file=${selectedFile}`;
}

// Filter logs by level
function filterByLevel() {
    const selectedLevel = document.getElementById('log-level').value;
    const rows = document.querySelectorAll('.log-entry');

    rows.forEach(row => {
        if (!selectedLevel || row.dataset.level === selectedLevel) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });

    updateCounts();
}

// Update counts
function updateCounts() {
    const visibleRows = document.querySelectorAll('.log-entry:not([style*="display: none"])');
    const levels = ['ERROR', 'WARNING', 'INFO'];

    levels.forEach(level => {
        const count = Array.from(visibleRows).filter(row => row.dataset.level === level).length;
        const countElement = document.getElementById(level.toLowerCase() + '-count');
        if (countElement) {
            countElement.textContent = count;
        }
    });

    document.getElementById('total-count').textContent = visibleRows.length;
}

// Show full log details
function showFullLog(index) {
    const log = logs[index];
    if (log) {
        document.getElementById('log-content').textContent = log.full_line;
        document.getElementById('log-modal').classList.remove('hidden');
    }
}

// Close log modal
function closeLogModal() {
    document.getElementById('log-modal').classList.add('hidden');
}

// Refresh logs
function refreshLogs() {
    window.location.reload();
}

// Clear logs
function clearLogs() {
    if (confirm('Sei sicuro di voler eliminare tutti i log? Questa azione è irreversibile.')) {
        // Implement log clearing logic
        alert('Funzionalità non ancora implementata');
    }
}

// Auto-refresh functionality
document.getElementById('auto-refresh').addEventListener('change', function() {
    if (this.checked) {
        autoRefreshInterval = setInterval(refreshLogs, 30000);
    } else {
        clearInterval(autoRefreshInterval);
    }
});

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('log-modal');
    if (event.target === modal) {
        closeLogModal();
    }
}
</script>
@endpush
@endsection

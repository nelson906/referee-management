@extends('layouts.admin')

@section('content')
<div class="p-6">
    <div class="max-w-7xl mx-auto">
        {{-- Header --}}
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900">🏆 Sistema Notifiche Torneo</h1>
            <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800">
                NUOVO SISTEMA
            </span>
        </div>

        {{-- Success Alert --}}
        @if(session('success'))
            <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('success') }}</span>
                <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
                    <svg class="fill-current h-6 w-6 text-green-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                        <title>Close</title>
                        <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
                    </svg>
                </button>
            </div>
        @endif

        {{-- Error Alert --}}
        @if(session('error'))
            <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('error') }}</span>
                <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
                    <svg class="fill-current h-6 w-6 text-red-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                        <title>Close</title>
                        <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
                    </svg>
                </button>
            </div>
        @endif

        {{-- 📊 Statistiche Dashboard --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-green-500 text-white rounded-lg shadow p-6">
                <div class="text-center">
                    <h3 class="text-3xl font-bold">{{ number_format($stats['total_sent'] ?? 0) }}</h3>
                    <p class="text-green-100 text-sm">Notifiche Inviate</p>
                </div>
            </div>

            <div class="bg-red-500 text-white rounded-lg shadow p-6">
                <div class="text-center">
                    <h3 class="text-3xl font-bold">{{ number_format($stats['total_failed'] ?? 0) }}</h3>
                    <p class="text-red-100 text-sm">Invii Falliti</p>
                </div>
            </div>

            <div class="bg-blue-500 text-white rounded-lg shadow p-6">
                <div class="text-center">
                    <h3 class="text-3xl font-bold">{{ number_format($stats['this_month'] ?? 0) }}</h3>
                    <p class="text-blue-100 text-sm">Questo Mese</p>
                </div>
            </div>

            <div class="bg-yellow-500 text-white rounded-lg shadow p-6">
                <div class="text-center">
                    <h3 class="text-3xl font-bold">{{ number_format($stats['pending_tournaments'] ?? 0) }}</h3>
                    <p class="text-yellow-100 text-sm">Tornei da Notificare</p>
                </div>
            </div>
        </div>

        {{-- 📋 Lista Notifiche Raggruppate --}}
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg font-medium text-gray-900">
                    📋 Notifiche Tornei ({{ $tournamentNotifications->total() ?? 0 }} totali)
                </h3>
            </div>

            <div class="p-6">
                @if($tournamentNotifications->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        🏆 Torneo
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        📧 Destinatari
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        📅 Inviato
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        📊 Stato
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        👤 Inviato da
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        ⚡ Azioni
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($tournamentNotifications as $notification)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">
                                                    {{ $notification->tournament->name }}
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    {{ $notification->tournament->club->name ?? 'N/A' }} •
                                                    {{ $notification->tournament->zone->name ?? 'N/A' }}
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                                {{ $notification->total_recipients }} totali
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                {{ $notification->sent_at ? $notification->sent_at->format('d/m/Y H:i') : 'Mai inviato' }}
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                {{ $notification->time_ago }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @php
                                                $statusClasses = [
                                                    'sent' => 'bg-green-100 text-green-800',
                                                    'partial' => 'bg-yellow-100 text-yellow-800',
                                                    'failed' => 'bg-red-100 text-red-800'
                                                ];
                                                $statusClass = $statusClasses[$notification->status] ?? 'bg-gray-100 text-gray-800';
                                            @endphp
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $statusClass }}">
                                                {{ $notification->status_formatted }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $notification->sentBy->name ?? 'Sistema' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="{{ route('admin.tournament-notifications.show', $notification) }}"
                                               class="inline-flex items-center px-3 py-1 border border-blue-600 text-blue-600 text-sm rounded-md hover:bg-blue-600 hover:text-white transition-colors">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                                Dettagli
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Paginazione --}}
                    <div class="mt-6">
                        {{ $tournamentNotifications->links() }}
                    </div>
                @else
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Nessuna notifica trovata</h3>
                        <p class="text-gray-600 max-w-md mx-auto">
                            Il nuovo sistema raggruppa le notifiche per torneo.<br>
                            Una volta inviate, vedrai <strong>1 riga per torneo</strong> invece di N righe separate.
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('[role="alert"]');
        alerts.forEach(function(alert) {
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.style.display = 'none';
            }, 300);
        });
    }, 5000);
});
</script>
@endpush

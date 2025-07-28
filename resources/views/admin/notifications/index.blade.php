{{-- File: resources/views/admin/notifications/index.blade.php - FIXED --}}
@extends('layouts.admin')

@section('content')
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                📧 Notifiche Inviate
            </h2>

            <div class="flex space-x-3">
                {{-- ✅ PULSANTE PER INVIARE NUOVE NOTIFICHE --}}
                <a href="{{ route('tournaments.index') }}"
                   class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                    ➕ Invia Nuova Notifica
                </a>
                <a href="{{ route('admin.notifications.stats') }}"
                   class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700">
                    📊 Statistiche
                </a>
                <a href="{{ route('admin.letter-templates.index') }}"
                   class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    📝 Template
                </a>
                <a href="{{ route('super-admin.institutional-emails.index') }}"
                   class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                    📮 Email Istituzionali
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Flash Messages --}}
            @if(session('success'))
                <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-400 rounded-lg">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-400 rounded-lg">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">

                    {{-- Stats Header --}}
                    @php
                        $totalNotifications = $notifications->total();
                        $sentCount = $notifications->where('status', 'sent')->count();
                        $pendingCount = $notifications->where('status', 'pending')->count();
                        $failedCount = $notifications->where('status', 'failed')->count();
                    @endphp

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-blue-600">{{ $totalNotifications }}</div>
                            <div class="text-sm text-blue-600">Totale Notifiche</div>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-green-600">{{ $sentCount }}</div>
                            <div class="text-sm text-green-600">Inviate</div>
                        </div>
                        <div class="bg-yellow-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-yellow-600">{{ $pendingCount }}</div>
                            <div class="text-sm text-yellow-600">In Sospeso</div>
                        </div>
                        <div class="bg-red-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-red-600">{{ $failedCount }}</div>
                            <div class="text-sm text-red-600">Fallite</div>
                        </div>
                    </div>

                    {{-- Filter Bar --}}
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <form method="GET" class="flex flex-wrap gap-4 items-end">
                            <div class="flex-1 min-w-48">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo Destinatario</label>
                                <select name="recipient_type" class="w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="">Tutti i tipi</option>
                                    <option value="referee" {{ request('recipient_type') === 'referee' ? 'selected' : '' }}>Arbitri</option>
                                    <option value="club" {{ request('recipient_type') === 'club' ? 'selected' : '' }}>Circoli</option>
                                    <option value="institutional" {{ request('recipient_type') === 'institutional' ? 'selected' : '' }}>Istituzionali</option>
                                </select>
                            </div>

                            <div class="flex-1 min-w-48">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Stato</label>
                                <select name="status" class="w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="">Tutti gli stati</option>
                                    <option value="sent" {{ request('status') === 'sent' ? 'selected' : '' }}>Inviate</option>
                                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>In Sospeso</option>
                                    <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Fallite</option>
                                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Annullate</option>
                                </select>
                            </div>

                            <div class="flex-1 min-w-48">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Periodo</label>
                                <select name="period" class="w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="">Tutto il periodo</option>
                                    <option value="today" {{ request('period') === 'today' ? 'selected' : '' }}>Oggi</option>
                                    <option value="week" {{ request('period') === 'week' ? 'selected' : '' }}>Ultima settimana</option>
                                    <option value="month" {{ request('period') === 'month' ? 'selected' : '' }}>Ultimo mese</option>
                                </select>
                            </div>

                            <div class="flex space-x-2">
                                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                    🔍 Filtra
                                </button>
                                <a href="{{ route('admin.notifications.index') }}" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                    🗑️ Reset
                                </a>
                            </div>
                        </form>
                    </div>

                    @if($notifications->count() > 0)
                        {{-- Notifications Table --}}
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Data/Ora
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Destinatario
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Oggetto
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Torneo
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Stato
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Azioni
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($notifications as $notification)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    {{ $notification->created_at->format('d/m/Y H:i') }}
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    {{ $notification->created_at->diffForHumans() }}
                                                </div>
                                            </td>

                                            <td class="px-6 py-4">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 h-10 w-10">
                                                        <div class="h-10 w-10 rounded-full
                                                            @if($notification->status === 'sent') bg-green-100
                                                            @elseif($notification->status === 'failed') bg-red-100
                                                            @else bg-yellow-100 @endif
                                                            flex items-center justify-center">
                                                            @if($notification->recipient_type === 'referee')
                                                                👨‍⚖️
                                                            @elseif($notification->recipient_type === 'club')
                                                                🏌️
                                                            @else
                                                                🏛️
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900">
                                                            {{-- ✅ NOME SPECIFICO DEL DESTINATARIO --}}
                                                            @if($notification->recipient_email)
                                                                @php
                                                                    // Estrai il nome dall'email se non c'è recipient_name
                                                                    $displayName = $notification->recipient_name ??
                                                                                   explode('@', $notification->recipient_email)[0];
                                                                @endphp
                                                                {{ $displayName }}
                                                                <span class="text-gray-600 text-xs">
                                                                    ({{ ucfirst($notification->recipient_type) }})
                                                                </span>
                                                            @else
                                                                {{ ucfirst($notification->recipient_type) }}
                                                            @endif
                                                        </div>
                                                        <div class="text-sm text-gray-500">
                                                            {{ $notification->recipient_email }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                            <td class="px-6 py-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    {{ Str::limit($notification->subject, 50) }}
                                                </div>
                                                @if($notification->template_used)
                                                    <div class="text-xs text-gray-500">
                                                        📝 Template: {{ $notification->template_used }}
                                                    </div>
                                                @endif
                                            </td>

                                            <td class="px-6 py-4">
                                                {{-- ✅ CORRETTO COLLEGAMENTO AL TORNEO --}}
                                                @php
                                                    $tournament = null;

                                                    // Tenta di trovare il torneo attraverso assignment
                                                    if ($notification->assignment && $notification->assignment->tournament) {
                                                        $tournament = $notification->assignment->tournament;
                                                    }
                                                    // Fallback: cerca per pattern nell'oggetto
                                                    elseif (preg_match('/torneo\s+(.+?)\s+/i', $notification->subject, $matches)) {
                                                        $tournamentName = trim($matches[1]);
                                                        $tournament = \App\Models\Tournament::where('name', 'LIKE', "%{$tournamentName}%")->first();
                                                    }
                                                @endphp

                                                @if($tournament)
                                                    <a href="{{ route('tournaments.show', $tournament) }}"
                                                       class="text-sm text-indigo-600 hover:text-indigo-900">
                                                        {{ Str::limit($tournament->name, 40) }}
                                                    </a>
                                                    <div class="text-xs text-gray-500">
                                                        {{ $tournament->start_date->format('d/m/Y') }}
                                                    </div>
                                                @else
                                                    {{-- ✅ MOSTRA INFO DAL SUBJECT SE NON TROVA IL TORNEO --}}
                                                    @if(str_contains($notification->subject, 'torneo'))
                                                        <div class="text-sm text-gray-600">
                                                            @php
                                                                // Estrai nome torneo dal subject
                                                                if (preg_match('/(?:torneo|tournament)\s+(.+?)(?:\s+che|\s+del|\s*$)/i', $notification->subject, $matches)) {
                                                                    echo Str::limit(trim($matches[1]), 30);
                                                                } else {
                                                                    echo 'Torneo (dettagli nel subject)';
                                                                }
                                                            @endphp
                                                        </div>
                                                        <div class="text-xs text-gray-400">Da subject</div>
                                                    @else
                                                        <span class="text-sm text-gray-400">N/A</span>
                                                    @endif
                                                @endif
                                            </td>

                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full
                                                    @switch($notification->status)
                                                        @case('sent')
                                                            bg-green-100 text-green-800
                                                            @break
                                                        @case('pending')
                                                            bg-yellow-100 text-yellow-800
                                                            @break
                                                        @case('failed')
                                                            bg-red-100 text-red-800
                                                            @break
                                                        @case('cancelled')
                                                            bg-gray-100 text-gray-800
                                                            @break
                                                        @default
                                                            bg-gray-100 text-gray-800
                                                    @endswitch">
                                                    @switch($notification->status)
                                                        @case('sent')
                                                            ✅ Inviata
                                                            @break
                                                        @case('pending')
                                                            ⏳ In Sospeso
                                                            @break
                                                        @case('failed')
                                                            ❌ Fallita
                                                            @break
                                                        @case('cancelled')
                                                            🚫 Annullata
                                                            @break
                                                        @default
                                                            {{ ucfirst($notification->status) }}
                                                    @endswitch
                                                </span>

                                                {{-- ✅ INFO ALLEGATI --}}
                                                @if($notification->attachments && is_array($notification->attachments) && count($notification->attachments) > 0)
                                                    <div class="text-xs text-blue-600 mt-1">
                                                        📎 {{ count($notification->attachments) }} allegati
                                                    </div>
                                                @endif

                                                {{-- ✅ INFO INVIO --}}
                                                @if($notification->sent_at)
                                                    <div class="text-xs text-green-500 mt-1">
                                                        Inviata: {{ $notification->sent_at->format('H:i') }}
                                                    </div>
                                                @endif

                                                {{-- ✅ INFO ERRORE --}}
                                                @if($notification->status === 'failed' && $notification->error_message)
                                                    <div class="text-xs text-red-500 mt-1" title="{{ $notification->error_message }}">
                                                        ⚠️ {{ Str::limit($notification->error_message, 20) }}
                                                    </div>
                                                @endif
                                            </td>

                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex flex-col space-y-1">
                                                    <a href="{{ route('admin.notifications.show', $notification) }}"
                                                       class="text-indigo-600 hover:text-indigo-900 text-xs">
                                                        👁️ Dettagli
                                                    </a>

                                                    @if($notification->status === 'failed')
                                                        <form method="POST" action="{{ route('admin.notifications.retry', $notification) }}" class="inline">
                                                            @csrf
                                                            <button type="submit"
                                                                    class="text-green-600 hover:text-green-900 text-xs"
                                                                    onclick="return confirm('Sei sicuro di voler reinviare questa notifica?')">
                                                                🔄 Reinvia
                                                            </button>
                                                        </form>
                                                    @endif

                                                    @if($notification->status !== 'sent')
                                                        <form method="POST" action="{{ route('admin.notifications.destroy', $notification) }}" class="inline">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit"
                                                                    class="text-red-600 hover:text-red-900 text-xs"
                                                                    onclick="return confirm('Sei sicuro di voler eliminare questa notifica?')">
                                                                🗑️ Elimina
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Pagination --}}
                        <div class="mt-6">
                            {{ $notifications->appends(request()->query())->links() }}
                        </div>

                    @else
                        {{-- Empty State --}}
                        <div class="text-center py-12">
                            <div class="text-6xl mb-4">📭</div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Nessuna notifica trovata</h3>
                            <p class="text-gray-500 mb-6">Non ci sono notifiche che corrispondono ai criteri di ricerca.</p>
                            <a href="{{ route('tournaments.index') }}"
                               class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                📝 Vai ai Tornei per Inviare Notifiche
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

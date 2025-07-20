{{-- File: resources/views/admin/notifications/index.blade.php --}}
@extends('layouts.admin')

@section('content')
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                📧 Notifiche Inviate
            </h2>

            <div class="flex space-x-3">
                <a href="{{ route('letter-templates.index') }}"
                   class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    📝 Template
                </a>
                <a href="{{ route('institutional-emails.index') }}"
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
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-blue-600">{{ $notifications->total() }}</div>
                            <div class="text-sm text-blue-600">Totale Notifiche</div>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-green-600">
                                {{ $notifications->where('status', 'sent')->count() }}
                            </div>
                            <div class="text-sm text-green-600">Inviate</div>
                        </div>
                        <div class="bg-yellow-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-yellow-600">
                                {{ $notifications->where('status', 'pending')->count() }}
                            </div>
                            <div class="text-sm text-yellow-600">In Sospeso</div>
                        </div>
                        <div class="bg-red-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-red-600">
                                {{ $notifications->where('status', 'failed')->count() }}
                            </div>
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
                                <a href="{{ route('notifications.index') }}" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
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
                                                    {{ $notification->time_since }}
                                                </div>
                                            </td>

                                            <td class="px-6 py-4">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 h-10 w-10">
                                                        <div class="h-10 w-10 rounded-full {{ $notification->status_color === 'green' ? 'bg-green-100' : ($notification->status_color === 'red' ? 'bg-red-100' : 'bg-yellow-100') }} flex items-center justify-center">
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
                                                            {{ $notification->recipient_type_label }}
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
                                                @if($notification->template_display_name)
                                                    <div class="text-xs text-gray-500">
                                                        📝 {{ $notification->template_display_name }}
                                                    </div>
                                                @endif
                                            </td>

                                            <td class="px-6 py-4">
                                                @if($notification->assignment && $notification->assignment->tournament)
                                                    <a href="{{ route('tournaments.show', $notification->assignment->tournament) }}"
                                                       class="text-sm text-indigo-600 hover:text-indigo-900">
                                                        {{ Str::limit($notification->assignment->tournament->name, 40) }}
                                                    </a>
                                                    <div class="text-xs text-gray-500">
                                                        {{ $notification->assignment->tournament->start_date->format('d/m/Y') }}
                                                    </div>
                                                @else
                                                    <span class="text-sm text-gray-500">N/A</span>
                                                @endif
                                            </td>

                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full
                                                    {{ $notification->status === 'sent' ? 'bg-green-100 text-green-800' : '' }}
                                                    {{ $notification->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                                    {{ $notification->status === 'failed' ? 'bg-red-100 text-red-800' : '' }}
                                                    {{ $notification->status === 'cancelled' ? 'bg-gray-100 text-gray-800' : '' }}">
                                                    {{ $notification->status_label }}
                                                </span>

                                                @if($notification->hasAttachments())
                                                    <div class="text-xs text-gray-500 mt-1">
                                                        📎 {{ $notification->attachment_count }} allegati
                                                    </div>
                                                @endif
                                            </td>

                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex space-x-2">
                                                    <a href="{{ route('notifications.show', $notification) }}"
                                                       class="text-indigo-600 hover:text-indigo-900">
                                                        👁️ Dettagli
                                                    </a>

                                                    @if($notification->canBeRetried())
                                                        <form method="POST" action="{{ route('notifications.resend', $notification) }}" class="inline">
                                                            @csrf
                                                            <button type="submit"
                                                                    class="text-green-600 hover:text-green-900"
                                                                    onclick="return confirm('Sei sicuro di voler reinviare questa notifica?')">
                                                                🔄 Reinvia
                                                            </button>
                                                        </form>
                                                    @endif

                                                    @if($notification->status === 'pending')
                                                        <form method="POST" action="{{ route('notifications.cancel', $notification) }}" class="inline">
                                                            @csrf
                                                            <button type="submit"
                                                                    class="text-red-600 hover:text-red-900"
                                                                    onclick="return confirm('Sei sicuro di voler annullare questa notifica?')">
                                                                ❌ Annulla
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
                            {{ $notifications->links() }}
                        </div>

                    @else
                        {{-- Empty State --}}
                        <div class="text-center py-12">
                            <div class="text-6xl mb-4">📭</div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Nessuna notifica trovata</h3>
                            <p class="text-gray-500 mb-6">Non ci sono notifiche che corrispondono ai criteri di ricerca.</p>
                            <a href="{{ route('tournaments.index') }}"
                               class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                📝 Invia Nuova Notifica
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

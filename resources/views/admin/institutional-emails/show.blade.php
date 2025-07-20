@extends('layouts.admin')

@section('title', 'Dettagli Email Istituzionale')

@section('content')
<div class="container mx-auto px-4 py-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="{{ route('institutional-emails.index') }}" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <h1 class="text-2xl font-bold text-gray-900">{{ $email->name }}</h1>

                {{-- Status Badge --}}
                @if($email->is_active)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        Attiva
                    </span>
                @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                        Inattiva
                    </span>
                @endif
            </div>

            {{-- Actions --}}
            <div class="flex space-x-2">
                <a href="{{ route('institutional-emails.edit', $email) }}"
                   class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Modifica
                </a>
            </div>
        </div>
    </div>

    {{-- Email Details --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main Info --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Informazioni Email</h3>
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Nome</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $email->name }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500">Indirizzo Email</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <a href="mailto:{{ $email->email }}" class="text-blue-600 hover:text-blue-800">
                                {{ $email->email }}
                            </a>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500">Categoria</dt>
                        <dd class="mt-1">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $email->category_badge_color }}">
                                {{ $email->category_display }}
                            </span>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500">Zona</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $email->zone?->name ?? 'Tutte le zone' }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500">Stato</dt>
                        <dd class="mt-1">
                            @if($email->is_active)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    ✅ Attiva
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    ❌ Inattiva
                                </span>
                            @endif
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500">Creata</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $email->created_at->format('d/m/Y H:i') }}</dd>
                    </div>
                </dl>

                @if($email->description)
                <div class="mt-4">
                    <dt class="text-sm font-medium text-gray-500">Descrizione</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $email->description }}</dd>
                </div>
                @endif
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Notifications Settings --}}
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Notifiche</h3>

                @if($email->receive_all_notifications)
                    <div class="flex items-center p-3 bg-green-50 rounded-md">
                        <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-sm text-green-800 font-medium">Riceve tutte le notifiche</span>
                    </div>
                @else
                    <div class="space-y-2">
                        <p class="text-sm text-gray-500 mb-3">Tipi di notifica abilitati:</p>
                        @if($email->notification_types && count($email->notification_types) > 0)
                            @foreach($email->notification_types as $type)
                                <div class="flex items-center p-2 bg-blue-50 rounded-md">
                                    <svg class="w-4 h-4 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                    </svg>
                                    <span class="text-sm text-blue-800">{{ ucfirst($type) }}</span>
                                </div>
                            @endforeach
                        @else
                            <p class="text-sm text-gray-500 italic">Nessun tipo di notifica selezionato</p>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Actions --}}
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Azioni</h3>
                <div class="space-y-3">
                    <button onclick="toggleActive()"
                            class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">
                        {{ $email->is_active ? '🔒 Disattiva' : '✅ Attiva' }}
                    </button>

                    <button onclick="testEmail()"
                            class="w-full text-left px-3 py-2 text-sm text-blue-600 hover:bg-blue-50 rounded-md">
                        📧 Invia Test Email
                    </button>

                    <form method="POST" action="{{ route('institutional-emails.destroy', $email) }}"
                          onsubmit="return confirm('Sei sicuro di voler eliminare questa email?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="w-full text-left px-3 py-2 text-sm text-red-600 hover:bg-red-50 rounded-md">
                            🗑️ Elimina
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleActive() {
    fetch(`/institutional-emails/{{ $email->id }}/toggle-active`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

function testEmail() {
    const subject = prompt('Oggetto email di test:', 'Test Email Sistema');
    if (!subject) return;

    const message = prompt('Messaggio:', 'Questa è una email di test dal sistema.');
    if (!message) return;

    const formData = new FormData();
    formData.append('test_subject', subject);
    formData.append('test_message', message);

    fetch(`/institutional-emails/{{ $email->id }}/test`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
    });
}
</script>
@endsection

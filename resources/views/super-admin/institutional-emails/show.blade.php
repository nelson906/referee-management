{{-- resources/views/super-admin/institutional-emails/show.blade.php --}}
@extends('layouts.super-admin')

@section('title', 'Dettagli Email Istituzionale')

@section('header', 'Dettagli Email Istituzionale')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white shadow-sm rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-medium text-gray-900">{{ $institutionalEmail->name }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ $institutionalEmail->email }}</p>
                </div>
                <div class="flex items-center space-x-3">
                    <a href="{{ route('super-admin.institutional-emails.edit', $institutionalEmail) }}"
                       class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        Modifica
                    </a>
                    <a href="{{ route('super-admin.institutional-emails.index') }}"
                       class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Indietro
                    </a>
                </div>
            </div>
        </div>

        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Informazioni Generali --}}
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="text-sm font-medium text-gray-900 mb-3">Informazioni Generali</h3>
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $institutionalEmail->name }}</dd>
                        </div>

                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Email</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <a href="mailto:{{ $institutionalEmail->email }}" class="text-indigo-600 hover:text-indigo-500">
                                    {{ $institutionalEmail->email }}
                                </a>
                            </dd>
                        </div>

                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Categoria</dt>
                            <dd class="mt-1">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @switch($institutionalEmail->category)
                                        @case('federazione') bg-purple-100 text-purple-800 @break
                                        @case('comitato') bg-blue-100 text-blue-800 @break
                                        @case('zona') bg-green-100 text-green-800 @break
                                        @default bg-gray-100 text-gray-800
                                    @endswitch">
                                    {{ $institutionalEmail->category_label }}
                                </span>
                            </dd>
                        </div>

                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Zona</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $institutionalEmail->zone ? $institutionalEmail->zone->name : 'Tutte le Zone' }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Stato</dt>
                            <dd class="mt-1">
                                @if($institutionalEmail->is_active)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <svg class="w-1.5 h-1.5 mr-1.5" fill="currentColor" viewBox="0 0 8 8">
                                            <circle cx="4" cy="4" r="3"/>
                                        </svg>
                                        Attiva
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        <svg class="w-1.5 h-1.5 mr-1.5" fill="currentColor" viewBox="0 0 8 8">
                                            <circle cx="4" cy="4" r="3"/>
                                        </svg>
                                        Disattivata
                                    </span>
                                @endif
                            </dd>
                        </div>

                        @if($institutionalEmail->description)
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Descrizione</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $institutionalEmail->description }}</dd>
                        </div>
                        @endif
                    </dl>
                </div>

                {{-- Configurazione Notifiche --}}
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="text-sm font-medium text-gray-900 mb-3">Configurazione Notifiche</h3>

                    @if($institutionalEmail->receive_all_notifications)
                        <div class="flex items-center p-3 bg-indigo-50 rounded-md">
                            <svg class="w-5 h-5 text-indigo-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="text-sm text-indigo-800 font-medium">Riceve tutte le notifiche</span>
                        </div>
                    @elseif($institutionalEmail->notification_types && count($institutionalEmail->notification_types) > 0)
                        <div>
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Tipi di notifica attivi</p>
                            <div class="space-y-2">
                                @foreach($institutionalEmail->notification_types as $type)
                                    @if(isset(App\Models\InstitutionalEmail::NOTIFICATION_TYPES[$type]))
                                        <div class="flex items-center">
                                            <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            <span class="text-sm text-gray-700">{{ App\Models\InstitutionalEmail::NOTIFICATION_TYPES[$type] }}</span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="flex items-center p-3 bg-yellow-50 rounded-md">
                            <svg class="w-5 h-5 text-yellow-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                            <span class="text-sm text-yellow-800">Nessuna notifica configurata</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Metadati --}}
            <div class="mt-8 border-t border-gray-200 pt-6">
                <h3 class="text-sm font-medium text-gray-900 mb-3">Informazioni Sistema</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Creata il</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            {{ $institutionalEmail->created_at->format('d/m/Y H:i') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Ultima modifica</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            {{ $institutionalEmail->updated_at->format('d/m/Y H:i') }}
                        </dd>
                    </div>
                </div>
            </div>

            {{-- Azioni Rapide --}}
            <div class="mt-8 border-t border-gray-200 pt-6">
                <h3 class="text-sm font-medium text-gray-900 mb-3">Azioni Rapide</h3>
                <div class="flex flex-wrap gap-3">
                    @if($institutionalEmail->is_active)
                        <form method="POST" action="{{ route('super-admin.institutional-emails.toggle-active', $institutionalEmail) }}" class="inline">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                    onclick="return confirm('Sei sicuro di voler disattivare questa email?')">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 5.636"></path>
                                </svg>
                                Disattiva
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('super-admin.institutional-emails.toggle-active', $institutionalEmail) }}" class="inline">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center px-3 py-2 border border-green-300 shadow-sm text-sm leading-4 font-medium rounded-md text-green-700 bg-green-50 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Attiva
                            </button>
                        </form>
                    @endif

                    {{-- Test Email Button (se implementato nel controller) --}}
                    {{-- Commentato fino a implementazione metodo test

                    <form method="POST" action="{{ route('super-admin.institutional-emails.test', $institutionalEmail) }}" class="inline">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center px-3 py-2 border border-blue-300 shadow-sm text-sm leading-4 font-medium rounded-md text-blue-700 bg-blue-50 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            Invia Test
                        </button>
                    </form>
                    --}}


                    <form method="POST" action="{{ route('super-admin.institutional-emails.destroy', $institutionalEmail) }}" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="inline-flex items-center px-3 py-2 border border-red-300 shadow-sm text-sm leading-4 font-medium rounded-md text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                onclick="return confirm('Sei sicuro di voler eliminare questa email istituzionale? Questa azione non può essere annullata.')">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            Elimina
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

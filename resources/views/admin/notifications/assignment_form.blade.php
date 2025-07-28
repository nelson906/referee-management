{{-- File: resources/views/admin/notifications/assignment_form.blade.php --}}
@extends('layouts.admin')

@section('title', ' ')

@section('content')

    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                📧 Invia Notifica Assegnazione - {{ $tournament->name }}
            </h2>
            <a href="{{ route('tournaments.show', $tournament) }}"
                class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
                ← Torna al Torneo
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Flash Messages --}}
            @if (session('success'))
                <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-400 rounded-lg">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            @if (session('error'))
                <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-400 rounded-lg">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                {{-- Sidebar: Info Torneo --}}
                <div class="lg:col-span-1">

                    {{-- Tournament Details --}}
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">🏌️ Dettagli Torneo</h3>
                            <dl class="space-y-3 text-sm">
                                <div>
                                    <dt class="text-gray-600">Nome:</dt>
                                    <dd class="font-medium text-gray-900">{{ $tournament->name }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-600">Date:</dt>
                                    <dd class="font-medium text-gray-900">
                                        {{ $tournament->start_date->format('d/m/Y') }}
                                        @if (!$tournament->start_date->isSameDay($tournament->end_date))
                                            - {{ $tournament->end_date->format('d/m/Y') }}
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-gray-600">Circolo:</dt>
                                    <dd class="font-medium text-gray-900">{{ $tournament->club->name }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-600">Zona:</dt>
                                    <dd class="font-medium text-gray-900">{{ $tournament->club->zone->name }}</dd>
                                </div>
                                @if ($tournament->tournamentType)
                                    <div>
                                        <dt class="text-gray-600">Categoria:</dt>
                                        <dd class="font-medium text-gray-900">{{ $tournament->tournamentType->name }}</dd>
                                    </div>
                                @endif
                            </dl>
                        </div>
                    </div>

                    {{-- Document Status --}}
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                        <div class="p-6">
                            <h4 class="text-md font-medium text-gray-900 mb-4">📎 Documenti Disponibili</h4>

                            @if ($documentStatus['hasConvocation'])
                                <div class="flex items-center p-3 mb-3 bg-green-50 border border-green-200 rounded-lg">
                                    <div class="flex-shrink-0">
                                        <svg class="w-5 h-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd"
                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-green-800">Convocazione SZR</p>
                                        <p class="text-xs text-green-600">Disponibile per arbitri</p>
                                    </div>
                                </div>
                            @endif

                            @if ($documentStatus['hasClubLetter'])
                                <div class="flex items-center p-3 mb-3 bg-blue-50 border border-blue-200 rounded-lg">
                                    <div class="flex-shrink-0">
                                        <svg class="w-5 h-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd"
                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-blue-800">Lettera Circolo</p>
                                        <p class="text-xs text-blue-600">Disponibile per circolo</p>
                                    </div>
                                </div>
                            @endif

                            @if (!$documentStatus['hasConvocation'] && !$documentStatus['hasClubLetter'])
                                <div class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <svg class="w-5 h-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd"
                                                    d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm font-medium text-yellow-800">Nessun documento</p>
                                            <p class="text-xs text-yellow-600">
                                                <a href="{{ route('tournaments.show', $tournament) }}" class="underline">
                                                    Genera i documenti prima
                                                </a>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Assignments Summary --}}
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h4 class="text-md font-medium text-gray-900 mb-4">
                                👥 Arbitri Assegnati ({{ $assignedReferees->count() }})
                            </h4>
                            @if ($assignedReferees->count() > 0)
                                <div class="space-y-3">
                                    @foreach (['Direttore di Torneo', 'Arbitro', 'Osservatore'] as $role)
                                        @php
                                            $roleAssignments = $assignedReferees->where('pivot.role', $role);
                                        @endphp
                                        @if ($roleAssignments->count() > 0)
                                            <div>
                                                <h5 class="text-sm font-medium text-gray-800 mb-2">
                                                    {{ $role }} ({{ $roleAssignments->count() }})
                                                </h5>
                                                <div class="space-y-1">
                                                    @foreach ($roleAssignments as $referee)
                                                        <div class="text-xs text-gray-600 ml-2 p-2 bg-gray-50 rounded">
                                                            • {{ $referee->name }}
                                                            <div class="text-xs text-gray-500">{{ $referee->email }}</div>
                                                            @if ($referee->pivot->assigned_at)
                                                                <div class="text-xs text-gray-400">
                                                                    Assegnato:
                                                                    {{ \Carbon\Carbon::parse($referee->pivot->assigned_at)->format('d/m/Y H:i') }}
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-4">
                                    <div class="text-gray-400 text-sm">
                                        ⚠️ Nessun arbitro assegnato al torneo
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        Assegna prima gli arbitri per poter inviare le notifiche
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Main Content: Form --}}
                <div class="lg:col-span-2">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">

                            <form method="POST"
                                action="{{ $hasExistingConvocation ||
                                (isset($documentStatus) && ($documentStatus['hasConvocation'] || $documentStatus['hasClubLetter']))
                                    ? route('admin.tournaments.send-assignment-with-convocation', $tournament)
                                    : route('admin.tournaments.send-assignment', $tournament) }}"
                                class="space-y-6">
                                @csrf

                                {{-- Template Selection --}}
                                @if ($templates->count() > 0)
                                    <div>
                                        <label for="template_id" class="block text-sm font-medium text-gray-700 mb-2">
                                            📝 Template Email (Opzionale)
                                        </label>
                                        <select name="template_id" id="template_id"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200"
                                            onchange="loadTemplate(this.value)">
                                            <option value="">Seleziona un template...</option>
                                            @foreach ($templates as $template)
                                                <option value="{{ $template->id }}"
                                                    data-subject="{{ $template->subject }}"
                                                    data-body="{{ $template->body }}">
                                                    {{ $template->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif

                                {{-- Subject --}}
                                <div>
                                    <label for="subject" class="block text-sm font-medium text-gray-700 mb-2">
                                        📧 Oggetto Email <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="subject" id="subject"
                                        value="{{ old('subject', 'Assegnazione Arbitri - ' . $tournament->name) }}"
                                        required
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 @error('subject') border-red-500 @enderror">
                                    @error('subject')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Message --}}
                                <div>
                                    <label for="message" class="block text-sm font-medium text-gray-700 mb-2">
                                        📝 Messaggio <span class="text-red-500">*</span>
                                    </label>
                                    <textarea name="message" id="message" rows="8" required
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 @error('message') border-red-500 @enderror"
                                        placeholder="Scrivi il messaggio per la notifica...">{{ old(
                                            'message',
                                            'Si comunica l\'assegnazione degli arbitri per il torneo ' .
                                                $tournament->name .
                                                ' che si terrà ' .
                                                $tournament->start_date->format('d/m/Y') .
                                                ($tournament->start_date->format('d/m/Y') != $tournament->end_date->format('d/m/Y')
                                                    ? ' - ' . $tournament->end_date->format('d/m/Y')
                                                    : '') .
                                                ' presso ' .
                                                $tournament->club->name .
                                                '.

                                        Si prega di prendere nota degli arbitri assegnati e di procedere con le comunicazioni necessarie.

                                        Cordiali saluti',
                                        ) }}</textarea>
                                    @error('message')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <!-- Gestione Allegati -->
                                @if (
                                    $hasExistingConvocation ||
                                        (isset($documentStatus) && ($documentStatus['hasConvocation'] || $documentStatus['hasClubLetter'])))
                                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                        <h4 class="font-medium text-green-800 mb-3">📎 Documenti Disponibili</h4>

                                        <div class="space-y-2">
                                            @if (isset($documentStatus) && $documentStatus['hasConvocation'])
                                                <div class="flex items-center text-sm text-green-700">
                                                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd"
                                                            d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"
                                                            clip-rule="evenodd"></path>
                                                    </svg>
                                                    Convocazione disponibile
                                                </div>
                                            @endif

                                            @if (isset($documentStatus) && $documentStatus['hasClubLetter'])
                                                <div class="flex items-center text-sm text-green-700">
                                                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd"
                                                            d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"
                                                            clip-rule="evenodd"></path>
                                                    </svg>
                                                    Lettera circolo disponibile
                                                </div>
                                            @endif
                                        </div>

                                        <div class="mt-3">
                                            <label class="flex items-center">
                                                <input type="checkbox" name="attach_convocation" value="1" checked
                                                    class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                                <span class="ml-2 text-sm font-medium text-green-800">
                                                    Allega documenti disponibili alle email
                                                </span>
                                            </label>
                                            <p class="text-xs text-green-600 mt-1">
                                                ℹ️ La lettera circolo verrà allegata solo alle email inviate al circolo
                                                organizzatore
                                            </p>
                                        </div>
                                    </div>
                                @endif
                                {{-- Attachment Options --}}
                                @if ($documentStatus['hasConvocation'] || $documentStatus['hasClubLetter'])
                                    <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                        <div class="flex items-start">
                                            <input type="checkbox" name="attach_documents" id="attach_documents"
                                                value="1" checked
                                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mt-1">
                                            <div class="ml-3">
                                                <label for="attach_documents" class="text-sm font-medium text-blue-900">
                                                    📎 Allega documenti disponibili
                                                </label>
                                                <div class="text-xs text-blue-700 mt-1 space-y-1">
                                                    @if ($documentStatus['hasConvocation'])
                                                        <div>✓ Convocazione SZR (solo per arbitri)</div>
                                                    @endif
                                                    @if ($documentStatus['hasClubLetter'])
                                                        <div>✓ Lettera Circolo (solo per circolo)</div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                {{-- Recipients: Referees --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-3">
                                        👥 Destinatari Arbitri
                                    </label>
                                    @if ($assignedReferees->count() > 0)
                                        <div
                                            class="space-y-3 max-h-64 overflow-y-auto border border-gray-200 rounded-lg p-4">
                                            @foreach ($assignedReferees as $referee)
                                                <div class="flex items-center">
                                                    <input type="checkbox" name="recipients[]"
                                                        value="{{ $referee->id }}" id="referee_{{ $referee->id }}"
                                                        checked
                                                        class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                                    <label for="referee_{{ $referee->id }}"
                                                        class="ml-3 text-sm text-gray-900 flex-1">
                                                        <div class="flex items-center justify-between">
                                                            <div>
                                                                <span class="font-medium">{{ $referee->name }}</span>
                                                                <span class="text-gray-600">({{ $referee->role }})</span>
                                                                <div class="text-xs text-gray-500">{{ $referee->email }}
                                                                </div>
                                                            </div>
                                                            <div class="text-xs text-gray-400">
                                                                Assegnato
                                                                {{ \Carbon\Carbon::parse($referee->pivot->assigned_at)->format('d/m/Y') }}
                                                            </div>
                                                        </div>
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                                            <p class="text-sm text-yellow-800">⚠️ Nessun arbitro assegnato al torneo</p>
                                        </div>
                                    @endif
                                </div>

                                {{-- Club Notification --}}
                                <div>
                                    <div class="flex items-center">
                                        <input type="checkbox" name="send_to_club" id="send_to_club" value="1"
                                            checked
                                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                        <label for="send_to_club" class="ml-2 text-sm font-medium text-gray-700">
                                            🏌️ Invia notifica al circolo organizzatore
                                        </label>
                                    </div>
                                    <div class="mt-2 ml-6 text-sm text-gray-600">
                                        Circolo: {{ $tournament->club->name }}
                                        @if ($tournament->club->email)
                                            ({{ $tournament->club->email }})
                                        @else
                                            <span class="text-red-600">⚠️ Email mancante</span>
                                        @endif
                                    </div>
                                </div>
                                <!-- Indirizzi Preimpostati -->
                                @if (isset($groupedEmails) && $groupedEmails->count() > 0)
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-3">
                                            📋 Indirizzi Preimpostati
                                        </label>
                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            @foreach ($groupedEmails as $category => $emails)
                                                <div class="mb-4 last:mb-0">
                                                    <h4 class="font-medium text-gray-900 mb-2">{{ $category }}</h4>
                                                    <div class="space-y-2">
                                                        @foreach ($emails as $email)
                                                            <div class="flex items-center">
                                                                <input type="checkbox" id="fixed_{{ $email->id }}"
                                                                    name="fixed_addresses[]" value="{{ $email->id }}"
                                                                    {{ $email->is_default ? 'checked' : '' }}
                                                                    class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                                                <label for="fixed_{{ $email->id }}"
                                                                    class="ml-2 text-sm text-gray-700">
                                                                    <span class="font-medium">{{ $email->name }}</span>
                                                                    <span
                                                                        class="text-gray-500">({{ $email->email }})</span>
                                                                </label>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                                {{-- Institutional Emails --}}
                                @if ($institutionalEmails && $institutionalEmails->count() > 0)
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-3">
                                            📮 Email Istituzionali
                                        </label>
                                        <div class="space-y-4">
                                            @foreach ($institutionalEmails as $category => $email)
                                                <div class="border border-gray-200 rounded-lg">
                                                    <div class="p-4 space-y-2">
                                                            @if (is_object($email) && isset($email->id))
                                                                <div class="flex items-center">
                                                                    <input type="checkbox" name="institutional_emails[]"
                                                                        value="{{ $email->id }}"
                                                                        id="institutional_{{ $email->id }}"
                                                                        {{ $category === 'convocazioni' ? 'checked' : '' }}
                                                                        class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">

                                                                    <label for="institutional_{{ $email->id }}"
                                                                        class="ml-2 text-sm text-gray-700">
                                                                        <span
                                                                            class="font-medium">{{ $email->name }}</span>
                                                                        <span
                                                                            class="text-gray-500">({{ $email->email }})</span>
                                                                        @if ($email->receive_all_notifications)
                                                                            <span class="text-xs text-blue-600">• Tutte le
                                                                                notifiche</span>
                                                                        @endif
                                                                    </label>
                                                                </div>
                                                            @else
                                                                {{-- DEBUG: Mostra il tipo di valore non valido --}}
                                                                <!-- Email non valida: {{ gettype($email) }} - {{ json_encode($email) }} -->
                                                            @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <!-- Email Aggiuntive -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-3">
                                        ➕ Email Aggiuntive
                                    </label>
                                    <div id="additional-emails-container" class="space-y-3">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <input type="email" name="additional_emails[]"
                                                placeholder="email@esempio.com"
                                                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200">
                                            <div class="flex">
                                                <input type="text" name="additional_names[]"
                                                    placeholder="Nome (opzionale)"
                                                    class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200">
                                                <button type="button" id="add-email-btn"
                                                    class="ml-2 px-3 py-2 bg-indigo-100 text-indigo-700 rounded-md hover:bg-indigo-200">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                {{-- Submit Buttons --}}
                                <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
                                    <a href="{{ route('tournaments.show', $tournament) }}"
                                        class="px-6 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                        Annulla
                                    </a>
                                    <button type="submit"
                                        class="px-6 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 flex items-center">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                        </svg>
                                        Invia Notifiche
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- JavaScript for Dynamic Functionality --}}
    <script>
        // Load template content
        function loadTemplate(templateId) {
            if (!templateId) return;

            const option = document.querySelector(`option[value="${templateId}"]`);
            if (option) {
                document.getElementById('subject').value = option.dataset.subject || '';
                document.getElementById('message').value = option.dataset.body || '';
            }
        }

        // Add additional email field
        function addEmailField() {
            const container = document.getElementById('additional-emails-container');
            const div = document.createElement('div');
            div.className = 'grid grid-cols-1 md:grid-cols-2 gap-3';
            div.innerHTML = `
                <input type="email" name="additional_emails[]" placeholder="email@esempio.com"
                       class="rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200">
                <div class="flex space-x-2">
                    <input type="text" name="additional_names[]" placeholder="Nome (opzionale)"
                           class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200">
                    <button type="button" onclick="this.closest('.grid').remove()"
                            class="px-3 py-2 bg-red-500 text-white rounded-md hover:bg-red-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            `;
            container.appendChild(div);
        }

        // Form validation before submit
        document.querySelector('form').addEventListener('submit', function(e) {
            const recipients = document.querySelectorAll('input[name="recipients[]"]:checked');
            const sendToClub = document.querySelector('input[name="send_to_club"]:checked');
            const institutionalEmails = document.querySelectorAll('input[name="institutional_emails[]"]:checked');
            const additionalEmails = Array.from(document.querySelectorAll('input[name="additional_emails[]"]'))
                .filter(input => input.value.trim() !== '');

            const totalRecipients = recipients.length + (sendToClub ? 1 : 0) + institutionalEmails.length +
                additionalEmails.length;

            if (totalRecipients === 0) {
                e.preventDefault();
                alert('Seleziona almeno un destinatario per la notifica.');
                return false;
            }

            return confirm(`Sei sicuro di voler inviare la notifica a ${totalRecipients} destinatari?`);
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const addEmailBtn = document.getElementById('add-email-btn');
            const additionalEmailsContainer = document.getElementById('additional-emails-container');

            if (addEmailBtn && additionalEmailsContainer) {
                addEmailBtn.addEventListener('click', function() {
                    const newRow = document.createElement('div');
                    newRow.className = 'grid grid-cols-1 md:grid-cols-2 gap-3';
                    newRow.innerHTML = `
                <input type="email"
                       name="additional_emails[]"
                       placeholder="email@esempio.com"
                       class="rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200">
                <div class="flex">
                    <input type="text"
                           name="additional_names[]"
                           placeholder="Nome (opzionale)"
                           class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200">
                    <button type="button" class="remove-email-btn ml-2 px-3 py-2 bg-red-100 text-red-700 rounded-md hover:bg-red-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            `;
                    additionalEmailsContainer.appendChild(newRow);

                    // Add remove functionality to the new button
                    const removeBtn = newRow.querySelector('.remove-email-btn');
                    removeBtn.addEventListener('click', function() {
                        newRow.remove();
                    });
                });

                // Handle existing remove buttons (if any)
                additionalEmailsContainer.addEventListener('click', function(e) {
                    if (e.target.closest('.remove-email-btn')) {
                        e.target.closest('.grid').remove();
                    }
                });
            }
        });
    </script>
@endsection

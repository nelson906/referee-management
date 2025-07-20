@extends('layouts.admin')

@section('title', 'Invia Notifica Assegnazione')

@section('content')
<div class="container mx-auto px-4 py-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center space-x-3">
            <a href="{{ route('notifications.index') }}" class="text-gray-500 hover:text-gray-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h1 class="text-2xl font-bold text-gray-900">Invia Notifica Assegnazione</h1>
        </div>
        <p class="text-gray-600">Invia notifiche email per l'assegnazione di arbitri ai tornei</p>
    </div>

    {{-- Form --}}
    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="{{ route('notifications.send-assignment.post') }}" class="space-y-6">
            @csrf

            {{-- Tournament Selection --}}
            <div>
                <label for="tournament_id" class="block text-sm font-medium text-gray-700 mb-1">Torneo *</label>
                <select name="tournament_id" id="tournament_id"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('tournament_id') border-red-500 @enderror"
                        required onchange="loadTournamentInfo()">
                    <option value="">Seleziona torneo...</option>
                    @foreach($tournaments as $tournament)
                        <option value="{{ $tournament->id }}"
                                data-club="{{ $tournament->club->name }}"
                                data-zone="{{ $tournament->zone->name }}"
                                data-dates="{{ $tournament->date_range }}"
                                data-assignments="{{ $tournament->assignments->count() }}"
                                {{ old('tournament_id') == $tournament->id ? 'selected' : '' }}>
                            {{ $tournament->name }} - {{ $tournament->date_range }}
                        </option>
                    @endforeach
                </select>
                @error('tournament_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Tournament Info --}}
            <div id="tournament-info" class="hidden bg-blue-50 rounded-lg p-4">
                <h4 class="text-sm font-medium text-blue-900 mb-2">📋 Informazioni Torneo</h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <span class="text-blue-700">Circolo:</span>
                        <div id="info-club" class="font-medium text-blue-900"></div>
                    </div>
                    <div>
                        <span class="text-blue-700">Zona:</span>
                        <div id="info-zone" class="font-medium text-blue-900"></div>
                    </div>
                    <div>
                        <span class="text-blue-700">Date:</span>
                        <div id="info-dates" class="font-medium text-blue-900"></div>
                    </div>
                    <div>
                        <span class="text-blue-700">Arbitri assegnati:</span>
                        <div id="info-assignments" class="font-medium text-blue-900"></div>
                    </div>
                </div>
            </div>

            {{-- Template Selection --}}
            <div>
                <label for="template_id" class="block text-sm font-medium text-gray-700 mb-1">Template (opzionale)</label>
                <select name="template_id" id="template_id"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
                        onchange="loadTemplate()">
                    <option value="">Nessun template - scrivi manualmente</option>
                    @foreach($templates as $template)
                        <option value="{{ $template->id }}"
                                data-subject="{{ $template->subject }}"
                                data-body="{{ $template->body }}"
                                {{ old('template_id') == $template->id ? 'selected' : '' }}>
                            {{ $template->name }}
                            @if($template->zone) - {{ $template->zone->name }} @endif
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">
                    Seleziona un template per pre-compilare oggetto e messaggio
                </p>
            </div>

            {{-- Subject --}}
            <div>
                <label for="subject" class="block text-sm font-medium text-gray-700 mb-1">Oggetto Email *</label>
                <input type="text" name="subject" id="subject" value="{{ old('subject') }}"
                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('subject') border-red-500 @enderror"
                       placeholder="Es: Assegnazione torneo {{'tournament_name'}}"
                       required>
                @error('subject')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Message --}}
            <div>
                <label for="message" class="block text-sm font-medium text-gray-700 mb-1">Messaggio *</label>
                <textarea name="message" id="message" rows="10"
                          class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('message') border-red-500 @enderror"
                          placeholder="Scrivi il messaggio dell'email qui..."
                          required>{{ old('message') }}</textarea>
                @error('message')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Recipients --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-3">Destinatari *</label>
                <div class="space-y-3">
                    <div class="flex items-center">
                        <input type="checkbox" name="recipients[]" value="referees" id="recipients_referees"
                               {{ in_array('referees', old('recipients', [])) ? 'checked' : '' }}
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="recipients_referees" class="ml-2 block text-sm text-gray-700">
                            🏌️ Arbitri assegnati al torneo
                            <span class="text-xs text-gray-500 block">Invia a tutti gli arbitri con assegnazioni per questo torneo</span>
                        </label>
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" name="recipients[]" value="club" id="recipients_club"
                               {{ in_array('club', old('recipients', [])) ? 'checked' : '' }}
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="recipients_club" class="ml-2 block text-sm text-gray-700">
                            🏌️ Circolo organizzatore
                            <span class="text-xs text-gray-500 block">Invia al circolo che organizza il torneo</span>
                        </label>
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" name="recipients[]" value="institutional" id="recipients_institutional"
                               {{ in_array('institutional', old('recipients', [])) ? 'checked' : '' }}
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="recipients_institutional" class="ml-2 block text-sm text-gray-700">
                            📮 Email istituzionali
                            <span class="text-xs text-gray-500 block">Invia alle email istituzionali configurate per questa zona</span>
                        </label>
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" name="recipients[]" value="custom" id="recipients_custom"
                               {{ in_array('custom', old('recipients', [])) ? 'checked' : '' }}
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                               onchange="toggleCustomEmails()">
                        <label for="recipients_custom" class="ml-2 block text-sm text-gray-700">
                            ✉️ Email personalizzate
                            <span class="text-xs text-gray-500 block">Aggiungi indirizzi email specifici</span>
                        </label>
                    </div>
                </div>

                @error('recipients')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Custom Emails --}}
            <div id="custom-emails-section" class="hidden">
                <label for="custom_emails" class="block text-sm font-medium text-gray-700 mb-1">Email Personalizzate</label>
                <textarea name="custom_emails" id="custom_emails" rows="3"
                          class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
                          placeholder="email1@example.com, email2@example.com, email3@example.com">{{ old('custom_emails') }}</textarea>
                <p class="mt-1 text-xs text-gray-500">
                    Inserisci gli indirizzi email separati da virgola
                </p>
            </div>

            {{-- Options --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-3">Opzioni</label>
                <div class="flex items-center">
                    <input type="checkbox" name="include_attachments" value="1" id="include_attachments"
                           {{ old('include_attachments') ? 'checked' : '' }}
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label for="include_attachments" class="ml-2 block text-sm text-gray-700">
                        📎 Includi allegati automatici
                        <span class="text-xs text-gray-500 block">Allega convocazioni e documenti del torneo se disponibili</span>
                    </label>
                </div>
            </div>

            {{-- Variables Help --}}
            <div class="bg-green-50 rounded-lg p-4">
                <h4 class="text-sm font-medium text-green-900 mb-2">🔧 Variabili Disponibili</h4>
                <p class="text-xs text-green-700 mb-3">
                    Puoi usare queste variabili nell'oggetto e nel messaggio. Verranno sostituite automaticamente:
                </p>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-xs">
                    @php
                        $variables = [
                            'tournament_name' => 'Nome torneo',
                            'tournament_dates' => 'Date torneo',
                            'club_name' => 'Nome circolo',
                            'club_address' => 'Indirizzo circolo',
                            'referee_name' => 'Nome arbitro',
                            'assignment_role' => 'Ruolo assegnazione',
                            'zone_name' => 'Nome zona',
                            'assigned_date' => 'Data assegnazione',
                        ];
                    @endphp

                    @foreach($variables as $var => $desc)
                        <div class="cursor-pointer hover:bg-green-100 p-1 rounded"
                             onclick="insertVariable('{{$var}}')">
                            <code class="bg-white px-1 py-0.5 rounded text-green-800">{{$var}}</code>
                            <div class="text-green-700">{{ $desc }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex justify-between">
                <a href="{{ route('notifications.index') }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Annulla
                </a>

                <button type="submit"
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                    Invia Notifiche
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Load tournament information
function loadTournamentInfo() {
    const select = document.getElementById('tournament_id');
    const info = document.getElementById('tournament-info');

    if (select.value) {
        const option = select.selectedOptions[0];

        document.getElementById('info-club').textContent = option.dataset.club;
        document.getElementById('info-zone').textContent = option.dataset.zone;
        document.getElementById('info-dates').textContent = option.dataset.dates;
        document.getElementById('info-assignments').textContent = option.dataset.assignments;

        info.classList.remove('hidden');
    } else {
        info.classList.add('hidden');
    }
}

// Load template content
function loadTemplate() {
    const select = document.getElementById('template_id');
    const subjectInput = document.getElementById('subject');
    const messageTextarea = document.getElementById('message');

    if (select.value) {
        const option = select.selectedOptions[0];
        subjectInput.value = option.dataset.subject;
        messageTextarea.value = option.dataset.body;
    }
}

// Toggle custom emails section
function toggleCustomEmails() {
    const checkbox = document.getElementById('recipients_custom');
    const section = document.getElementById('custom-emails-section');

    if (checkbox.checked) {
        section.classList.remove('hidden');
    } else {
        section.classList.add('hidden');
    }
}

// Insert variable in textarea
function insertVariable(variable) {
    const textarea = document.getElementById('message');
    const cursorPos = textarea.selectionStart;
    const textBefore = textarea.value.substring(0, cursorPos);
    const textAfter = textarea.value.substring(cursorPos);

    textarea.value = textBefore + '{{' + variable + '}}' + textAfter;
    textarea.focus();
    textarea.setSelectionRange(cursorPos + variable.length + 4, cursorPos + variable.length + 4);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadTournamentInfo();
    toggleCustomEmails();
});
</script>
@endsection

{{-- File: resources/views/admin/letter-templates/create.blade.php & edit.blade.php --}}
<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                📝 {{ isset($letterTemplate) ? 'Modifica' : 'Nuovo' }} Template Lettera
            </h2>
            <div class="flex space-x-3">
                @if(isset($letterTemplate))
                    <a href="{{ route('letter-templates.preview', $letterTemplate) }}"
                       class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        🔍 Anteprima
                    </a>
                @endif
                <a href="{{ route('letter-templates.index') }}"
                    class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
                    ← Torna all'elenco
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">

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

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                {{-- Main Form --}}
                <div class="lg:col-span-2">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">

                            <form method="POST" action="{{ isset($letterTemplate) ? route('letter-templates.update', $letterTemplate) : route('letter-templates.store') }}" class="space-y-6" id="template-form">
                                @csrf
                                @if(isset($letterTemplate))
                                    @method('PUT')
                                @endif

                                {{-- Basic Info --}}
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                                    {{-- Nome --}}
                                    <div>
                                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                            Nome Template <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" name="name" id="name"
                                               value="{{ old('name', $letterTemplate->name ?? '') }}"
                                               required
                                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 @error('name') border-red-500 @enderror"
                                               placeholder="es. Notifica Assegnazione Standard">
                                        @error('name')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    {{-- Tipologia --}}
                                    <div>
                                        <label for="type" class="block text-sm font-medium text-gray-700 mb-2">
                                            Tipologia <span class="text-red-500">*</span>
                                        </label>
                                        <select name="type" id="type" required
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 @error('type') border-red-500 @enderror">
                                            <option value="">Seleziona tipologia...</option>
                                            @foreach($types as $key => $label)
                                                <option value="{{ $key }}"
                                                        {{ old('type', $letterTemplate->type ?? '') === $key ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('type')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>

                                {{-- Oggetto --}}
                                <div>
                                    <label for="subject" class="block text-sm font-medium text-gray-700 mb-2">
                                        Oggetto Email <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="subject" id="subject"
                                           value="{{ old('subject', $letterTemplate->subject ?? '') }}"
                                           required
                                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 @error('subject') border-red-500 @enderror"
                                           placeholder="es. Assegnazione Arbitri - {{tournament_name}}">
                                    @error('subject')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Contenuto --}}
                                <div>
                                    <label for="body" class="block text-sm font-medium text-gray-700 mb-2">
                                        Contenuto Template <span class="text-red-500">*</span>
                                    </label>
                                    <textarea name="body" id="body" rows="15" required
                                              class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 @error('body') border-red-500 @enderror font-mono text-sm"
                                              placeholder="Scrivi il contenuto del template usando le variabili disponibili...">{{ old('body', $letterTemplate->body ?? '') }}</textarea>
                                    @error('body')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Scope Settings --}}
                                <div class="border-t border-gray-200 pt-6">
                                    <h3 class="text-lg font-medium text-gray-900 mb-4">Ambito di Applicazione</h3>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                                        {{-- Zona --}}
                                        <div>
                                            <label for="zone_id" class="block text-sm font-medium text-gray-700 mb-2">
                                                Zona Specifica
                                            </label>
                                            <select name="zone_id" id="zone_id"
                                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 @error('zone_id') border-red-500 @enderror">
                                                <option value="">Tutte le zone</option>
                                                @foreach($zones as $zone)
                                                    <option value="{{ $zone->id }}"
                                                            {{ old('zone_id', $letterTemplate->zone_id ?? '') == $zone->id ? 'selected' : '' }}>
                                                        {{ $zone->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <div class="mt-1 text-xs text-gray-500">
                                                Lascia vuoto per template globali
                                            </div>
                                            @error('zone_id')
                                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        {{-- Categoria Torneo --}}
                                        <div>
                                            <label for="tournament_type_id" class="block text-sm font-medium text-gray-700 mb-2">
                                                Categoria Torneo
                                            </label>
                                            <select name="tournament_type_id" id="tournament_type_id"
                                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 @error('tournament_type_id') border-red-500 @enderror">
                                                <option value="">Tutte le categorie</option>
                                                @foreach($tournamentTypes as $tournamentType)
                                                    <option value="{{ $tournamentType->id }}"
                                                            {{ old('tournament_type_id', $letterTemplate->tournament_type_id ?? '') == $tournamentType->id ? 'selected' : '' }}>
                                                        {{ $tournamentType->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <div class="mt-1 text-xs text-gray-500">
                                                Opzionale: template specifico per categoria
                                            </div>
                                            @error('tournament_type_id')
                                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                {{-- Options --}}
                                <div class="border-t border-gray-200 pt-6">
                                    <h3 class="text-lg font-medium text-gray-900 mb-4">Opzioni</h3>

                                    <div class="space-y-4">

                                        {{-- Is Active --}}
                                        <div class="flex items-center">
                                            <input type="checkbox" name="is_active" id="is_active" value="1"
                                                   {{ old('is_active', $letterTemplate->is_active ?? true) ? 'checked' : '' }}
                                                   class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                            <label for="is_active" class="ml-2 text-sm font-medium text-gray-700">
                                                ✅ Template attivo
                                            </label>
                                        </div>

                                        {{-- Is Default --}}
                                        <div class="flex items-start">
                                            <div class="flex items-center h-5">
                                                <input type="checkbox" name="is_default" id="is_default" value="1"
                                                       {{ old('is_default', $letterTemplate->is_default ?? false) ? 'checked' : '' }}
                                                       class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                            </div>
                                            <div class="ml-3">
                                                <label for="is_default" class="text-sm font-medium text-gray-700">
                                                    ⭐ Template predefinito
                                                </label>
                                                <div class="text-xs text-gray-500">
                                                    Sarà utilizzato automaticamente quando non viene specificato un template
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Submit Buttons --}}
                                <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
                                    <button type="button" id="preview-btn"
                                            class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                        🔍 Anteprima
                                    </button>
                                    <a href="{{ route('letter-templates.index') }}"
                                       class="px-6 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                        Annulla
                                    </a>
                                    <button type="submit"
                                            class="px-6 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                        {{ isset($letterTemplate) ? '💾 Aggiorna' : '➕ Crea' }} Template
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- Sidebar: Variables & Help --}}
                <div class="lg:col-span-1">

                    {{-- Variables Panel --}}
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">🔧 Variabili Disponibili</h3>
                            <div class="space-y-4">
                                @foreach($variables as $category => $categoryVars)
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-700 mb-2 capitalize">{{ $category }}</h4>
                                        <div class="space-y-1">
                                            @foreach($categoryVars as $variable => $description)
                                                <div class="p-2 bg-gray-50 rounded border cursor-pointer hover:bg-gray-100"
                                                     onclick="insertVariable('{{ $variable }}')">
                                                    <div class="text-xs font-mono text-indigo-600">{{ $variable }}</div>
                                                    <div class="text-xs text-gray-500">{{ $description }}</div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Help Panel --}}
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                        <h3 class="text-lg font-medium text-blue-900 mb-3">💡 Guida ai Template</h3>
                        <div class="text-sm text-blue-800 space-y-2">
                            <p><strong>Tipologie:</strong></p>
                            <ul class="list-disc list-inside ml-4 space-y-1">
                                <li><strong>Assegnazione:</strong> Notifiche di assegnazione arbitri</li>
                                <li><strong>Convocazione:</strong> Convocazioni ufficiali</li>
                                <li><strong>Circolo:</strong> Comunicazioni ai circoli</li>
                                <li><strong>Istituzionale:</strong> Comunicazioni istituzionali</li>
                            </ul>

                            <p class="pt-2"><strong>Variabili:</strong></p>
                            <ul class="list-disc list-inside ml-4 space-y-1">
                                <li>Clicca su una variabile per inserirla</li>
                                <li>Le variabili sono racchiuse tra  '{{'  e  '}}' </li>
                                <li>Verranno sostituite automaticamente con i dati reali</li>
                            </ul>

                            <p class="pt-2"><strong>Ambito:</strong></p>
                            <ul class="list-disc list-inside ml-4 space-y-1">
                                <li><strong>Globale:</strong> Utilizzabile in tutte le zone</li>
                                <li><strong>Zonale:</strong> Solo per la zona specifica</li>
                                <li><strong>Categoria:</strong> Solo per specifiche categorie torneo</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Preview Panel --}}
                    <div id="preview-panel" class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-6 hidden">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">🔍 Anteprima</h3>
                            <div id="preview-content" class="space-y-4">
                                <div>
                                    <h4 class="text-sm font-medium text-gray-700">Oggetto:</h4>
                                    <div id="preview-subject" class="text-sm bg-gray-50 p-2 rounded"></div>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium text-gray-700">Contenuto:</h4>
                                    <div id="preview-body" class="text-sm bg-gray-50 p-2 rounded max-h-64 overflow-y-auto"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- JavaScript for Dynamic Functionality --}}
    <script>
        // Insert variable into the body textarea
        function insertVariable(variable) {
            const bodyTextarea = document.getElementById('body');
            const startPos = bodyTextarea.selectionStart;
            const endPos = bodyTextarea.selectionEnd;
            const textBefore = bodyTextarea.value.substring(0, startPos);
            const textAfter = bodyTextarea.value.substring(endPos);

            bodyTextarea.value = textBefore + variable + textAfter;
            bodyTextarea.focus();
            bodyTextarea.setSelectionRange(startPos + variable.length, startPos + variable.length);
        }

        // Preview functionality
        document.getElementById('preview-btn').addEventListener('click', function() {
            const subject = document.getElementById('subject').value;
            const body = document.getElementById('body').value;

            // Sample data for preview
            const sampleData = {
                '{{tournament_name}}': 'Campionato Regionale di Golf',
                '{{tournament_dates}}': '15/06/2024 - 16/06/2024',
                '{{tournament_category}}': 'Torneo Nazionale',
                '{{club_name}}': 'Golf Club Roma',
                '{{club_address}}': 'Via del Golf, 123 - Roma',
                '{{club_phone}}': '+39 06 1234567',
                '{{club_email}}': 'info@golfclub.roma.it',
                '{{zone_name}}': 'Zona Lazio',
                '{{referee_name}}': 'Mario Rossi',
                '{{referee_email}}': 'mario.rossi@email.com',
                '{{referee_phone}}': '+39 333 1234567',
                '{{assignment_role}}': 'Direttore di Torneo',
                '{{assigned_date}}': new Date().toLocaleDateString('it-IT')
            };

            let previewSubject = subject;
            let previewBody = body;

            // Replace variables with sample data
            Object.keys(sampleData).forEach(variable => {
                const regex = new RegExp(variable.replace(/[{}]/g, '\\$&'), 'g');
                previewSubject = previewSubject.replace(regex, sampleData[variable]);
                previewBody = previewBody.replace(regex, sampleData[variable]);
            });

            // Show preview
            document.getElementById('preview-subject').textContent = previewSubject;
            document.getElementById('preview-body').innerHTML = previewBody.replace(/\n/g, '<br>');
            document.getElementById('preview-panel').classList.remove('hidden');

            // Scroll to preview
            document.getElementById('preview-panel').scrollIntoView({ behavior: 'smooth' });
        });

        // Auto-resize textarea
        document.getElementById('body').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });

        // Initialize textarea height
        document.addEventListener('DOMContentLoaded', function() {
            const bodyTextarea = document.getElementById('body');
            bodyTextarea.style.height = 'auto';
            bodyTextarea.style.height = (bodyTextarea.scrollHeight) + 'px';
        });
    </script>
</x-admin-layout>

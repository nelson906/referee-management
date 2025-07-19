{{-- File: resources/views/admin/letter-templates/preview.blade.php --}}
<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                🔍 Anteprima Template: {{ $letterTemplate->name }}
            </h2>
            <div class="flex space-x-3">
                <a href="{{ route('letter-templates.edit', $letterTemplate) }}"
                   class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                    ✏️ Modifica
                </a>
                <a href="{{ route('letter-templates.index') }}"
                    class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
                    ← Torna all'elenco
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                {{-- Main Preview --}}
                <div class="lg:col-span-2">

                    {{-- Template Info --}}
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900 mb-3">📋 Informazioni Template</h3>
                                    <dl class="space-y-2 text-sm">
                                        <div>
                                            <dt class="text-gray-600">Nome:</dt>
                                            <dd class="font-medium text-gray-900">{{ $letterTemplate->name }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-gray-600">Tipologia:</dt>
                                            <dd class="font-medium text-gray-900">
                                                <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full
                                                    {{ $letterTemplate->type === 'assignment' ? 'bg-blue-100 text-blue-800' : '' }}
                                                    {{ $letterTemplate->type === 'convocation' ? 'bg-green-100 text-green-800' : '' }}
                                                    {{ $letterTemplate->type === 'club' ? 'bg-purple-100 text-purple-800' : '' }}
                                                    {{ $letterTemplate->type === 'institutional' ? 'bg-yellow-100 text-yellow-800' : '' }}">
                                                    {{ $letterTemplate->type_label }}
                                                </span>
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-gray-600">Ambito:</dt>
                                            <dd class="font-medium text-gray-900">{{ $letterTemplate->scope_label }}</dd>
                                        </div>
                                        @if($letterTemplate->zone)
                                            <div>
                                                <dt class="text-gray-600">Zona:</dt>
                                                <dd class="font-medium text-gray-900">{{ $letterTemplate->zone->name }}</dd>
                                            </div>
                                        @endif
                                        @if($letterTemplate->tournamentType)
                                            <div>
                                                <dt class="text-gray-600">Categoria Torneo:</dt>
                                                <dd class="font-medium text-gray-900">{{ $letterTemplate->tournamentType->name }}</dd>
                                            </div>
                                        @endif
                                    </dl>
                                </div>

                                <div>
                                    <h3 class="text-lg font-medium text-gray-900 mb-3">📊 Stato e Statistiche</h3>
                                    <dl class="space-y-2 text-sm">
                                        <div>
                                            <dt class="text-gray-600">Stato:</dt>
                                            <dd>
                                                <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full
                                                    {{ $letterTemplate->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                    {{ $letterTemplate->status_label }}
                                                </span>
                                                @if($letterTemplate->is_default)
                                                    <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-orange-100 text-orange-800 ml-1">
                                                        ⭐ Predefinito
                                                    </span>
                                                @endif
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-gray-600">Creato:</dt>
                                            <dd class="font-medium text-gray-900">{{ $letterTemplate->created_at->format('d/m/Y H:i') }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-gray-600">Ultima modifica:</dt>
                                            <dd class="font-medium text-gray-900">{{ $letterTemplate->updated_at->format('d/m/Y H:i') }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-gray-600">Variabili utilizzate:</dt>
                                            <dd class="font-medium text-gray-900">{{ count($letterTemplate->used_variables) }}</dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Email Preview --}}
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-6">
                                <h3 class="text-lg font-medium text-gray-900">📧 Anteprima Email</h3>
                                <div class="flex space-x-2">
                                    <button onclick="toggleRawView()" id="toggle-btn"
                                            class="px-3 py-1 text-xs bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                                        📝 Mostra Sorgente
                                    </button>
                                    <button onclick="printPreview()"
                                            class="px-3 py-1 text-xs bg-blue-100 text-blue-700 rounded hover:bg-blue-200">
                                        🖨️ Stampa
                                    </button>
                                </div>
                            </div>

                            {{-- Email Header --}}
                            <div class="border border-gray-300 rounded-lg overflow-hidden">
                                <div class="bg-gray-50 px-4 py-3 border-b border-gray-300">
                                    <div class="space-y-1 text-sm">
                                        <div class="flex">
                                            <span class="font-medium text-gray-700 w-16">Da:</span>
                                            <span class="text-gray-900">{{ config('mail.from.name') }} &lt;{{ config('mail.from.address') }}&gt;</span>
                                        </div>
                                        <div class="flex">
                                            <span class="font-medium text-gray-700 w-16">A:</span>
                                            <span class="text-gray-900">mario.rossi@email.com</span>
                                        </div>
                                        <div class="flex">
                                            <span class="font-medium text-gray-700 w-16">Oggetto:</span>
                                            <span class="text-gray-900 font-medium">{{ $previewSubject }}</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Email Body --}}
                                <div class="p-6">
                                    <div id="preview-rendered" class="prose max-w-none">
                                        {!! nl2br(e($previewBody)) !!}
                                    </div>

                                    <div id="preview-raw" class="hidden">
                                        <div class="mb-4">
                                            <h4 class="text-sm font-medium text-gray-700 mb-2">Oggetto (sorgente):</h4>
                                            <div class="bg-gray-100 p-3 rounded font-mono text-sm">{{ $letterTemplate->subject }}</div>
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-medium text-gray-700 mb-2">Contenuto (sorgente):</h4>
                                            <div class="bg-gray-100 p-3 rounded font-mono text-sm whitespace-pre-wrap">{{ $letterTemplate->body }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Sidebar: Variables & Sample Data --}}
                <div class="lg:col-span-1">

                    {{-- Variables Used --}}
                    @if($letterTemplate->used_variables)
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">🔧 Variabili Utilizzate</h3>
                                <div class="space-y-2">
                                    @foreach($letterTemplate->used_variables as $variable => $description)
                                        <div class="p-2 bg-indigo-50 rounded border border-indigo-200">
                                            <div class="text-xs font-mono text-indigo-600">{{ $variable }}</div>
                                            <div class="text-xs text-indigo-800">{{ $description }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Sample Data --}}
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">📋 Dati di Esempio</h3>
                            <div class="space-y-3 text-sm">
                                @foreach($sampleData as $variable => $value)
                                    <div class="flex flex-col">
                                        <span class="font-mono text-xs text-indigo-600">{{ $variable }}</span>
                                        <span class="text-gray-900 font-medium">{{ $value }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Validation Results --}}
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">✅ Validazione Template</h3>

                            @php
                                $validationErrors = $letterTemplate->validateVariables();
                            @endphp

                            @if(empty($validationErrors))
                                <div class="flex items-center p-3 bg-green-50 border border-green-200 rounded-lg">
                                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-green-800">Template valido</p>
                                        <p class="text-xs text-green-600">Tutte le variabili sono riconosciute</p>
                                    </div>
                                </div>
                            @else
                                <div class="space-y-2">
                                    @foreach($validationErrors as $error)
                                        <div class="flex items-center p-3 bg-red-50 border border-red-200 rounded-lg">
                                            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                            </svg>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium text-red-800">{{ $error }}</p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Quick Actions --}}
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                        <h3 class="text-lg font-medium text-blue-900 mb-3">⚡ Azioni Rapide</h3>
                        <div class="space-y-2">
                            <a href="{{ route('letter-templates.edit', $letterTemplate) }}"
                               class="w-full inline-flex justify-center px-3 py-2 border border-blue-300 shadow-sm text-sm font-medium rounded-md text-blue-700 bg-white hover:bg-blue-50">
                                ✏️ Modifica Template
                            </a>

                            <form method="POST" action="{{ route('letter-templates.duplicate', $letterTemplate) }}" class="w-full">
                                @csrf
                                <button type="submit"
                                        class="w-full inline-flex justify-center px-3 py-2 border border-blue-300 shadow-sm text-sm font-medium rounded-md text-blue-700 bg-white hover:bg-blue-50">
                                    📋 Duplica Template
                                </button>
                            </form>

                            <form method="POST" action="{{ route('letter-templates.toggle', $letterTemplate) }}" class="w-full">
                                @csrf
                                <button type="submit"
                                        class="w-full inline-flex justify-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    {{ $letterTemplate->is_active ? '⏸️ Disattiva' : '▶️ Attiva' }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- JavaScript for Preview Functionality --}}
    <script>
        function toggleRawView() {
            const rendered = document.getElementById('preview-rendered');
            const raw = document.getElementById('preview-raw');
            const toggleBtn = document.getElementById('toggle-btn');

            if (raw.classList.contains('hidden')) {
                // Show raw view
                rendered.classList.add('hidden');
                raw.classList.remove('hidden');
                toggleBtn.textContent = '📧 Mostra Anteprima';
            } else {
                // Show rendered view
                raw.classList.add('hidden');
                rendered.classList.remove('hidden');
                toggleBtn.textContent = '📝 Mostra Sorgente';
            }
        }

        function printPreview() {
            const printWindow = window.open('', '_blank');
            const previewContent = document.getElementById('preview-rendered').innerHTML;

            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Anteprima Template: {{ $letterTemplate->name }}</title>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            line-height: 1.6;
                            max-width: 800px;
                            margin: 0 auto;
                            padding: 20px;
                        }
                        .header {
                            border-bottom: 2px solid #ddd;
                            padding-bottom: 10px;
                            margin-bottom: 20px;
                        }
                        @media print {
                            body { margin: 0; padding: 15px; }
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h2>{{ $letterTemplate->name }}</h2>
                        <p><strong>Oggetto:</strong> {{ $previewSubject }}</p>
                        <p><strong>Data anteprima:</strong> ${new Date().toLocaleDateString('it-IT')}</p>
                    </div>
                    ${previewContent}
                </body>
                </html>
            `);

            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
        }
    </script>
</x-admin-layout>

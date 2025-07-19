<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                📮 {{ isset($institutionalEmail) ? 'Modifica' : 'Nuova' }} Email Istituzionale
            </h2>
            <a href="{{ route('institutional-emails.index') }}"
                class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
                ← Torna all'elenco
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

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

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">

                    <form method="POST" action="{{ isset($institutionalEmail) ? route('institutional-emails.update', $institutionalEmail) : route('institutional-emails.store') }}" class="space-y-6">
                        @csrf
                        @if(isset($institutionalEmail))
                            @method('PUT')
                        @endif

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                            {{-- Nome --}}
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                    Nome Organizzazione <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="name" id="name"
                                       value="{{ old('name', $institutionalEmail->name ?? '') }}"
                                       required
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 @error('name') border-red-500 @enderror"
                                       placeholder="es. Federazione Italiana Golf">
                                @error('name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Email --}}
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                    Indirizzo Email <span class="text-red-500">*</span>
                                </label>
                                <input type="email" name="email" id="email"
                                       value="{{ old('email', $institutionalEmail->email ?? '') }}"
                                       required
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 @error('email') border-red-500 @enderror"
                                       placeholder="email@organizzazione.it">
                                @error('email')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Descrizione --}}
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                                Descrizione
                            </label>
                            <textarea name="description" id="description" rows="3"
                                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 @error('description') border-red-500 @enderror"
                                      placeholder="Descrizione dell'organizzazione e del suo ruolo...">{{ old('description', $institutionalEmail->description ?? '') }}</textarea>
                            @error('description')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                            {{-- Categoria --}}
                            <div>
                                <label for="category" class="block text-sm font-medium text-gray-700 mb-2">
                                    Categoria <span class="text-red-500">*</span>
                                </label>
                                <select name="category" id="category" required
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 @error('category') border-red-500 @enderror">
                                    <option value="">Seleziona categoria...</option>
                                    @foreach($categories as $key => $label)
                                        <option value="{{ $key }}"
                                                {{ old('category', $institutionalEmail->category ?? '') === $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('category')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Zona --}}
                            <div>
                                <label for="zone_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Zona di Competenza
                                </label>
                                <select name="zone_id" id="zone_id"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 @error('zone_id') border-red-500 @enderror">
                                    <option value="">Tutte le zone</option>
                                    @foreach($zones as $zone)
                                        <option value="{{ $zone->id }}"
                                                {{ old('zone_id', $institutionalEmail->zone_id ?? '') == $zone->id ? 'selected' : '' }}>
                                            {{ $zone->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="mt-1 text-xs text-gray-500">
                                    Lascia vuoto per email globali che ricevono notifiche da tutte le zone
                                </div>
                                @error('zone_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Opzioni Notifiche --}}
                        <div class="border-t border-gray-200 pt-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Impostazioni Notifiche</h3>

                            <div class="space-y-4">

                                {{-- Ricevi Tutte le Notifiche --}}
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input type="checkbox" name="receive_all_notifications" id="receive_all_notifications"
                                               value="1"
                                               {{ old('receive_all_notifications', $institutionalEmail->receive_all_notifications ?? false) ? 'checked' : '' }}
                                               class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                               onchange="toggleNotificationTypes(this.checked)">
                                    </div>
                                    <div class="ml-3">
                                        <label for="receive_all_notifications" class="text-sm font-medium text-gray-700">
                                            🌍 Ricevi tutte le notifiche
                                        </label>
                                        <div class="text-xs text-gray-500">
                                            Questa email riceverà automaticamente tutte le tipologie di notifiche da tutte le zone
                                        </div>
                                    </div>
                                </div>

                                {{-- Tipi di Notifica Specifici --}}
                                <div id="notification-types-section" class="{{ old('receive_all_notifications', $institutionalEmail->receive_all_notifications ?? false) ? 'hidden' : '' }}">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        📋 Tipi di Notifica Specifici
                                    </label>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        @foreach(\App\Models\InstitutionalEmail::NOTIFICATION_TYPES as $key => $label)
                                            <div class="flex items-center">
                                                <input type="checkbox" name="notification_types[]" value="{{ $key }}"
                                                       id="notification_type_{{ $key }}"
                                                       {{ in_array($key, old('notification_types', $institutionalEmail->notification_types ?? [])) ? 'checked' : '' }}
                                                       class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                                <label for="notification_type_{{ $key }}" class="ml-2 text-sm text-gray-700">
                                                    {{ $label }}
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="mt-2 text-xs text-gray-500">
                                        Seleziona solo i tipi di notifica che questa email deve ricevere
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Stato --}}
                        <div class="border-t border-gray-200 pt-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Stato</h3>

                            <div class="flex items-center">
                                <input type="checkbox" name="is_active" id="is_active" value="1"
                                       {{ old('is_active', $institutionalEmail->is_active ?? true) ? 'checked' : '' }}
                                       class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="is_active" class="ml-2 text-sm font-medium text-gray-700">
                                    ✅ Email attiva
                                </label>
                            </div>
                            <div class="mt-1 text-xs text-gray-500">
                                Solo le email attive riceveranno le notifiche
                            </div>
                        </div>

                        {{-- Submit Buttons --}}
                        <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
                            <a href="{{ route('institutional-emails.index') }}"
                               class="px-6 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                Annulla
                            </a>
                            <button type="submit"
                                    class="px-6 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                {{ isset($institutionalEmail) ? '💾 Aggiorna' : '➕ Crea' }} Email
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Help Panel --}}
            <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-6">
                <h3 class="text-lg font-medium text-blue-900 mb-3">💡 Guida alle Email Istituzionali</h3>
                <div class="text-sm text-blue-800 space-y-2">
                    <p><strong>Categorie:</strong></p>
                    <ul class="list-disc list-inside ml-4 space-y-1">
                        <li><strong>Federazione:</strong> Email della Federazione Italiana Golf</li>
                        <li><strong>Comitati:</strong> Comitati regionali e nazionali</li>
                        <li><strong>Zone:</strong> Sezioni zonali regole specifiche</li>
                        <li><strong>Convocazioni:</strong> Email dedicate alle convocazioni</li>
                        <li><strong>Arbitri:</strong> Comunicazioni specifiche per arbitri</li>
                    </ul>

                    <p class="pt-2"><strong>Ambito:</strong></p>
                    <ul class="list-disc list-inside ml-4 space-y-1">
                        <li><strong>Globale:</strong> Riceve tutte le notifiche da tutte le zone</li>
                        <li><strong>Zonale:</strong> Riceve solo notifiche dalla zona specifica</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    {{-- JavaScript for Dynamic Behavior --}}
    <script>
        function toggleNotificationTypes(receiveAll) {
            const section = document.getElementById('notification-types-section');
            const checkboxes = section.querySelectorAll('input[type="checkbox"]');

            if (receiveAll) {
                section.classList.add('hidden');
                // Uncheck all specific notification types
                checkboxes.forEach(checkbox => checkbox.checked = false);
            } else {
                section.classList.remove('hidden');
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            const receiveAllCheckbox = document.getElementById('receive_all_notifications');
            toggleNotificationTypes(receiveAllCheckbox.checked);
        });
    </script>
</x-admin-layout>

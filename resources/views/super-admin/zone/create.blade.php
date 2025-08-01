@extends('layouts.super-admin')

@section('title', 'Nuova Zona')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl">
    {{-- Header --}}
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Nuova Zona</h1>
                <p class="mt-2 text-gray-600">Crea una nuova zona territoriale</p>
            </div>
            <a href="{{ route('super-admin.zones.index') }}"
               class="text-gray-600 hover:text-gray-900 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Torna all'elenco
            </a>
        </div>
    </div>

    {{-- Form --}}
    <form action="{{ route('super-admin.zones.store') }}" method="POST" class="space-y-6">
        @csrf

        {{-- Basic Information --}}
        <div class="bg-white shadow-sm rounded-lg p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Informazioni Base</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">
                        Nome Zona <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name" id="name"
                           value="{{ old('name') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('name') border-red-500 @enderror"
                           required>
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="code" class="block text-sm font-medium text-gray-700">
                        Codice Zona <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="code" id="code"
                           value="{{ old('code') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm uppercase @error('code') border-red-500 @enderror"
                           pattern="[A-Za-z0-9_-]+"
                           title="Solo lettere, numeri, trattini e underscore"
                           required>
                    <p class="mt-1 text-xs text-gray-500">Es: CAM, LOM, VEN, SIC</p>
                    @error('code')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="region" class="block text-sm font-medium text-gray-700">
                        Regione <span class="text-red-500">*</span>
                    </label>
                    <select name="region" id="region"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('region') border-red-500 @enderror"
                            required>
                        <option value="">Seleziona regione</option>
                        <option value="Abruzzo" {{ old('region') === 'Abruzzo' ? 'selected' : '' }}>Abruzzo</option>
                        <option value="Basilicata" {{ old('region') === 'Basilicata' ? 'selected' : '' }}>Basilicata</option>
                        <option value="Calabria" {{ old('region') === 'Calabria' ? 'selected' : '' }}>Calabria</option>
                        <option value="Campania" {{ old('region') === 'Campania' ? 'selected' : '' }}>Campania</option>
                        <option value="Emilia-Romagna" {{ old('region') === 'Emilia-Romagna' ? 'selected' : '' }}>Emilia-Romagna</option>
                        <option value="Friuli-Venezia Giulia" {{ old('region') === 'Friuli-Venezia Giulia' ? 'selected' : '' }}>Friuli-Venezia Giulia</option>
                        <option value="Lazio" {{ old('region') === 'Lazio' ? 'selected' : '' }}>Lazio</option>
                        <option value="Liguria" {{ old('region') === 'Liguria' ? 'selected' : '' }}>Liguria</option>
                        <option value="Lombardia" {{ old('region') === 'Lombardia' ? 'selected' : '' }}>Lombardia</option>
                        <option value="Marche" {{ old('region') === 'Marche' ? 'selected' : '' }}>Marche</option>
                        <option value="Molise" {{ old('region') === 'Molise' ? 'selected' : '' }}>Molise</option>
                        <option value="Piemonte" {{ old('region') === 'Piemonte' ? 'selected' : '' }}>Piemonte</option>
                        <option value="Puglia" {{ old('region') === 'Puglia' ? 'selected' : '' }}>Puglia</option>
                        <option value="Sardegna" {{ old('region') === 'Sardegna' ? 'selected' : '' }}>Sardegna</option>
                        <option value="Sicilia" {{ old('region') === 'Sicilia' ? 'selected' : '' }}>Sicilia</option>
                        <option value="Toscana" {{ old('region') === 'Toscana' ? 'selected' : '' }}>Toscana</option>
                        <option value="Trentino-Alto Adige" {{ old('region') === 'Trentino-Alto Adige' ? 'selected' : '' }}>Trentino-Alto Adige</option>
                        <option value="Umbria" {{ old('region') === 'Umbria' ? 'selected' : '' }}>Umbria</option>
                        <option value="Valle d'Aosta" {{ old('region') === "Valle d'Aosta" ? 'selected' : '' }}>Valle d'Aosta</option>
                        <option value="Veneto" {{ old('region') === 'Veneto' ? 'selected' : '' }}>Veneto</option>
                    </select>
                    @error('region')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="sort_order" class="block text-sm font-medium text-gray-700">
                        Ordine Visualizzazione
                    </label>
                    <input type="number" name="sort_order" id="sort_order"
                           value="{{ old('sort_order', 0) }}"
                           min="0"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('sort_order') border-red-500 @enderror">
                    <p class="mt-1 text-xs text-gray-500">Ordine crescente (0 = primo)</p>
                    @error('sort_order')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="col-span-2">
                    <label for="description" class="block text-sm font-medium text-gray-700">
                        Descrizione
                    </label>
                    <textarea name="description" id="description" rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('description') border-red-500 @enderror">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Contact Information --}}
        <div class="bg-white shadow-sm rounded-lg p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Informazioni di Contatto</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="contact_email" class="block text-sm font-medium text-gray-700">
                        Email di Contatto
                    </label>
                    <input type="email" name="contact_email" id="contact_email"
                           value="{{ old('contact_email') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('contact_email') border-red-500 @enderror">
                    @error('contact_email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="contact_phone" class="block text-sm font-medium text-gray-700">
                        Telefono di Contatto
                    </label>
                    <input type="tel" name="contact_phone" id="contact_phone"
                           value="{{ old('contact_phone') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('contact_phone') border-red-500 @enderror">
                    @error('contact_phone')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="col-span-2">
                    <label for="address" class="block text-sm font-medium text-gray-700">
                        Indirizzo Sede
                    </label>
                    <input type="text" name="address" id="address"
                           value="{{ old('address') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('address') border-red-500 @enderror">
                    @error('address')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="city" class="block text-sm font-medium text-gray-700">
                        Città
                    </label>
                    <input type="text" name="city" id="city"
                           value="{{ old('city') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('city') border-red-500 @enderror">
                    @error('city')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="postal_code" class="block text-sm font-medium text-gray-700">
                        CAP
                    </label>
                    <input type="text" name="postal_code" id="postal_code"
                           value="{{ old('postal_code') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('postal_code') border-red-500 @enderror">
                    @error('postal_code')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="col-span-2">
                    <label for="website" class="block text-sm font-medium text-gray-700">
                        Sito Web
                    </label>
                    <input type="url" name="website" id="website"
                           value="{{ old('website') }}"
                           placeholder="https://www.esempio.it"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('website') border-red-500 @enderror">
                    @error('website')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Administrator Assignment --}}
        <div class="bg-white shadow-sm rounded-lg p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Amministratore Zona</h2>

            <div>
                <label for="admin_id" class="block text-sm font-medium text-gray-700">
                    Assegna Amministratore
                </label>
                <select name="admin_id" id="admin_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('admin_id') border-red-500 @enderror">
                    <option value="">Seleziona un amministratore (opzionale)</option>
                    @foreach($admins as $admin)
                        <option value="{{ $admin->id }}" {{ old('admin_id') == $admin->id ? 'selected' : '' }}>
                            {{ $admin->name }} ({{ $admin->email }})
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">
                    Solo amministratori zona non ancora assegnati ad altre zone.
                    Puoi creare l'amministratore successivamente se necessario.
                </p>
                @error('admin_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Advanced Settings --}}
        <div class="bg-white shadow-sm rounded-lg p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Impostazioni Avanzate</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="coordinates" class="block text-sm font-medium text-gray-700">
                        Coordinate GPS
                    </label>
                    <input type="text" name="coordinates" id="coordinates"
                           value="{{ old('coordinates') }}"
                           placeholder="40.8518, 14.2681"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('coordinates') border-red-500 @enderror">
                    <p class="mt-1 text-xs text-gray-500">Formato: latitudine, longitudine</p>
                    @error('coordinates')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-4">
                    <div class="flex items-center">
                        <input type="checkbox" name="is_active" id="is_active" value="1"
                               {{ old('is_active', true) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <label for="is_active" class="ml-2 text-sm text-gray-700">
                            Zona Attiva
                        </label>
                    </div>
                    <p class="text-xs text-gray-500">Le zone non attive non sono visibili agli utenti</p>
                </div>
            </div>

            {{-- Custom Settings --}}
            <div class="mt-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Impostazioni Personalizzate (JSON)
                </label>
                <textarea name="settings" id="settings" rows="4"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-mono text-xs @error('settings') border-red-500 @enderror"
                          placeholder='{"max_tournaments_per_month": 10, "auto_assign_referees": false}'>{{ old('settings', '{}') }}</textarea>
                <p class="mt-1 text-xs text-gray-500">
                    Impostazioni avanzate in formato JSON. Lascia vuoto per usare le impostazioni predefinite.
                </p>
                @error('settings')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex justify-end space-x-4">
            <a href="{{ route('super-admin.zones.index') }}"
               class="px-6 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Annulla
            </a>
            <button type="submit"
                    class="px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Crea Zona
            </button>
        </div>
    </form>

    {{-- Help Box --}}
    <div class="mt-8 bg-gray-50 rounded-lg p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Guida alla creazione delle zone</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm text-gray-600">
            <div>
                <h4 class="font-medium text-gray-900 mb-2">📍 Informazioni Base</h4>
                <ul class="space-y-1 list-disc list-inside">
                    <li>Il nome deve essere chiaro e identificativo</li>
                    <li>Il codice deve essere univoco (es: CAM per Campania)</li>
                    <li>La regione determina l'area geografica</li>
                </ul>
            </div>
            <div>
                <h4 class="font-medium text-gray-900 mb-2">👤 Amministratore</h4>
                <ul class="space-y-1 list-disc list-inside">
                    <li>Puoi assegnare un amministratore esistente</li>
                    <li>O crearlo successivamente dalla gestione utenti</li>
                    <li>L'amministratore gestirà tornei e arbitri della zona</li>
                </ul>
            </div>
            <div>
                <h4 class="font-medium text-gray-900 mb-2">⚙️ Impostazioni</h4>
                <ul class="space-y-1 list-disc list-inside">
                    <li>L'ordine determina la posizione negli elenchi</li>
                    <li>Le coordinate GPS possono essere utili per mappe</li>
                    <li>Le impostazioni JSON permettono configurazioni avanzate</li>
                </ul>
            </div>
            <div>
                <h4 class="font-medium text-gray-900 mb-2">📞 Contatti</h4>
                <ul class="space-y-1 list-disc list-inside">
                    <li>Email e telefono per comunicazioni ufficiali</li>
                    <li>Indirizzo sede per corrispondenza</li>
                    <li>Sito web per informazioni pubbliche</li>
                </ul>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Auto uppercase for code
document.getElementById('code').addEventListener('input', function() {
    this.value = this.value.toUpperCase();
});

// Validate JSON settings
document.getElementById('settings').addEventListener('blur', function() {
    if (this.value.trim() === '') {
        this.value = '{}';
        return;
    }

    try {
        JSON.parse(this.value);
        this.classList.remove('border-red-500');
    } catch (e) {
        this.classList.add('border-red-500');
        alert('Formato JSON non valido. Controlla la sintassi.');
    }
});

// Auto-fill coordinates (placeholder functionality)
document.getElementById('city').addEventListener('blur', function() {
    // Here you could integrate with a geocoding service
    // to automatically fill coordinates based on city
});
</script>
@endpush
@endsection

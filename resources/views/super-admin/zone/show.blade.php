@extends('layouts.super-admin')

@section('title', 'Modifica Zona: ' . $zone->name)

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl">
    {{-- Header --}}
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Modifica Zona</h1>
                <p class="mt-2 text-gray-600">Modifica le impostazioni della zona: {{ $zone->name }}</p>
            </div>
            <div class="flex space-x-4">
                <a href="{{ route('super-admin.zones.show', $zone) }}"
                   class="text-gray-600 hover:text-gray-900 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                    Visualizza
                </a>
                <a href="{{ route('super-admin.zones.index') }}"
                   class="text-gray-600 hover:text-gray-900 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Torna all'elenco
                </a>
            </div>
        </div>
    </div>

    {{-- Alert if zone has content --}}
    @if($zone->users()->exists() || $zone->tournaments()->exists() || $zone->clubs()->exists())
    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-yellow-700">
                    <strong>Attenzione:</strong> Questa zona ha {{ $zone->users()->count() }} utenti,
                    {{ $zone->tournaments()->count() }} tornei e {{ $zone->clubs()->count() }} clubs associati.
                    Le modifiche influenzeranno tutti i dati esistenti.
                </p>
            </div>
        </div>
    </div>
    @endif

    {{-- Form --}}
    <form action="{{ route('super-admin.zones.update', $zone) }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Basic Information --}}
        <div class="bg-white shadow-sm rounded-lg p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Informazioni Base</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">
                        Nome Zona <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name" id="name"
                           value="{{ old('name', $zone->name) }}"
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
                           value="{{ old('code', $zone->code) }}"
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
                        @foreach(['Abruzzo', 'Basilicata', 'Calabria', 'Campania', 'Emilia-Romagna', 'Friuli-Venezia Giulia', 'Lazio', 'Liguria', 'Lombardia', 'Marche', 'Molise', 'Piemonte', 'Puglia', 'Sardegna', 'Sicilia', 'Toscana', 'Trentino-Alto Adige', 'Umbria', "Valle d'Aosta", 'Veneto'] as $regionName)
                            <option value="{{ $regionName }}" {{ old('region', $zone->region) === $regionName ? 'selected' : '' }}>
                                {{ $regionName }}
                            </option>
                        @endforeach
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
                           value="{{ old('sort_order', $zone->sort_order) }}"
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
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('description') border-red-500 @enderror">{{ old('description', $zone->description) }}</textarea>
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
                           value="{{ old('contact_email', $zone->contact_email) }}"
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
                           value="{{ old('contact_phone', $zone->contact_phone) }}"
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
                           value="{{ old('address', $zone->address) }}"
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
                           value="{{ old('city', $zone->city) }}"
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
                           value="{{ old('postal_code', $zone->postal_code) }}"
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
                           value="{{ old('website', $zone->website) }}"
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

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="admin_id" class="block text-sm font-medium text-gray-700">
                        Assegna Amministratore
                    </label>
                    <select name="admin_id" id="admin_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('admin_id') border-red-500 @enderror">
                        <option value="">Nessun amministratore</option>
                        @foreach($admins as $admin)
                            <option value="{{ $admin->id }}"
                                    {{ old('admin_id', $currentAdmin?->id) == $admin->id ? 'selected' : '' }}>
                                {{ $admin->name }} ({{ $admin->email }})
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">
                        Solo amministratori zona non ancora assegnati ad altre zone.
                    </p>
                    @error('admin_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                @if($currentAdmin)
                <div>
                    <label class="block text-sm font-medium text-gray-700">Amministratore Attuale</label>
                    <div class="mt-1 p-3 bg-gray-50 rounded-md">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-8 w-8">
                                @if($currentAdmin->profile_photo_path)
                                    <img class="h-8 w-8 rounded-full object-cover" src="{{ Storage::url($currentAdmin->profile_photo_path) }}" alt="">
                                @else
                                    <div class="h-8 w-8 rounded-full bg-indigo-500 flex items-center justify-center">
                                        <span class="text-white font-medium text-xs">{{ substr($currentAdmin->name, 0, 1) }}</span>
                                    </div>
                                @endif
                            </div>
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">{{ $currentAdmin->name }}</div>
                                <div class="text-xs text-gray-500">{{ $currentAdmin->email }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
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
                           value="{{ old('coordinates', $zone->coordinates) }}"
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
                               {{ old('is_active', $zone->is_active) ? 'checked' : '' }}
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
                          placeholder='{"max_tournaments_per_month": 10, "auto_assign_referees": false}'>{{ old('settings', json_encode($zone->settings ?? [], JSON_PRETTY_PRINT)) }}</textarea>
                <p class="mt-1 text-xs text-gray-500">
                    Impostazioni avanzate in formato JSON.
                </p>
                @error('settings')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex justify-between">
            <div>
                @if($zone->users()->count() == 0 && $zone->tournaments()->count() == 0 && $zone->clubs()->count() == 0)
                <button type="button" onclick="confirmDelete()"
                        class="px-4 py-2 border border-red-300 rounded-md shadow-sm text-sm font-medium text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    Elimina Zona
                </button>
                @endif
            </div>
            <div class="flex space-x-4">
                <a href="{{ route('super-admin.zones.index') }}"
                   class="px-6 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Annulla
                </a>
                <button type="submit"
                        class="px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Salva Modifiche
                </button>
            </div>
        </div>
    </form>

    {{-- Zone Statistics --}}
    <div class="mt-8 bg-gray-50 rounded-lg p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Statistiche Zona</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-indigo-600">{{ $zone->users()->count() }}</div>
                <div class="text-sm text-gray-600">Utenti Totali</div>
            </div>
            <div class="bg-white rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-green-600">{{ $zone->tournaments()->count() }}</div>
                <div class="text-sm text-gray-600">Tornei Creati</div>
            </div>
            <div class="bg-white rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-blue-600">{{ $zone->clubs()->count() }}</div>
                <div class="text-sm text-gray-600">Clubs Registrati</div>
            </div>
        </div>
    </div>
</div>

{{-- Delete Form (hidden) --}}
<form id="delete-form" action="{{ route('super-admin.zones.destroy', $zone) }}" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>

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

function confirmDelete() {
    if (confirm('Sei sicuro di voler eliminare questa zona? Questa azione è irreversibile.')) {
        document.getElementById('delete-form').submit();
    }
}
</script>
@endpush
@endsection

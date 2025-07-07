@extends('layouts.super-admin')

@section('title', 'Nuovo Utente')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl">
    {{-- Header --}}
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Nuovo Utente</h1>
                <p class="mt-2 text-gray-600">Crea un nuovo utente nel sistema</p>
            </div>
            <a href="{{ route('super-admin.users.index') }}"
               class="text-gray-600 hover:text-gray-900 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Torna all'elenco
            </a>
        </div>
    </div>

    {{-- Form --}}
    <form action="{{ route('super-admin.users.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf

        {{-- Basic Information --}}
        <div class="bg-white shadow-sm rounded-lg p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Informazioni Base</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="col-span-2">
                    <label for="name" class="block text-sm font-medium text-gray-700">
                        Nome Completo <span class="text-red-500">*</span>
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
                    <label for="email" class="block text-sm font-medium text-gray-700">
                        Email <span class="text-red-500">*</span>
                    </label>
                    <input type="email" name="email" id="email"
                           value="{{ old('email') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('email') border-red-500 @enderror"
                           required>
                    @error('email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="codice_tessera" class="block text-sm font-medium text-gray-700">
                        Codice Tessera
                    </label>
                    <input type="text" name="codice_tessera" id="codice_tessera"
                           value="{{ old('codice_tessera') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('codice_tessera') border-red-500 @enderror">
                    @error('codice_tessera')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">
                        Password <span class="text-red-500">*</span>
                    </label>
                    <input type="password" name="password" id="password"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('password') border-red-500 @enderror"
                           required>
                    @error('password')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700">
                        Conferma Password <span class="text-red-500">*</span>
                    </label>
                    <input type="password" name="password_confirmation" id="password_confirmation"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           required>
                </div>
            </div>
        </div>

        {{-- User Type and Zone --}}
        <div class="bg-white shadow-sm rounded-lg p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Tipo Utente e Zona</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="user_type" class="block text-sm font-medium text-gray-700">
                        Tipo Utente <span class="text-red-500">*</span>
                    </label>
                    <select name="user_type" id="user_type"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('user_type') border-red-500 @enderror"
                            required onchange="toggleFields()">
                        <option value="">Seleziona tipo utente</option>
                        @foreach($userTypes as $value => $label)
                            <option value="{{ $value }}" {{ old('user_type') === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @error('user_type')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div id="zone-field" style="{{ old('user_type') && old('user_type') !== 'super_admin' && old('user_type') !== 'national_admin' ? '' : 'display: none;' }}">
                    <label for="zone_id" class="block text-sm font-medium text-gray-700">
                        Zona <span class="text-red-500" id="zone-required" style="display: none;">*</span>
                    </label>
                    <select name="zone_id" id="zone_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('zone_id') border-red-500 @enderror">
                        <option value="">Seleziona zona</option>
                        @foreach($zones as $zone)
                            <option value="{{ $zone->id }}" {{ old('zone_id') == $zone->id ? 'selected' : '' }}>
                                {{ $zone->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('zone_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div id="referee-fields" class="mt-6" style="{{ old('user_type') === 'referee' ? '' : 'display: none;' }}">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Informazioni Arbitro</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="livello_arbitro" class="block text-sm font-medium text-gray-700">
                            Livello Arbitro
                        </label>
                        <select name="livello_arbitro" id="livello_arbitro"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">Seleziona livello</option>
                            @foreach($refereeLevels as $value => $label)
                                <option value="{{ $value }}" {{ old('livello_arbitro') === $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- Contact Information --}}
        <div class="bg-white shadow-sm rounded-lg p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Informazioni di Contatto</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="telefono" class="block text-sm font-medium text-gray-700">
                        Telefono
                    </label>
                    <input type="tel" name="telefono" id="telefono"
                           value="{{ old('telefono') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('telefono') border-red-500 @enderror">
                    @error('telefono')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="data_nascita" class="block text-sm font-medium text-gray-700">
                        Data di Nascita
                    </label>
                    <input type="date" name="data_nascita" id="data_nascita"
                           value="{{ old('data_nascita') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('data_nascita') border-red-500 @enderror">
                    @error('data_nascita')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="col-span-2">
                    <label for="indirizzo" class="block text-sm font-medium text-gray-700">
                        Indirizzo
                    </label>
                    <input type="text" name="indirizzo" id="indirizzo"
                           value="{{ old('indirizzo') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('indirizzo') border-red-500 @enderror">
                    @error('indirizzo')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="citta" class="block text-sm font-medium text-gray-700">
                        Città
                    </label>
                    <input type="text" name="citta" id="citta"
                           value="{{ old('citta') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('citta') border-red-500 @enderror">
                    @error('citta')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="cap" class="block text-sm font-medium text-gray-700">
                        CAP
                    </label>
                    <input type="text" name="cap" id="cap"
                           value="{{ old('cap') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('cap') border-red-500 @enderror">
                    @error('cap')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Profile Photo --}}
        <div class="bg-white shadow-sm rounded-lg p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Foto Profilo</h2>

            <div>
                <label for="profile_photo" class="block text-sm font-medium text-gray-700">
                    Foto Profilo
                </label>
                <div class="mt-1 flex items-center space-x-4">
                    <div class="flex-shrink-0">
                        <img id="photo-preview" class="h-20 w-20 rounded-full object-cover bg-gray-200"
                             src="data:image/svg+xml,%3csvg width='100' height='100' xmlns='http://www.w3.org/2000/svg'%3e%3crect width='100' height='100' fill='%23e5e7eb'/%3e%3ctext x='50%25' y='50%25' font-size='16' text-anchor='middle' dy='.3em' fill='%236b7280'%3eUser%3c/text%3e%3c/svg%3e"
                             alt="Preview">
                    </div>
                    <input type="file" name="profile_photo" id="profile_photo"
                           accept="image/*"
                           class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                           onchange="previewPhoto(this)">
                </div>
                <p class="mt-1 text-sm text-gray-500">PNG, JPG, JPEG fino a 2MB</p>
                @error('profile_photo')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Status --}}
        <div class="bg-white shadow-sm rounded-lg p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Stato</h2>

            <div class="space-y-4">
                <div class="flex items-center">
                    <input type="checkbox" name="is_active" id="is_active" value="1"
                           {{ old('is_active', true) ? 'checked' : '' }}
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <label for="is_active" class="ml-2 text-sm text-gray-700">
                        Utente Attivo
                    </label>
                </div>
                <p class="text-xs text-gray-500">Gli utenti non attivi non possono accedere al sistema</p>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex justify-end space-x-4">
            <a href="{{ route('super-admin.users.index') }}"
               class="px-6 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Annulla
            </a>
            <button type="submit"
                    class="px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Crea Utente
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
function toggleFields() {
    const userType = document.getElementById('user_type').value;
    const zoneField = document.getElementById('zone-field');
    const zoneRequired = document.getElementById('zone-required');
    const refereeFields = document.getElementById('referee-fields');
    const zoneSelect = document.getElementById('zone_id');

    // Reset
    zoneField.style.display = 'none';
    zoneRequired.style.display = 'none';
    refereeFields.style.display = 'none';
    zoneSelect.required = false;

    if (userType === 'zone_admin' || userType === 'referee') {
        zoneField.style.display = 'block';
        zoneRequired.style.display = 'inline';
        zoneSelect.required = true;
    }

    if (userType === 'referee') {
        refereeFields.style.display = 'block';
    }
}

function previewPhoto(input) {
    const preview = document.getElementById('photo-preview');

    if (input.files && input.files[0]) {
        const reader = new FileReader();

        reader.onload = function(e) {
            preview.src = e.target.result;
        }

        reader.readAsDataURL(input.files[0]);
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleFields();
});
</script>
@endpush
@endsection

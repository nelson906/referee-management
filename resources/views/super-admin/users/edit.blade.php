@extends('layouts.super-admin')

@section('title', 'Modifica Utente: ' . $user->name)

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl">
    {{-- Header --}}
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Modifica Utente</h1>
                <p class="mt-2 text-gray-600">Modifica le informazioni di: {{ $user->name }}</p>
            </div>
            <div class="flex space-x-4">
                <a href="{{ route('super-admin.users.show', $user) }}"
                   class="text-gray-600 hover:text-gray-900 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                    Visualizza
                </a>
                <a href="{{ route('super-admin.users.index') }}"
                   class="text-gray-600 hover:text-gray-900 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Torna all'elenco
                </a>
            </div>
        </div>
    </div>

    {{-- Warning for current user --}}
    @if($user->id === auth()->id())
    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-yellow-700">
                    <strong>Attenzione:</strong> Stai modificando il tuo account.
                    Fai attenzione a non disattivare il tuo accesso.
                </p>
            </div>
        </div>
    </div>
    @endif

    {{-- Form --}}
    <form action="{{ route('super-admin.users.update', $user) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Basic Information --}}
        <div class="bg-white shadow-sm rounded-lg p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Informazioni Base</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="col-span-2">
                    <label for="name" class="block text-sm font-medium text-gray-700">
                        Nome Completo <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name" id="name"
                           value="{{ old('name', $user->name) }}"
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
                           value="{{ old('email', $user->email) }}"
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
                           value="{{ old('codice_tessera', $user->codice_tessera) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('codice_tessera') border-red-500 @enderror">
                    @error('codice_tessera')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">
                        Nuova Password
                    </label>
                    <input type="password" name="password" id="password"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('password') border-red-500 @enderror">
                    <p class="mt-1 text-xs text-gray-500">Lascia vuoto per mantenere la password attuale</p>
                    @error('password')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700">
                        Conferma Nuova Password
                    </label>
                    <input type="password" name="password_confirmation" id="password_confirmation"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
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
                            required onchange="toggleFields()"
                            {{ $user->id === auth()->id() ? 'disabled' : '' }}>
                        @foreach($userTypes as $value => $label)
                            <option value="{{ $value }}" {{ old('user_type', $user->user_type) === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @if($user->id === auth()->id())
                        <input type="hidden" name="user_type" value="{{ $user->user_type }}">
                        <p class="mt-1 text-xs text-gray-500">Non puoi modificare il tuo tipo utente</p>
                    @endif
                    @error('user_type')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div id="zone-field" style="{{ old('user_type', $user->user_type) && old('user_type', $user->user_type) !== 'super_admin' && old('user_type', $user->user_type) !== 'national_admin' ? '' : 'display: none;' }}">
                    <label for="zone_id" class="block text-sm font-medium text-gray-700">
                        Zona <span class="text-red-500" id="zone-required" style="display: none;">*</span>
                    </label>
                    <select name="zone_id" id="zone_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('zone_id') border-red-500 @enderror">
                        <option value="">Seleziona zona</option>
                        @foreach($zones as $zone)
                            <option value="{{ $zone->id }}" {{ old('zone_id', $user->zone_id) == $zone->id ? 'selected' : '' }}>
                                {{ $zone->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('zone_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div id="referee-fields" class="mt-6" style="{{ old('user_type', $user->user_type) === 'referee' ? '' : 'display: none;' }}">
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
                                <option value="{{ $value }}" {{ old('livello_arbitro', $user->livello_arbitro) === $value ? 'selected' : '' }}>
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
                           value="{{ old('telefono', $user->telefono) }}"
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
                           value="{{ old('data_nascita', $user->data_nascita ? $user->data_nascita->format('Y-m-d') : '') }}"
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
                           value="{{ old('indirizzo', $user->indirizzo) }}"
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
                           value="{{ old('citta', $user->citta) }}"
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
                           value="{{ old('cap', $user->cap) }}"
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
                        @if($user->profile_photo_path)
                            <img id="photo-preview" class="h-20 w-20 rounded-full object-cover"
                                 src="{{ Storage::url($user->profile_photo_path) }}"
                                 alt="Foto profilo">
                        @else
                            <img id="photo-preview" class="h-20 w-20 rounded-full object-cover bg-gray-200"
                                 src="data:image/svg+xml,%3csvg width='100' height='100' xmlns='http://www.w3.org/2000/svg'%3e%3crect width='100' height='100' fill='%23e5e7eb'/%3e%3ctext x='50%25' y='50%25' font-size='16' text-anchor='middle' dy='.3em' fill='%236b7280'%3e{{ substr($user->name, 0, 1) }}%3c/text%3e%3c/svg%3e"
                                 alt="Preview">
                        @endif
                    </div>
                    <div class="flex-1">
                        <input type="file" name="profile_photo" id="profile_photo"
                               accept="image/*"
                               class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                               onchange="previewPhoto(this)">
                        <p class="mt-1 text-sm text-gray-500">PNG, JPG, JPEG fino a 2MB</p>
                    </div>
                    @if($user->profile_photo_path)
                        <button type="button" onclick="removePhoto()"
                                class="text-red-600 hover:text-red-800 text-sm">
                            Rimuovi
                        </button>
                        <input type="hidden" name="remove_photo" id="remove_photo" value="0">
                    @endif
                </div>
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
                           {{ old('is_active', $user->is_active) ? 'checked' : '' }}
                           {{ $user->id === auth()->id() ? 'disabled' : '' }}
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <label for="is_active" class="ml-2 text-sm text-gray-700">
                        Utente Attivo
                    </label>
                    @if($user->id === auth()->id())
                        <input type="hidden" name="is_active" value="1">
                    @endif
                </div>
                @if($user->id === auth()->id())
                    <p class="text-xs text-gray-500">Non puoi disattivare il tuo account</p>
                @else
                    <p class="text-xs text-gray-500">Gli utenti non attivi non possono accedere al sistema</p>
                @endif
            </div>

            {{-- Last activity info --}}
            <div class="mt-4 pt-4 border-t border-gray-200">
                <dl class="grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Ultimo accesso</dt>
                        <dd class="text-sm text-gray-900">
                            {{ $user->last_login_at ? $user->last_login_at->format('d/m/Y H:i') : 'Mai' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Registrato il</dt>
                        <dd class="text-sm text-gray-900">
                            {{ $user->created_at->format('d/m/Y H:i') }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex justify-between">
            <div>
                @if($user->id !== auth()->id())
                <button type="button" onclick="confirmDelete()"
                        class="px-4 py-2 border border-red-300 rounded-md shadow-sm text-sm font-medium text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    Elimina Utente
                </button>
                @endif
            </div>
            <div class="flex space-x-4">
                <a href="{{ route('super-admin.users.index') }}"
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
</div>

{{-- Delete Form (hidden) --}}
<form id="delete-form" action="{{ route('super-admin.users.destroy', $user) }}" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>

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

        // Reset remove photo flag
        const removePhotoInput = document.getElementById('remove_photo');
        if (removePhotoInput) {
            removePhotoInput.value = '0';
        }
    }
}

function removePhoto() {
    if (confirm('Sei sicuro di voler rimuovere la foto profilo?')) {
        document.getElementById('remove_photo').value = '1';
        document.getElementById('photo-preview').src = "data:image/svg+xml,%3csvg width='100' height='100' xmlns='http://www.w3.org/2000/svg'%3e%3crect width='100' height='100' fill='%23e5e7eb'/%3e%3ctext x='50%25' y='50%25' font-size='16' text-anchor='middle' dy='.3em' fill='%236b7280'%3e{{ substr($user->name, 0, 1) }}%3c/text%3e%3c/svg%3e";
        document.getElementById('profile_photo').value = '';
    }
}

function confirmDelete() {
    if (confirm('Sei sicuro di voler eliminare questo utente? Questa azione è irreversibile.')) {
        document.getElementById('delete-form').submit();
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleFields();
});
</script>
@endpush
@endsection

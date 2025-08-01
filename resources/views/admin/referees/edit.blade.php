@extends('layouts.admin')

@section('title', 'Modifica Arbitro')

@section('content')
    <div class="container mx-auto px-4 py-6">
        {{-- Header --}}
        <div class="mb-6">
            <div class="flex items-center space-x-3">
                <a href="{{ route('admin.referees.show', $referee) }}" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                <h1 class="text-2xl font-bold text-gray-900">Modifica Arbitro</h1>
            </div>
            <p class="text-gray-600">
                {{ $referee->name ?? ($referee->name ?? 'Nome non disponibile') }} -
                {{ $referee->referee_code ?? 'Codice non disponibile' }}
            </p>
        </div>

        {{-- Alert Messages --}}
        @if (session('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p class="font-bold">Successo!</p>
                <p>{{ session('success') }}</p>
            </div>
        @endif

        @if (session('error'))
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p class="font-bold">Errore!</p>
                <p>{{ session('error') }}</p>
            </div>
        @endif

        {{-- Form --}}
        <div class="bg-white rounded-lg shadow p-6">
            <form method="POST" action="{{ route('admin.referees.update', $referee) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Nome (dall'User) --}}
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Nome Completo *</label>
                        <input type="text" name="name" id="name" value="{{ old('name', $referee->name) }}"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('name') border-red-500 @enderror"
                            required>
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Email (dall'User) --}}
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                        <input type="email" name="email" id="email"
                            value="{{ old('email', $referee->email) }}"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('email') border-red-500 @enderror"
                            required>
                        @error('email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    {{-- Codice Arbitro - CORRETTO: dal User, non dal Referee --}}
                    <div>
                        <label for="referee_code" class="block text-sm font-medium text-gray-700 mb-1">
                            Codice Arbitro
                            <span class="text-gray-500 text-xs">(Generato automaticamente)</span>
                        </label>
                        {{-- ✅ FIX: usa $referee->referee_code --}}
                        <input type="text" name="referee_code" id="referee_code"
                            value="{{ old('referee_code', $referee->referee_code) }}"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 bg-gray-50 focus:ring-blue-500 focus:border-blue-500"
                            readonly>
                        <p class="mt-1 text-xs text-gray-500">Il codice viene generato automaticamente e non può essere
                            modificato</p>
                    </div>


                    {{-- Telefono (dall'User) --}}
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Telefono</label>
                        <input type="text" name="phone" id="phone"
                            value="{{ old('phone', $referee->phone) }}"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('phone') border-red-500 @enderror">
                        @error('phone')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="level" class="block text-sm font-medium text-gray-700 mb-1">Livello *</label>
                        <select name="level" id="level"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('level') border-red-500 @enderror"
                            required>
                            <option value="">Seleziona livello</option>
                            @foreach(referee_levels() as $key => $label)
                                <option value="{{ $key }}"
                                    {{ old('level', normalize_referee_level($referee->level)) == $key ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('level')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Zona - CORRETTO: dal User, non dal Referee --}}
                    <div>
                        <label for="zone_id" class="block text-sm font-medium text-gray-700 mb-1">Zona *</label>
                        <select name="zone_id" id="zone_id"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('zone_id') border-red-500 @enderror"
                            required>
                            <option value="">Seleziona zona</option>
                            @foreach ($zones as $zone)
                                {{-- ✅ FIX: usa $referee->zone_id invece di $referee->zone_id --}}
                                <option value="{{ $zone->id }}"
                                    {{ old('zone_id', $referee->zone_id) == $zone->id ? 'selected' : '' }}>
                                    {{ $zone->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('zone_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Categoria (dal Referee) --}}
                    <div>
                        <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Categoria</label>
                        <select name="category" id="category"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="misto"
                                {{ old('category', $referee->category ?? 'misto') == 'misto' ? 'selected' : '' }}>
                                Misto
                            </option>
                            <option value="maschile"
                                {{ old('category', $referee->category ?? 'misto') == 'maschile' ? 'selected' : '' }}>
                                Maschile
                            </option>
                            <option value="femminile"
                                {{ old('category', $referee->category ?? 'misto') == 'femminile' ? 'selected' : '' }}>
                                Femminile
                            </option>
                        </select>
                    </div>
                </div>

                {{-- Note (dal Referee) --}}
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Note</label>
                    <textarea name="notes" id="notes" rows="3"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('notes') border-red-500 @enderror">{{ old('notes', $referee->notes) }}</textarea>
                    @error('notes')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Stato (dall'User) --}}
                <div class="flex items-center">
                    <input type="checkbox" name="is_active" id="is_active" value="1"
                        {{ old('is_active', $referee->is_active) ? 'checked' : '' }}
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label for="is_active" class="ml-2 block text-sm text-gray-900">Arbitro attivo</label>
                </div>

                {{-- Actions --}}
                {{-- Submit Buttons del form UPDATE --}}
                <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                    <a href="{{ route('admin.referees.show', $referee) }}"
                        class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400 transition">
                        Annulla
                    </a>
                    <button type="submit"
                        class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                        Aggiorna Arbitro
                    </button>
                </div>
            </form> {{-- ✅ CHIUDI IL FORM UPDATE QUI --}}
        </div>

        {{-- Form DELETE separato, FUORI dal form update --}}
        <div class="bg-white rounded-lg shadow p-6 mt-6">
            <h3 class="text-lg font-medium text-red-900 mb-4">⚠️ Zona Pericolosa</h3>
            <p class="text-sm text-gray-600 mb-4">
                L'eliminazione di un arbitro è irreversibile. Tutti i dati associati andranno persi.
            </p>

            <form method="POST" action="{{ route('admin.referees.destroy', $referee) }}"
                onsubmit="return confirm('Sei sicuro di voler eliminare questo arbitro? Questa azione è irreversibile.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 transition">
                    🗑️ Elimina Arbitro
                </button>
            </form>
        </div>
    </div> {{-- Chiusura container principale --}}
@endsection

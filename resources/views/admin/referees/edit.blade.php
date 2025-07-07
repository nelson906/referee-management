@extends('layouts.admin')

@section('title', 'Modifica Arbitro')

@section('content')
<div class="container mx-auto px-4 py-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center space-x-3">
            <a href="{{ route('admin.referees.show', $referee) }}" class="text-gray-500 hover:text-gray-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h1 class="text-2xl font-bold text-gray-900">Modifica Arbitro</h1>
        </div>
        <p class="text-gray-600">{{ $referee->name }} - {{ $referee->referee_code }}</p>
    </div>

    {{-- Form --}}
    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="{{ route('admin.referees.update', $referee) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Nome --}}
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Nome Completo *</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $referee->name) }}"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('name') border-red-500 @enderror"
                           required>
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Email --}}
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                    <input type="email" name="email" id="email" value="{{ old('email', $referee->email) }}"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('email') border-red-500 @enderror"
                           required>
                    @error('email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Codice Arbitro --}}
                <div>
                    <label for="referee_code" class="block text-sm font-medium text-gray-700 mb-1">Codice Arbitro *</label>
                    <input type="text" name="referee_code" id="referee_code" value="{{ old('referee_code', $referee->referee_code) }}"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('referee_code') border-red-500 @enderror"
                           required>
                    @error('referee_code')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Telefono --}}
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Telefono</label>
                    <input type="text" name="phone" id="phone" value="{{ old('phone', $referee->phone) }}"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('phone') border-red-500 @enderror">
                    @error('phone')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Livello --}}
                <div>
                    <label for="level" class="block text-sm font-medium text-gray-700 mb-1">Livello *</label>
                    <select name="level" id="level"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('level') border-red-500 @enderror"
                            required>
                        <option value="">Seleziona livello</option>
                        @foreach($levels as $key => $label)
                            <option value="{{ $key }}" {{ old('level', $referee->level) == $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @error('level')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Zona --}}
                <div>
                    <label for="zone_id" class="block text-sm font-medium text-gray-700 mb-1">Zona *</label>
                    <select name="zone_id" id="zone_id"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('zone_id') border-red-500 @enderror"
                            required>
                        <option value="">Seleziona zona</option>
                        @foreach($zones as $zone)
                            <option value="{{ $zone->id }}" {{ old('zone_id', $referee->zone_id) == $zone->id ? 'selected' : '' }}>
                                {{ $zone->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('zone_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Note --}}
            <div>
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Note</label>
                <textarea name="notes" id="notes" rows="3"
                          class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('notes') border-red-500 @enderror">{{ old('notes', $referee->notes) }}</textarea>
                @error('notes')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Stato --}}
            <div class="flex items-center">
                <input type="checkbox" name="is_active" id="is_active" value="1"
                       {{ old('is_active', $referee->is_active) ? 'checked' : '' }}
                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                <label for="is_active" class="ml-2 block text-sm text-gray-900">Arbitro attivo</label>
            </div>

            {{-- Actions --}}
            <div class="flex justify-between pt-6 border-t border-gray-200">
                <div>
                    {{-- Delete Button --}}
                    <form method="POST" action="{{ route('admin.referees.destroy', $referee) }}" class="inline"
                          onsubmit="return confirm('Sei sicuro di voler eliminare questo arbitro? Questa azione è irreversibile.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 transition">
                            Elimina
                        </button>
                    </form>
                </div>

                <div class="flex space-x-3">
                    <a href="{{ route('admin.referees.show', $referee) }}"
                       class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400 transition">
                        Annulla
                    </a>
                    <button type="submit"
                            class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                        Aggiorna Arbitro
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

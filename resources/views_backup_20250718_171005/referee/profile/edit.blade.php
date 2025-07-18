@extends('layouts.app')

@section('title', 'Modifica Profilo')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl">
    {{-- Header --}}
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">👤 Il mio Profilo</h1>
        <p class="mt-2 text-gray-600">Aggiorna le tue informazioni personali</p>
    </div>

    {{-- Alert Messages --}}
    @if(session('status'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p class="font-bold">Successo!</p>
            <p>{{ session('status') }}</p>
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p class="font-bold">Errore!</p>
            <p>{{ session('error') }}</p>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {{-- Sidebar Info --}}
        <div class="lg:col-span-1">
            <div class="bg-white shadow rounded-lg p-6">
                <div class="text-center">
                    <div class="w-20 h-20 bg-blue-100 rounded-full mx-auto mb-4 flex items-center justify-center">
                        <span class="text-2xl font-bold text-blue-600">{{ substr($user->name, 0, 1) }}</span>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900">{{ $user->name }}</h3>
                    @if($user->referee)
                        <p class="text-sm text-gray-500">{{ $user->referee->referee_code }}</p>
                        <p class="text-sm text-blue-600">{{ ucwords(str_replace('_', ' ', $user->referee->level ?? 'Primo Livello')) }}</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Main Form --}}
        <div class="lg:col-span-2">
            <div class="bg-white shadow rounded-lg p-6">
                <form action="{{ route('referee.profile.update') }}" method="POST" class="space-y-6">
                    @csrf
                    @method('PUT')

                    {{-- Informazioni di base --}}
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Informazioni di Base</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {{-- Nome --}}
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                                    Nome Completo *
                                </label>
                                <input type="text" name="name" id="name"
                                       value="{{ old('name', $user->name) }}"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('name') border-red-500 @enderror"
                                       required>
                                @error('name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Email --}}
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                                    Email *
                                </label>
                                <input type="email" name="email" id="email"
                                       value="{{ old('email', $user->email) }}"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('email') border-red-500 @enderror"
                                       required>
                                @error('email')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Telefono --}}
                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">
                                    Telefono
                                </label>
                                <input type="text" name="phone" id="phone"
                                       value="{{ old('phone', $user->phone) }}"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="es. 3331234567">
                            </div>

                            {{-- Zona --}}
                            <div>
                                <label for="zone_id" class="block text-sm font-medium text-gray-700 mb-1">
                                    Zona *
                                </label>
                                <select name="zone_id" id="zone_id"
                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
                                        required>
                                    @foreach($zones as $zone)
                                        <option value="{{ $zone->id }}"
                                            {{ old('zone_id', $user->zone_id) == $zone->id ? 'selected' : '' }}>
                                            {{ $zone->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('zone_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Informazioni Arbitrali --}}
                    <div class="border-t pt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Informazioni Arbitrali</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {{-- Codice Arbitro (readonly) --}}
                            <div>
                                <label for="referee_code_display" class="block text-sm font-medium text-gray-700 mb-1">
                                    Codice Arbitro
                                </label>
                                <input type="text"
                                       value="{{ $user->referee->referee_code ?? 'Verrà generato automaticamente' }}"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 bg-gray-50"
                                       readonly>
                                <p class="mt-1 text-xs text-gray-500">Il codice viene assegnato automaticamente</p>
                            </div>

                            {{-- Livello --}}
                            <div>
                                <label for="level" class="block text-sm font-medium text-gray-700 mb-1">
                                    Livello *
                                </label>
                                <select name="level" id="level"
                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
                                        required>
                                    @foreach($levels as $key => $label)
                                        <option value="{{ $key }}"
                                            {{ old('level', $user->referee->level ?? 'primo_livello') == $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('level')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Categoria --}}
                            <div>
                                <label for="category" class="block text-sm font-medium text-gray-700 mb-1">
                                    Categoria
                                </label>
                                <select name="category" id="category"
                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="misto"
                                        {{ old('category', $user->referee->category ?? 'misto') == 'misto' ? 'selected' : '' }}>
                                        Misto
                                    </option>
                                    <option value="maschile"
                                        {{ old('category', $user->referee->category ?? 'misto') == 'maschile' ? 'selected' : '' }}>
                                        Maschile
                                    </option>
                                    <option value="femminile"
                                        {{ old('category', $user->referee->category ?? 'misto') == 'femminile' ? 'selected' : '' }}>
                                        Femminile
                                    </option>
                                </select>
                            </div>

                            {{-- Anni Esperienza --}}
                            <div>
                                <label for="experience_years" class="block text-sm font-medium text-gray-700 mb-1">
                                    Anni di Esperienza
                                </label>
                                <input type="number" name="experience_years" id="experience_years" min="0"
                                       value="{{ old('experience_years', $user->referee->experience_years ?? 0) }}"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>

                        {{-- Bio --}}
                        <div class="mt-4">
                            <label for="bio" class="block text-sm font-medium text-gray-700 mb-1">
                                Biografia
                            </label>
                            <textarea name="bio" id="bio" rows="3"
                                      class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
                                      placeholder="Descrivi la tua esperienza come arbitro...">{{ old('bio', $user->referee->bio ?? '') }}</textarea>
                        </div>
                    </div>

                    {{-- Submit Buttons --}}
                    <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
                        <a href="{{ route('referee.dashboard') }}"
                           class="px-6 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            Annulla
                        </a>
                        <button type="submit"
                                class="px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                            Salva Modifiche
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

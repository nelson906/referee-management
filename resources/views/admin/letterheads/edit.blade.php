@extends('layouts.admin')

@section('title', 'Modifica Letterhead')

@section('content')
<div class="container mx-auto px-4 py-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center space-x-3">
            <a href="{{ route('admin.letterheads.show', $letterhead) }}" class="text-gray-500 hover:text-gray-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h1 class="text-2xl font-bold text-gray-900">Modifica Letterhead</h1>
        </div>
        <p class="text-gray-600">Modifica le informazioni della letterhead</p>
    </div>

    {{-- Form --}}
    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="{{ route('admin.letterheads.update', $letterhead) }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Titolo --}}
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Titolo *</label>
                    <input type="text" name="title" id="title" value="{{ old('title', $letterhead->title) }}"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('title') border-red-500 @enderror"
                           required>
                    @error('title')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Zona --}}
                <div>
                    <label for="zone_id" class="block text-sm font-medium text-gray-700 mb-1">Zona</label>
                    <select name="zone_id" id="zone_id"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('zone_id') border-red-500 @enderror">
                        <option value="">Globale</option>
                        @foreach($zones as $zone)
                            <option value="{{ $zone->id }}" {{ old('zone_id', $letterhead->zone_id) == $zone->id ? 'selected' : '' }}>
                                {{ $zone->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('zone_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Descrizione --}}
            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Descrizione</label>
                <textarea name="description" id="description" rows="3"
                          class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('description') border-red-500 @enderror">{{ old('description', $letterhead->description) }}</textarea>
                @error('description')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Logo --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="logo" class="block text-sm font-medium text-gray-700 mb-1">Logo</label>
                    <input type="file" name="logo" id="logo" accept="image/*"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('logo') border-red-500 @enderror">
                    <p class="mt-1 text-xs text-gray-500">JPG, PNG, SVG fino a 2MB</p>
                    @error('logo')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Logo Preview --}}
                @if($letterhead->logo_path)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Logo attuale</label>
                        <div class="border border-gray-300 rounded-md p-4 bg-gray-50">
                            <img src="{{ Storage::url($letterhead->logo_path) }}" alt="Logo attuale"
                                 class="max-h-20 max-w-full object-contain">
                        </div>
                    </div>
                @endif
            </div>

            {{-- Header e Footer --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="header_text" class="block text-sm font-medium text-gray-700 mb-1">Testo Header</label>
                    <textarea name="header_text" id="header_text" rows="4"
                              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('header_text') border-red-500 @enderror">{{ old('header_text', $letterhead->header_text) }}</textarea>
                    @error('header_text')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="footer_text" class="block text-sm font-medium text-gray-700 mb-1">Testo Footer</label>
                    <textarea name="footer_text" id="footer_text" rows="4"
                              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('footer_text') border-red-500 @enderror">{{ old('footer_text', $letterhead->footer_text) }}</textarea>
                    @error('footer_text')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Informazioni di Contatto --}}
            <div class="border-t pt-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Informazioni di Contatto</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="contact_address" class="block text-sm font-medium text-gray-700 mb-1">Indirizzo</label>
                        <input type="text" name="contact_info[address]" id="contact_address"
                               value="{{ old('contact_info.address', $letterhead->contact_info['address'] ?? '') }}"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <label for="contact_phone" class="block text-sm font-medium text-gray-700 mb-1">Telefono</label>
                        <input type="text" name="contact_info[phone]" id="contact_phone"
                               value="{{ old('contact_info.phone', $letterhead->contact_info['phone'] ?? '') }}"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <label for="contact_email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="contact_info[email]" id="contact_email"
                               value="{{ old('contact_info.email', $letterhead->contact_info['email'] ?? '') }}"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <label for="contact_website" class="block text-sm font-medium text-gray-700 mb-1">Sito Web</label>
                        <input type="url" name="contact_info[website]" id="contact_website"
                               value="{{ old('contact_info.website', $letterhead->contact_info['website'] ?? '') }}"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
            </div>

            {{-- Impostazioni --}}
            <div class="border-t pt-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Impostazioni</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    {{-- Stato --}}
                    <div class="space-y-4">
                        <div class="flex items-center">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" id="is_active" value="1"
                                   {{ old('is_active', $letterhead->is_active) ? 'checked' : '' }}
                                   class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                            <label for="is_active" class="ml-2 block text-sm text-gray-900">Attiva</label>
                        </div>

                        <div class="flex items-center">
                            <input type="hidden" name="is_default" value="0">
                            <input type="checkbox" name="is_default" id="is_default" value="1"
                                   {{ old('is_default', $letterhead->is_default) ? 'checked' : '' }}
                                   class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                            <label for="is_default" class="ml-2 block text-sm text-gray-900">Predefinita</label>
                        </div>
                    </div>

                    {{-- Font --}}
                    <div>
                        <label for="font_family" class="block text-sm font-medium text-gray-700 mb-1">Font</label>
                        <select name="settings[font][family]" id="font_family"
                                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="Arial" {{ old('settings.font.family', $letterhead->settings['font']['family'] ?? 'Arial') === 'Arial' ? 'selected' : '' }}>Arial</option>
                            <option value="Times New Roman" {{ old('settings.font.family', $letterhead->settings['font']['family'] ?? '') === 'Times New Roman' ? 'selected' : '' }}>Times New Roman</option>
                            <option value="Helvetica" {{ old('settings.font.family', $letterhead->settings['font']['family'] ?? '') === 'Helvetica' ? 'selected' : '' }}>Helvetica</option>
                        </select>

                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <div>
                                <label for="font_size" class="block text-xs text-gray-500">Dimensione</label>
                                <input type="number" name="settings[font][size]" id="font_size" min="8" max="24"
                                       value="{{ old('settings.font.size', $letterhead->settings['font']['size'] ?? 11) }}"
                                       class="w-full border border-gray-300 rounded-md px-2 py-1 text-sm">
                            </div>
                            <div>
                                <label for="font_color" class="block text-xs text-gray-500">Colore</label>
                                <input type="color" name="settings[font][color]" id="font_color"
                                       value="{{ old('settings.font.color', $letterhead->settings['font']['color'] ?? '#000000') }}"
                                       class="w-full border border-gray-300 rounded-md px-1 py-1">
                            </div>
                        </div>
                    </div>

                    {{-- Margini --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Margini (mm)</label>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label for="margin_top" class="block text-xs text-gray-500">Alto</label>
                                <input type="number" name="settings[margins][top]" id="margin_top" min="0" max="100"
                                       value="{{ old('settings.margins.top', $letterhead->settings['margins']['top'] ?? 20) }}"
                                       class="w-full border border-gray-300 rounded-md px-2 py-1 text-sm">
                            </div>
                            <div>
                                <label for="margin_bottom" class="block text-xs text-gray-500">Basso</label>
                                <input type="number" name="settings[margins][bottom]" id="margin_bottom" min="0" max="100"
                                       value="{{ old('settings.margins.bottom', $letterhead->settings['margins']['bottom'] ?? 20) }}"
                                       class="w-full border border-gray-300 rounded-md px-2 py-1 text-sm">
                            </div>
                            <div>
                                <label for="margin_left" class="block text-xs text-gray-500">Sinistro</label>
                                <input type="number" name="settings[margins][left]" id="margin_left" min="0" max="100"
                                       value="{{ old('settings.margins.left', $letterhead->settings['margins']['left'] ?? 25) }}"
                                       class="w-full border border-gray-300 rounded-md px-2 py-1 text-sm">
                            </div>
                            <div>
                                <label for="margin_right" class="block text-xs text-gray-500">Destro</label>
                                <input type="number" name="settings[margins][right]" id="margin_right" min="0" max="100"
                                       value="{{ old('settings.margins.right', $letterhead->settings['margins']['right'] ?? 25) }}"
                                       class="w-full border border-gray-300 rounded-md px-2 py-1 text-sm">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex justify-between pt-6 border-t">
                <div>
                    @if(!$letterhead->is_default)
                        <button type="button" onclick="if(confirm('Sei sicuro di voler eliminare questa letterhead?')) { document.getElementById('delete-form').submit(); }"
                                class="px-4 py-2 border border-red-300 rounded-md text-sm font-medium text-red-700 bg-white hover:bg-red-50">
                            🗑️ Elimina
                        </button>
                    @endif
                </div>
                <div class="flex space-x-4">
                    <a href="{{ route('admin.letterheads.show', $letterhead) }}"
                       class="px-6 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Annulla
                    </a>
                    <button type="submit"
                            class="px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                        💾 Salva Modifiche
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Delete Form (hidden) --}}
@if(!$letterhead->is_default)
    <form id="delete-form" action="{{ route('admin.letterheads.destroy', $letterhead) }}" method="POST" style="display: none;">
        @csrf
        @method('DELETE')
    </form>
@endif
@endsection

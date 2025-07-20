{{-- resources/views/super-admin/institutional-emails/edit.blade.php --}}
@extends('layouts.super-admin')

@section('title', 'Modifica Email Istituzionale')

@section('header', 'Modifica Email Istituzionale')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white shadow-sm rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-medium text-gray-900">Modifica Email Istituzionale</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ $institutionalEmail->email }}</p>
                </div>
                <div class="flex items-center space-x-3">
                    <a href="{{ route('super-admin.institutional-emails.show', $institutionalEmail) }}"
                       class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        Visualizza
                    </a>
                    <a href="{{ route('super-admin.institutional-emails.index') }}"
                       class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Indietro
                    </a>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('super-admin.institutional-emails.update', $institutionalEmail) }}" class="p-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Nome --}}
                <div class="md:col-span-1">
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                        Nome *
                    </label>
                    <input type="text" name="name" id="name" value="{{ old('name', $institutionalEmail->name) }}"
                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('name') border-red-500 @enderror"
                           required>
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Email --}}
                <div class="md:col-span-1">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                        Indirizzo Email *
                    </label>
                    <input type="email" name="email" id="email" value="{{ old('email', $institutionalEmail->email) }}"
                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('email') border-red-500 @enderror"
                           required>
                    @error('email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Categoria --}}
                <div class="md:col-span-1">
                    <label for="category" class="block text-sm font-medium text-gray-700 mb-1">
                        Categoria *
                    </label>
                    <select name="category" id="category"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('category') border-red-500 @enderror"
                            required>
                        <option value="">Seleziona categoria</option>
                        @foreach($categories as $key => $label)
                            <option value="{{ $key }}" {{ old('category', $institutionalEmail->category) === $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @error('category')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Zona --}}
                <div class="md:col-span-1">
                    <label for="zone_id" class="block text-sm font-medium text-gray-700 mb-1">
                        Zona
                    </label>
                    <select name="zone_id" id="zone_id"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('zone_id') border-red-500 @enderror">
                        <option value="">Tutte le zone</option>
                        @foreach($zones as $zone)
                            <option value="{{ $zone->id }}" {{ old('zone_id', $institutionalEmail->zone_id) == $zone->id ? 'selected' : '' }}>
                                {{ $zone->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('zone_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-500">Lascia vuoto per applicare a tutte le zone</p>
                </div>

                {{-- Descrizione --}}
                <div class="md:col-span-2">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">
                        Descrizione
                    </label>
                    <textarea name="description" id="description" rows="3"
                              class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('description') border-red-500 @enderror"
                              placeholder="Descrizione opzionale dell'email istituzionale...">{{ old('description', $institutionalEmail->description) }}</textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Sezione Notifiche --}}
            <div class="mt-8 border-t border-gray-200 pt-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Configurazione Notifiche</h3>

                <div class="space-y-4">
                    {{-- Ricevi tutte le notifiche --}}
                    <div class="flex items-start">
                        <div class="flex items-center h-5">
                            <input type="hidden" name="receive_all_notifications" value="0">
                            <input type="checkbox" name="receive_all_notifications" id="receive_all_notifications"
                                   value="1" {{ old('receive_all_notifications', $institutionalEmail->receive_all_notifications) ? 'checked' : '' }}
                                   class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                        </div>
                        <div class="ml-3 text-sm">
                            <label for="receive_all_notifications" class="font-medium text-gray-700">
                                Ricevi tutte le notifiche
                            </label>
                            <p class="text-gray-500">Se attivato, questa email riceverà automaticamente tutti i tipi di notifica</p>
                        </div>
                    </div>

                    {{-- Tipi di notifica specifici --}}
                    <div id="specific-notifications" class="{{ old('receive_all_notifications', $institutionalEmail->receive_all_notifications) ? 'hidden' : '' }}">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Tipi di notifica specifici
                        </label>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                            @foreach($notificationTypes as $key => $label)
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input type="checkbox" name="notification_types[]" id="notification_{{ $key }}"
                                               value="{{ $key }}"
                                               {{ in_array($key, old('notification_types', $institutionalEmail->notification_types ?? [])) ? 'checked' : '' }}
                                               class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="notification_{{ $key }}" class="text-gray-700">
                                            {{ $label }}
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @error('notification_types')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- Stato --}}
            <div class="mt-6 border-t border-gray-200 pt-6">
                <div class="flex items-start">
                    <div class="flex items-center h-5">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" id="is_active"
                               value="1" {{ old('is_active', $institutionalEmail->is_active) ? 'checked' : '' }}
                               class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="is_active" class="font-medium text-gray-700">
                            Email attiva
                        </label>
                        <p class="text-gray-500">Se disattivata, questa email non riceverà alcuna notifica</p>
                    </div>
                </div>
            </div>

            {{-- Informazioni di sistema --}}
            <div class="mt-6 border-t border-gray-200 pt-6">
                <div class="bg-gray-50 rounded-lg p-4">
                    <h4 class="text-sm font-medium text-gray-900 mb-2">Informazioni Sistema</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs text-gray-600">
                        <div>
                            <span class="font-medium">Creata il:</span> {{ $institutionalEmail->created_at->format('d/m/Y H:i') }}
                        </div>
                        <div>
                            <span class="font-medium">Ultima modifica:</span> {{ $institutionalEmail->updated_at->format('d/m/Y H:i') }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Pulsanti di azione --}}
            <div class="mt-8 flex items-center justify-end space-x-3 border-t border-gray-200 pt-6">
                <a href="{{ route('super-admin.institutional-emails.show', $institutionalEmail) }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Annulla
                </a>
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring ring-indigo-300 disabled:opacity-25 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Salva Modifiche
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const receiveAllCheckbox = document.getElementById('receive_all_notifications');
    const specificNotifications = document.getElementById('specific-notifications');
    const specificCheckboxes = document.querySelectorAll('input[name="notification_types[]"]');

    function toggleSpecificNotifications() {
        if (receiveAllCheckbox.checked) {
            specificNotifications.classList.add('hidden');
            // Uncheck all specific notifications
            specificCheckboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
        } else {
            specificNotifications.classList.remove('hidden');
        }
    }

    receiveAllCheckbox.addEventListener('change', toggleSpecificNotifications);

    // Initialize on page load
    toggleSpecificNotifications();
});
</script>
@endpush
@endsection

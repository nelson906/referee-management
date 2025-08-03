{{-- resources/views/super-admin/institutional-emails/index.blade.php --}}
@extends('layouts.super-admin')

@section('title', 'Email Istituzionali')

@section('header', 'Gestione Email Istituzionali')

@section('content')
    <div class="space-y-6">
        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Email Istituzionali</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Gestisci gli indirizzi email istituzionali per notifiche e comunicazioni
                </p>
            </div>
            <div class="mt-4 sm:mt-0">
                <a href="{{ route('super-admin.institutional-emails.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring ring-indigo-300 disabled:opacity-25 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Nuova Email
                </a>
            </div>
        </div>

        {{-- Filters Section --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <form method="GET" action="{{ route('super-admin.institutional-emails.index') }}" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    {{-- Search --}}
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Cerca</label>
                        <input type="text" name="search" id="search" value="{{ request('search') }}"
                            placeholder="Nome, email, descrizione..."
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>

                    {{-- Category Filter --}}
                    <div>
                        <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Categoria</label>
                        <select name="category" id="category"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">Tutte le categorie</option>
                            @foreach (App\Models\InstitutionalEmail::CATEGORIES as $key => $label)
                                <option value="{{ $key }}" {{ request('category') === $key ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Zone Filter --}}
                    <div>
                        <label for="zone_id" class="block text-sm font-medium text-gray-700 mb-1">Zona</label>
                        <select name="zone_id" id="zone_id"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">Tutte le zone</option>
                            <option value="null" {{ request('zone_id') === 'null' ? 'selected' : '' }}>Nessuna zona
                                specifica</option>
                            @foreach ($zones as $zone)
                                <option value="{{ $zone->id }}" {{ request('zone_id') == $zone->id ? 'selected' : '' }}>
                                    {{ $zone->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Status Filter --}}
                    <div>
                        <label for="is_active" class="block text-sm font-medium text-gray-700 mb-1">Stato</label>
                        <select name="is_active" id="is_active"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">Tutti</option>
                            <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>Attive</option>
                            <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Disattivate
                            </option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex space-x-2">
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            Filtra
                        </button>
                        @if (request()->hasAny(['search', 'category', 'zone_id', 'is_active']))
                            <a href="{{ route('super-admin.institutional-emails.index') }}"
                                class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400 active:bg-gray-500 focus:outline-none focus:border-gray-500 focus:ring ring-gray-200 disabled:opacity-25 transition ease-in-out duration-150">
                                Reset Filtri
                            </a>
                        @endif
                    </div>

                    <div class="flex space-x-2">
                        {{-- Export Button - ✅ NOME ROUTE CORRETTO --}}
                        <a href="{{ route('super-admin.institutional-emails.export', request()->query()) }}"
                            class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 active:bg-green-900 focus:outline-none focus:border-green-900 focus:ring ring-green-300 disabled:opacity-25 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                </path>
                            </svg>
                            Esporta CSV
                        </a>
                    </div>
                </div>
            </form>
        </div>

        {{-- Results Section --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            @if ($institutionalEmails->count() > 0)
                {{-- Bulk Actions --}}
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <form id="bulk-action-form" method="POST"
                        action="{{ route('super-admin.institutional-emails.bulk-action') }}"
                        class="flex items-center space-x-4">
                        @csrf
                        <div class="flex items-center space-x-2">
                            <input type="checkbox" id="select-all"
                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <label for="select-all" class="text-sm font-medium text-gray-700">Seleziona tutto</label>
                        </div>

                        <select name="action"
                            class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">Azioni selezionate...</option>
                            <option value="activate">Attiva</option>
                            <option value="deactivate">Disattiva</option>
                            <option value="delete">Elimina</option>
                        </select>

                        <button type="submit"
                            class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Applica
                        </button>
                    </form>
                </div>

                {{-- Table --}}
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col"
                                    class="w-12 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <span class="sr-only">Seleziona</span>
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Email
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Categoria
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Zona
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Notifiche
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Stato
                                </th>
                                <th scope="col" class="relative px-6 py-3">
                                    <span class="sr-only">Azioni</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($institutionalEmails as $email)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="checkbox" name="email_ids[]" value="{{ $email->id }}"
                                            form="bulk-action-form"
                                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-8 w-8">
                                                <div
                                                    class="h-8 w-8 rounded-full bg-indigo-100 flex items-center justify-center">
                                                    <svg class="w-4 h-4 text-indigo-600" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                                                        </path>
                                                    </svg>
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">{{ $email->name }}</div>
                                                <div class="text-sm text-gray-500">{{ $email->email }}</div>
                                                @if ($email->description)
                                                    <div class="text-xs text-gray-400 mt-1">
                                                        {{ Str::limit($email->description, 50) }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        @switch($email->category)
                                            @case('federazione') bg-purple-100 text-purple-800 @break
                                            @case('comitato') bg-blue-100 text-blue-800 @break
                                            @case('zona') bg-green-100 text-green-800 @break
                                            @default bg-gray-100 text-gray-800
                                        @endswitch">
                                            {{ $email->category_label }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $email->zone ? $email->zone->name : 'Tutte le Zone' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if ($email->receive_all_notifications)
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                                Tutte
                                            </span>
                                        @elseif($email->notification_types)
                                            @php
                                                $types = is_array($email->notification_types)
                                                    ? $email->notification_types
                                                    : json_decode($email->notification_types, true);
                                                $count = is_array($types) ? count($types) : 0;
                                            @endphp
                                            @if ($count > 0)
                                                <span
                                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                    {{ $count }} tipi
                                                </span>
                                            @else
                                                <span
                                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                    Nessuna
                                                </span>
                                            @endif
                                        @else
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                Nessuna
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if ($email->is_active)
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                Attiva
                                            </span>
                                        @else
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                Disattivata
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex items-center justify-end space-x-2">
                                            <a href="{{ route('super-admin.institutional-emails.show', $email) }}"
                                                class="text-indigo-600 hover:text-indigo-900">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                                    </path>
                                                </svg>
                                            </a>
                                            <a href="{{ route('super-admin.institutional-emails.edit', $email) }}"
                                                class="text-gray-600 hover:text-gray-900">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                                    </path>
                                                </svg>
                                            </a>
                                            <form method="POST"
                                                action="{{ route('super-admin.institutional-emails.destroy', $email) }}"
                                                class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900"
                                                    onclick="return confirm('Sei sicuro di voler eliminare questa email istituzionale?')">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                        </path>
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $institutionalEmails->withQueryString()->links() }}
                </div>
            @else
                {{-- Empty State --}}
                <div class="px-6 py-12 text-center">
                    <div class="mx-auto h-12 w-12 text-gray-400">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                            </path>
                        </svg>
                    </div>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">Nessuna email istituzionale</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        @if (request()->hasAny(['search', 'category', 'zone_id', 'is_active']))
                            Nessuna email istituzionale corrisponde ai criteri di ricerca.
                        @else
                            Inizia creando la prima email istituzionale.
                        @endif
                    </p>
                    <div class="mt-6">
                        @if (request()->hasAny(['search', 'category', 'zone_id', 'is_active']))
                            <a href="{{ route('super-admin.institutional-emails.index') }}"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Reset Filtri
                            </a>
                        @else
                            <a href="{{ route('super-admin.institutional-emails.create') }}"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring ring-indigo-300 disabled:opacity-25 transition ease-in-out duration-150">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Nuova Email Istituzionale
                            </a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Select all functionality
                const selectAllCheckbox = document.getElementById('select-all');
                const itemCheckboxes = document.querySelectorAll('input[name="email_ids[]"]');
                const bulkActionForm = document.getElementById('bulk-action-form');

                selectAllCheckbox?.addEventListener('change', function() {
                    itemCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                });

                // Bulk action form submission
                bulkActionForm?.addEventListener('submit', function(e) {
                    const checkedItems = document.querySelectorAll('input[name="email_ids[]"]:checked');
                    const actionSelect = document.querySelector('select[name="action"]');

                    if (checkedItems.length === 0) {
                        e.preventDefault();
                        alert('Seleziona almeno una email istituzionale');
                        return;
                    }

                    if (!actionSelect.value) {
                        e.preventDefault();
                        alert('Seleziona un\'azione da eseguire');
                        return;
                    }

                    if (actionSelect.value === 'delete') {
                        if (!confirm('Sei sicuro di voler eliminare le email istituzionali selezionate?')) {
                            e.preventDefault();
                        }
                    }
                });
            });
        </script>
    @endpush
@endsection

{{-- resources/views/super-admin/zones/index.blade.php --}}
@extends('layouts.super-admin')

@section('title', 'Gestione Zone')

@section('header', 'Gestione Zone')

@section('content')
<div class="space-y-6">
    {{-- Header Section --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Gestione Zone</h1>
            <p class="mt-1 text-sm text-gray-600">
                Gestisci le zone territoriali del sistema
            </p>
        </div>
        <div class="mt-4 sm:mt-0">
            <a href="{{ route('super-admin.zones.create') }}"
               class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring ring-indigo-300 disabled:opacity-25 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                Nuova Zona
            </a>
        </div>
    </div>

    {{-- Filters Section --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <form method="GET" action="{{ route('super-admin.zones.index') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- Search --}}
                <div class="md:col-span-2">
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Cerca</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}"
                           placeholder="Nome zona, descrizione..."
                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>

                {{-- Status Filter --}}
                <div>
                    <label for="is_active" class="block text-sm font-medium text-gray-700 mb-1">Stato</label>
                    <select name="is_active" id="is_active"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="">Tutte</option>
                        <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>Attive</option>
                        <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Disattivate</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-between">
                <div class="flex space-x-2">
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        Filtra
                    </button>
                    @if(request()->hasAny(['search', 'is_active']))
                        <a href="{{ route('super-admin.zones.index') }}"
                           class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400 active:bg-gray-500 focus:outline-none focus:border-gray-500 focus:ring ring-gray-200 disabled:opacity-25 transition ease-in-out duration-150">
                            Reset Filtri
                        </a>
                    @endif
                </div>
            </div>
        </form>
    </div>

    {{-- Results Section --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        @if($zones->count() > 0)
            {{-- Table --}}
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="w-16 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Ordine
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Zona
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Statistiche
                            </th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Stato
                            </th>
                            <th scope="col" class="relative px-6 py-3">
                                <span class="sr-only">Azioni</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200" id="sortable-zones">
                        @foreach($zones as $zone)
                            <tr data-id="{{ $zone->id }}" class="hover:bg-gray-50 transition-colors duration-150">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <svg class="w-5 h-5 text-gray-400 cursor-move handle" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                                        </svg>
                                        <span class="ml-2 text-sm text-gray-500">{{ $zone->sort_order ?? $loop->iteration }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">{{ $zone->name }}</div>
                                        @if($zone->description)
                                            <div class="text-sm text-gray-500">{{ Str::limit($zone->description, 50) }}</div>
                                        @endif
                                        @if($zone->is_national)
                                            <div class="mt-1">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                    Nazionale
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 space-y-1">
                                        <div class="flex justify-between">
                                            <span class="text-gray-500">Utenti:</span>
                                            <span class="font-medium">{{ $zone->users_count ?? 0 }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-500">Tornei:</span>
                                            <span class="font-medium">{{ $zone->tournaments_count ?? 0 }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-500">Circoli:</span>
                                            <span class="font-medium">{{ $zone->clubs_count ?? 0 }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    @if($zone->is_active ?? true)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            <svg class="w-1.5 h-1.5 mr-1.5" fill="currentColor" viewBox="0 0 8 8">
                                                <circle cx="4" cy="4" r="3"/>
                                            </svg>
                                            Attiva
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            <svg class="w-1.5 h-1.5 mr-1.5" fill="currentColor" viewBox="0 0 8 8">
                                                <circle cx="4" cy="4" r="3"/>
                                            </svg>
                                            Disattivata
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-2">
                                        <a href="{{ route('super-admin.zones.show', $zone) }}"
                                           class="text-indigo-600 hover:text-indigo-900" title="Visualizza">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                        </a>
                                        <a href="{{ route('super-admin.zones.edit', $zone) }}"
                                           class="text-gray-600 hover:text-gray-900" title="Modifica">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </a>

                                        {{-- Toggle Active --}}
                                        <button type="button"
                                                onclick="toggleZoneStatus({{ $zone->id }})"
                                                class="{{ ($zone->is_active ?? true) ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900' }}"
                                                title="{{ ($zone->is_active ?? true) ? 'Disattiva' : 'Attiva' }}">
                                            @if($zone->is_active ?? true)
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 5.636"></path>
                                                </svg>
                                            @else
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                </svg>
                                            @endif
                                        </button>

                                        {{-- Delete --}}
                                        <form method="POST" action="{{ route('super-admin.zones.destroy', $zone) }}" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900" title="Elimina"
                                                    onclick="return confirm('Sei sicuro di voler eliminare questa zona?')">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
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
                {{ $zones->withQueryString()->links() }}
            </div>
        @else
            {{-- Empty State --}}
            <div class="px-6 py-12 text-center">
                <div class="mx-auto h-12 w-12 text-gray-400">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                </div>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Nessuna zona trovata</h3>
                <p class="mt-1 text-sm text-gray-500">
                    @if(request()->hasAny(['search', 'is_active']))
                        Nessuna zona corrisponde ai criteri di ricerca.
                    @else
                        Inizia creando la prima zona.
                    @endif
                </p>
                <div class="mt-6">
                    @if(request()->hasAny(['search', 'is_active']))
                        <a href="{{ route('super-admin.zones.index') }}"
                           class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Reset Filtri
                        </a>
                    @else
                        <a href="{{ route('super-admin.zones.create') }}"
                           class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Nuova Zona
                        </a>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
// Toggle zone status
async function toggleZoneStatus(zoneId) {
    try {
        const response = await fetch(`/super-admin/zones/${zoneId}/toggle-active`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });

        const result = await response.json();

        if (result.success) {
            // Reload page to reflect changes
            window.location.reload();
        } else {
            alert('Errore durante il cambio di stato: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Errore durante il cambio di stato');
    }
}

// Optional: Sortable functionality if you want to implement it
document.addEventListener('DOMContentLoaded', function() {
    console.log('Zone management loaded');
});
</script>
@endpush
@endsection

@extends('layouts.super-admin')

@section('title', 'Gestione Zone')

@section('content')
<div class="container mx-auto px-4 py-8">
    {{-- Header --}}
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Gestione Zone</h1>
            <p class="mt-2 text-gray-600">Gestisci le zone territoriali del sistema</p>
        </div>
        <div class="flex space-x-4">
            <button onclick="exportZones()"
                    class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-200 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Esporta
            </button>
            <a href="{{ route('super-admin.zones.create') }}"
               class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition duration-200 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Nuova Zona
            </a>
        </div>
    </div>

    {{-- Statistics Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-2 bg-blue-100 rounded-lg">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Totale Zone</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $stats['total_zones'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-2 bg-green-100 rounded-lg">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Zone Attive</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $stats['active_zones'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-2 bg-purple-100 rounded-lg">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Totale Utenti</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $stats['total_users'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-2 bg-yellow-100 rounded-lg">
                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Totale Tornei</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $stats['total_tournaments'] }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <form method="GET" action="{{ route('super-admin.zones.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="col-span-2">
                <label for="search" class="block text-sm font-medium text-gray-700">Cerca</label>
                <input type="text" name="search" id="search"
                       value="{{ request('search') }}"
                       placeholder="Nome zona, codice o descrizione..."
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            </div>

            <div>
                <label for="is_active" class="block text-sm font-medium text-gray-700">Stato</label>
                <select name="is_active" id="is_active"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <option value="">Tutte</option>
                    <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>Attive</option>
                    <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Non Attive</option>
                </select>
            </div>

            <div class="flex items-end space-x-2">
                <button type="submit"
                        class="flex-1 bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 transition duration-200">
                    Filtra
                </button>
                <a href="{{ route('super-admin.zones.index') }}"
                   class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400 transition duration-200">
                    Reset
                </a>
            </div>
        </form>
    </div>

    {{-- Zones Table --}}
    <div class="bg-white shadow-xl rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200" id="zones-table">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Ordine
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Zona
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Regione / Contatti
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Amministratore
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
                @forelse($zones as $zone)
                <tr data-id="{{ $zone->id }}" class="hover:bg-gray-50 transition-colors duration-150">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-gray-400 cursor-move handle" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                            <span class="ml-2 text-sm text-gray-500">{{ $zone->sort_order }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div>
                            <div class="text-sm font-medium text-gray-900">{{ $zone->name }}</div>
                            <div class="text-sm text-gray-500">Codice: {{ $zone->code }}</div>
                            @if($zone->description)
                                <div class="text-xs text-gray-400 truncate max-w-xs">{{ $zone->description }}</div>
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900">{{ $zone->region }}</div>
                        @if($zone->contact_email)
                            <div class="text-xs text-gray-500">📧 {{ $zone->contact_email }}</div>
                        @endif
                        @if($zone->contact_phone)
                            <div class="text-xs text-gray-500">📞 {{ $zone->contact_phone }}</div>
                        @endif
                        @if($zone->city)
                            <div class="text-xs text-gray-500">📍 {{ $zone->city }}</div>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @php
                            $admin = $zone->users()->where('user_type', 'zone_admin')->first();
                        @endphp
                        @if($admin)
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-8 w-8">
                                    @if($admin->profile_photo_path)
                                        <img class="h-8 w-8 rounded-full object-cover" src="{{ Storage::url($admin->profile_photo_path) }}" alt="">
                                    @else
                                        <div class="h-8 w-8 rounded-full bg-indigo-500 flex items-center justify-center">
                                            <span class="text-white font-medium text-xs">{{ substr($admin->name, 0, 1) }}</span>
                                        </div>
                                    @endif
                                </div>
                                <div class="ml-2">
                                    <div class="text-sm font-medium text-gray-900">{{ $admin->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $admin->email }}</div>
                                </div>
                            </div>
                        @else
                            <span class="text-sm text-gray-400 italic">Nessun amministratore</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <div>Utenti: {{ $zone->users_count }}</div>
                        <div>Tornei: {{ $zone->tournaments_count }}</div>
                        <div>Clubs: {{ $zone->clubs_count }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <button onclick="toggleActive({{ $zone->id }})"
                                class="relative inline-flex items-center h-6 rounded-full w-11 transition-colors duration-200 focus:outline-none
                                {{ $zone->is_active ? 'bg-green-500' : 'bg-gray-300' }}">
                            <span class="sr-only">Attiva/Disattiva</span>
                            <span class="inline-block w-4 h-4 transform transition-transform duration-200 bg-white rounded-full shadow
                                {{ $zone->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                        </button>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <div class="flex items-center justify-end space-x-2">
                            <a href="{{ route('super-admin.zones.show', $zone) }}"
                               class="text-gray-600 hover:text-gray-900" title="Visualizza">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </a>
                            <a href="{{ route('super-admin.zones.edit', $zone) }}"
                               class="text-indigo-600 hover:text-indigo-900" title="Modifica">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                            </a>
                            <button onclick="duplicateZone({{ $zone->id }})"
                                    class="text-blue-600 hover:text-blue-900" title="Duplica">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                </svg>
                            </button>
                            @if($zone->users_count == 0 && $zone->tournaments_count == 0 && $zone->clubs_count == 0)
                            <form action="{{ route('super-admin.zones.destroy', $zone) }}"
                                  method="POST"
                                  class="inline"
                                  onsubmit="return confirm('Sei sicuro di voler eliminare questa zona?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-900" title="Elimina">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <p class="mt-2">Nessuna zona trovata</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($zones->hasPages())
        <div class="mt-6">
            {{ $zones->appends(request()->query())->links() }}
        </div>
    @endif

    {{-- Info Box --}}
    <div class="mt-8 bg-blue-50 border-l-4 border-blue-400 p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-blue-700">
                    <strong>Suggerimento:</strong> Puoi riordinare le zone trascinandole.
                    Le zone con utenti, tornei o clubs associati non possono essere eliminate.
                </p>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
// Sortable zones
document.addEventListener('DOMContentLoaded', function() {
    const sortable = Sortable.create(document.getElementById('sortable-zones'), {
        handle: '.handle',
        animation: 150,
        onEnd: function(evt) {
            const zones = [];
            document.querySelectorAll('#sortable-zones tr').forEach((row, index) => {
                if (row.dataset.id) {
                    zones.push({
                        id: row.dataset.id,
                        sort_order: index * 10
                    });
                }
            });

            fetch('{{ route("super-admin.zones.update-order") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ zones })
            });
        }
    });
});

// Toggle active status
function toggleActive(zoneId) {
    fetch(`/super-admin/zones/${zoneId}/toggle-active`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

// Duplicate zone
function duplicateZone(zoneId) {
    if (confirm('Vuoi duplicare questa zona?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/super-admin/zones/${zoneId}/duplicate`;

        const token = document.createElement('input');
        token.type = 'hidden';
        token.name = '_token';
        token.value = '{{ csrf_token() }}';
        form.appendChild(token);

        document.body.appendChild(form);
        form.submit();
    }
}

// Export zones
function exportZones() {
    window.location.href = '{{ route("super-admin.zones.export") }}';
}
</script>
@endpush
@endsection

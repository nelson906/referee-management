@extends('layouts.super-admin')

@section('title', 'Gestione Utenti')

@section('content')
<div class="container mx-auto px-4 py-8">
    {{-- Header --}}
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Gestione Utenti</h1>
            <p class="mt-2 text-gray-600">Gestisci tutti gli utenti del sistema</p>
        </div>
        <div class="flex space-x-4">
            <a href="{{ route('super-admin.users.create') }}"
               class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition duration-200 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Nuovo Utente
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <form method="GET" action="{{ route('super-admin.users.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700">Cerca</label>
                <input type="text" name="search" id="search"
                       value="{{ request('search') }}"
                       placeholder="Nome, email o tessera..."
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            </div>

            <div>
                <label for="user_type" class="block text-sm font-medium text-gray-700">Tipo Utente</label>
                <select name="user_type" id="user_type"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <option value="">Tutti</option>
                    <option value="super_admin" {{ request('user_type') === 'super_admin' ? 'selected' : '' }}>Super Admin</option>
                    <option value="national_admin" {{ request('user_type') === 'national_admin' ? 'selected' : '' }}>Admin Nazionale</option>
                    <option value="zone_admin" {{ request('user_type') === 'zone_admin' ? 'selected' : '' }}>Admin Zona</option>
                    <option value="referee" {{ request('user_type') === 'referee' ? 'selected' : '' }}>Arbitro</option>
                </select>
            </div>

            <div>
                <label for="zone_id" class="block text-sm font-medium text-gray-700">Zona</label>
                <select name="zone_id" id="zone_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <option value="">Tutte le Zone</option>
                    @foreach($zones as $zone)
                        <option value="{{ $zone->id }}" {{ request('zone_id') == $zone->id ? 'selected' : '' }}>
                            {{ $zone->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="is_active" class="block text-sm font-medium text-gray-700">Stato</label>
                <select name="is_active" id="is_active"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <option value="">Tutti</option>
                    <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>Attivi</option>
                    <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Non Attivi</option>
                </select>
            </div>

            <div class="flex items-end space-x-2">
                <button type="submit"
                        class="flex-1 bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 transition duration-200">
                    Filtra
                </button>
                <a href="{{ route('super-admin.users.index') }}"
                   class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400 transition duration-200">
                    Reset
                </a>
            </div>
        </form>
    </div>

    {{-- Bulk Actions --}}
    <div class="bg-white rounded-lg shadow mb-6" id="bulk-actions" style="display: none;">
        <div class="p-4 border-b">
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-600">
                    <span id="selected-count">0</span> utenti selezionati
                </span>
                <div class="flex space-x-2">
                    <button onclick="bulkAction('activate')"
                            class="bg-green-600 text-white px-3 py-1 rounded text-sm hover:bg-green-700">
                        Attiva
                    </button>
                    <button onclick="bulkAction('deactivate')"
                            class="bg-yellow-600 text-white px-3 py-1 rounded text-sm hover:bg-yellow-700">
                        Disattiva
                    </button>
                    <button onclick="bulkAction('delete')"
                            class="bg-red-600 text-white px-3 py-1 rounded text-sm hover:bg-red-700">
                        Elimina
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Users Table --}}
    <div class="bg-white shadow-xl rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <input type="checkbox" id="select-all" class="rounded border-gray-300 text-indigo-600">
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Utente
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Tipo / Zona
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Contatti
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
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($users as $user)
                <tr class="hover:bg-gray-50 transition-colors duration-150">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <input type="checkbox" value="{{ $user->id }}" class="user-checkbox rounded border-gray-300 text-indigo-600">
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-10 w-10">
                                @if($user->profile_photo_path)
                                    <img class="h-10 w-10 rounded-full object-cover" src="{{ Storage::url($user->profile_photo_path) }}" alt="">
                                @else
                                    <div class="h-10 w-10 rounded-full bg-indigo-500 flex items-center justify-center">
                                        <span class="text-white font-medium">{{ substr($user->name, 0, 1) }}</span>
                                    </div>
                                @endif
                            </div>
                            <div class="ml-4">
                                <div class="text-sm font-medium text-gray-900">{{ $user->name }}</div>
                                <div class="text-sm text-gray-500">{{ $user->email }}</div>
                                @if($user->codice_tessera)
                                    <div class="text-xs text-gray-400">Tessera: {{ $user->codice_tessera }}</div>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                @switch($user->user_type)
                                    @case('super_admin') bg-purple-100 text-purple-800 @break
                                    @case('national_admin') bg-blue-100 text-blue-800 @break
                                    @case('zone_admin') bg-green-100 text-green-800 @break
                                    @case('referee') bg-gray-100 text-gray-800 @break
                                @endswitch">
                                @switch($user->user_type)
                                    @case('super_admin') Super Admin @break
                                    @case('national_admin') Admin Nazionale @break
                                    @case('zone_admin') Admin Zona @break
                                    @case('referee') Arbitro @break
                                @endswitch
                            </span>
                        </div>
                        @if($user->zone)
                            <div class="text-xs text-gray-500 mt-1">{{ $user->zone->name }}</div>
                        @endif
                        @if($user->livello_arbitro && $user->user_type === 'referee')
                            <div class="text-xs text-gray-400 mt-1">{{ ucfirst($user->livello_arbitro) }}</div>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        @if($user->telefono)
                            <div>📞 {{ $user->telefono }}</div>
                        @endif
                        @if($user->citta)
                            <div>📍 {{ $user->citta }}</div>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        @if($user->user_type === 'referee')
                            <div>Tornei: {{ $user->tournaments_count ?? 0 }}</div>
                            <div>Assegnazioni: {{ $user->assignments_count ?? 0 }}</div>
                        @elseif(in_array($user->user_type, ['zone_admin', 'national_admin']))
                            <div>Tornei creati: {{ $user->tournaments_count ?? 0 }}</div>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <button onclick="toggleActive({{ $user->id }})"
                                class="relative inline-flex items-center h-6 rounded-full w-11 transition-colors duration-200 focus:outline-none
                                {{ $user->is_active ? 'bg-green-500' : 'bg-gray-300' }}">
                            <span class="sr-only">Attiva/Disattiva</span>
                            <span class="inline-block w-4 h-4 transform transition-transform duration-200 bg-white rounded-full shadow
                                {{ $user->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                        </button>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <div class="flex items-center justify-end space-x-2">
                            <a href="{{ route('super-admin.users.show', $user) }}"
                               class="text-gray-600 hover:text-gray-900" title="Visualizza">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </a>
                            <a href="{{ route('super-admin.users.edit', $user) }}"
                               class="text-indigo-600 hover:text-indigo-900" title="Modifica">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                            </a>
                            <button onclick="resetPassword({{ $user->id }})"
                                    class="text-yellow-600 hover:text-yellow-900" title="Reset Password">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                                </svg>
                            </button>
                            @if($user->id !== auth()->id())
                            <button onclick="deleteUser({{ $user->id }})"
                                    class="text-red-600 hover:text-red-900" title="Elimina">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        <p class="mt-2">Nessun utente trovato</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($users->hasPages())
        <div class="mt-6">
            {{ $users->appends(request()->query())->links() }}
        </div>
    @endif
</div>

{{-- Hidden form for bulk actions --}}
<form id="bulk-form" method="POST" action="{{ route('super-admin.users.bulk-action') }}" style="display: none;">
    @csrf
    <input type="hidden" name="action" id="bulk-action">
    <input type="hidden" name="user_ids" id="bulk-user-ids">
</form>

{{-- Hidden forms for individual actions --}}
<form id="delete-form" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>

@push('scripts')
<script>
// Select all functionality
document.getElementById('select-all').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.user-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
    updateBulkActions();
});

// Individual checkbox functionality
document.querySelectorAll('.user-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', updateBulkActions);
});

function updateBulkActions() {
    const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
    const bulkActions = document.getElementById('bulk-actions');
    const selectedCount = document.getElementById('selected-count');

    if (checkedBoxes.length > 0) {
        bulkActions.style.display = 'block';
        selectedCount.textContent = checkedBoxes.length;
    } else {
        bulkActions.style.display = 'none';
    }
}

// Bulk actions
function bulkAction(action) {
    const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
    if (checkedBoxes.length === 0) {
        alert('Seleziona almeno un utente');
        return;
    }

    const userIds = Array.from(checkedBoxes).map(cb => cb.value);
    const actionText = {
        'activate': 'attivare',
        'deactivate': 'disattivare',
        'delete': 'eliminare'
    };

    if (confirm(`Sei sicuro di voler ${actionText[action]} ${userIds.length} utenti?`)) {
        document.getElementById('bulk-action').value = action;
        document.getElementById('bulk-user-ids').value = JSON.stringify(userIds);
        document.getElementById('bulk-form').submit();
    }
}

// Toggle active status
function toggleActive(userId) {
    fetch(`/super-admin/users/${userId}/toggle-active`, {
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
        } else {
            alert('Errore: ' + data.message);
        }
    });
}

// Reset password
function resetPassword(userId) {
    if (confirm('Sei sicuro di voler reimpostare la password di questo utente?')) {
        fetch(`/super-admin/users/${userId}/reset-password`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
        });
    }
}

// Delete user
function deleteUser(userId) {
    if (confirm('Sei sicuro di voler eliminare questo utente? Questa azione è irreversibile.')) {
        const form = document.getElementById('delete-form');
        form.action = `/super-admin/users/${userId}`;
        form.submit();
    }
}
</script>
@endpush
@endsection

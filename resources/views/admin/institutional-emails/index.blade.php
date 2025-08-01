@extends('layouts.admin')

@section('title', 'Email Istituzionali')

@section('content')
<div class="container mx-auto px-4 py-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Email Istituzionali</h1>
                <p class="text-gray-600">Gestisci gli indirizzi email per le notifiche automatiche</p>
            </div>

            <div class="flex space-x-3">
                <a href="{{ route('institutional-emails.export') }}"
                   class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Esporta CSV
                </a>

                <a href="{{ route('institutional-emails.create') }}"
                   class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Nuova Email
                </a>
            </div>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center">
                <div class="p-2 bg-blue-100 rounded-lg">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Totale Email</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $emails->count() }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center">
                <div class="p-2 bg-green-100 rounded-lg">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Attive</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $emails->where('is_active', true)->count() }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center">
                <div class="p-2 bg-yellow-100 rounded-lg">
                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Inattive</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $emails->where('is_active', false)->count() }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center">
                <div class="p-2 bg-purple-100 rounded-lg">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4V2a1 1 0 011-1h4a1 1 0 011 1v2h4a1 1 0 110 2h-1v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6H3a1 1 0 110-2h4z"/>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Categorie</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $emails->groupBy('category')->count() }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Categoria</label>
                <select name="category" id="category" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                    <option value="">Tutte le categorie</option>
                    @foreach(\App\Models\InstitutionalEmail::CATEGORIES as $key => $label)
                        <option value="{{ $key }}" {{ request('category') === $key ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="zone" class="block text-sm font-medium text-gray-700 mb-1">Zona</label>
                <select name="zone" id="zone" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                    <option value="">Tutte le zone</option>
                    @foreach($emails->whereNotNull('zone')->load('zone')->pluck('zone')->unique('id') as $zone)
                        <option value="{{ $zone->id }}" {{ request('zone') == $zone->id ? 'selected' : '' }}>
                            {{ $zone->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Stato</label>
                <select name="status" id="status" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                    <option value="">Tutti</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Attive</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inattive</option>
                </select>
            </div>

            <div class="flex items-end space-x-2">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                    Filtra
                </button>
                <a href="{{ route('institutional-emails.index') }}" class="px-4 py-2 bg-gray-300 text-gray-700 text-sm rounded-md hover:bg-gray-400">
                    Reset
                </a>
            </div>
        </form>
    </div>

    {{-- Email Table --}}
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <input type="checkbox" id="select-all" class="rounded border-gray-300">
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Nome & Email
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Categoria
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Zona
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Notifiche
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Stato
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Azioni
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($emails as $email)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input type="checkbox" name="selected_emails[]" value="{{ $email->id }}" class="rounded border-gray-300">
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap">
                                <div>
                                    <div class="text-sm font-medium text-gray-900">{{ $email->name }}</div>
                                    <div class="text-sm text-gray-500">{{ $email->email }}</div>
                                    @if($email->description)
                                        <div class="text-xs text-gray-400 mt-1">{{ Str::limit($email->description, 50) }}</div>
                                    @endif
                                </div>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $email->category_badge_color }}">
                                    {{ $email->category_display }}
                                </span>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $email->zone?->name ?? 'Tutte' }}
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($email->receive_all_notifications)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        🔔 Tutte
                                    </span>
                                @else
                                    <div class="text-xs text-gray-500">
                                        {{ count($email->notification_types ?? []) }} tipi
                                    </div>
                                @endif
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap">
                                <button onclick="toggleActive({{ $email->id }})"
                                        class="relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 {{ $email->is_active ? 'bg-indigo-600' : 'bg-gray-200' }}">
                                    <span class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200 {{ $email->is_active ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                </button>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <a href="{{ route('institutional-emails.show', $email) }}"
                                       class="text-indigo-600 hover:text-indigo-900">Visualizza</a>

                                    <a href="{{ route('institutional-emails.edit', $email) }}"
                                       class="text-green-600 hover:text-green-900">Modifica</a>

                                    <button onclick="testEmail({{ $email->id }})"
                                            class="text-blue-600 hover:text-blue-900">Test</button>

                                    <form method="POST" action="{{ route('institutional-emails.destroy', $email) }}"
                                          onsubmit="return confirm('Sei sicuro di voler eliminare questa email?')" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-900">Elimina</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">Nessuna email istituzionale</h3>
                                <p class="mt-1 text-sm text-gray-500">Inizia creando la prima email istituzionale.</p>
                                <div class="mt-6">
                                    <a href="{{ route('institutional-emails.create') }}"
                                       class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                        Nuova Email
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Bulk Actions --}}
    @if($emails->count() > 0)
    <div class="mt-4 bg-white rounded-lg shadow p-4">
        <form method="POST" action="{{ route('institutional-emails.bulk-action') }}" id="bulk-form">
            @csrf
            <div class="flex items-center space-x-4">
                <span class="text-sm text-gray-700">Azioni selezionate:</span>

                <select name="action" class="border border-gray-300 rounded-md px-3 py-2 text-sm">
                    <option value="">Seleziona azione...</option>
                    <option value="activate">Attiva</option>
                    <option value="deactivate">Disattiva</option>
                    <option value="delete">Elimina</option>
                </select>

                <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 disabled:opacity-50"
                        disabled id="bulk-submit">
                    Esegui
                </button>

                <span class="text-sm text-gray-500" id="selected-count">0 selezionate</span>
            </div>
        </form>
    </div>
    @endif
</div>

{{-- Test Email Modal --}}
<div id="test-email-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Test Email</h3>

            <form id="test-email-form">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Oggetto</label>
                    <input type="text" name="test_subject" value="Test Email Sistema"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Messaggio</label>
                    <textarea name="test_message" rows="4"
                              class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">Questa è una email di test inviata dal sistema di gestione arbitri.</textarea>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeTestModal()"
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                        Annulla
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Invia Test
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Toggle active status
function toggleActive(emailId) {
    fetch(`/institutional-emails/${emailId}/toggle-active`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

// Test email functionality
let currentTestEmailId = null;

function testEmail(emailId) {
    currentTestEmailId = emailId;
    document.getElementById('test-email-modal').classList.remove('hidden');
}

function closeTestModal() {
    document.getElementById('test-email-modal').classList.add('hidden');
    currentTestEmailId = null;
}

document.getElementById('test-email-form').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);

    fetch(`/institutional-emails/${currentTestEmailId}/test`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            closeTestModal();
        }
    });
});

// Bulk actions
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('select-all');
    const checkboxes = document.querySelectorAll('input[name="selected_emails[]"]');
    const bulkSubmit = document.getElementById('bulk-submit');
    const selectedCount = document.getElementById('selected-count');

    function updateBulkActions() {
        const checkedBoxes = document.querySelectorAll('input[name="selected_emails[]"]:checked');
        const count = checkedBoxes.length;

        selectedCount.textContent = `${count} selezionate`;
        bulkSubmit.disabled = count === 0;
    }

    selectAll.addEventListener('change', function() {
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateBulkActions();
    });

    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkActions);
    });

    document.getElementById('bulk-form').addEventListener('submit', function(e) {
        const checkedBoxes = document.querySelectorAll('input[name="selected_emails[]"]:checked');
        if (checkedBoxes.length === 0) {
            e.preventDefault();
            alert('Seleziona almeno una email.');
            return;
        }

        const action = this.querySelector('select[name="action"]').value;
        if (!action) {
            e.preventDefault();
            alert('Seleziona un\'azione.');
            return;
        }

        if (action === 'delete') {
            if (!confirm(`Sei sicuro di voler eliminare ${checkedBoxes.length} email selezionate?`)) {
                e.preventDefault();
            }
        }
    });
});
</script>
@endsection

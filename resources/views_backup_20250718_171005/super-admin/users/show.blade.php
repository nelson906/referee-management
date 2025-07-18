@extends('layouts.super-admin')

@section('title', 'Utente: ' . $user->name)

@section('content')
<div class="container mx-auto px-4 py-8 max-w-6xl">
    {{-- Header --}}
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="flex-shrink-0 mr-4">
                    @if($user->profile_photo_path)
                        <img class="h-16 w-16 rounded-full object-cover" src="{{ Storage::url($user->profile_photo_path) }}" alt="">
                    @else
                        <div class="h-16 w-16 rounded-full bg-indigo-500 flex items-center justify-center">
                            <span class="text-white font-medium text-xl">{{ substr($user->name, 0, 1) }}</span>
                        </div>
                    @endif
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">{{ $user->name }}</h1>
                    <p class="mt-1 text-gray-600">{{ $user->email }}</p>
                    @if($user->codice_tessera)
                        <p class="text-sm text-gray-500">Tessera: {{ $user->codice_tessera }}</p>
                    @endif
                </div>
            </div>
            <div class="flex space-x-4">
                <a href="{{ route('super-admin.users.edit', $user) }}"
                   class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition duration-200 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Modifica
                </a>
                <a href="{{ route('super-admin.users.index') }}"
                   class="text-gray-600 hover:text-gray-900 flex items-center px-4 py-2">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Torna all'elenco
                </a>
            </div>
        </div>
    </div>

    {{-- Status Badges --}}
    <div class="mb-6 flex items-center space-x-4">
        <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full
            {{ $user->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
            {{ $user->is_active ? 'Attivo' : 'Non Attivo' }}
        </span>
        <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full
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
        @if($user->zone)
            <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                {{ $user->zone->name }}
            </span>
        @endif
        @if($user->livello_arbitro && $user->user_type === 'referee')
            <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                {{ ucfirst($user->livello_arbitro) }}
            </span>
        @endif
    </div>

    {{-- Statistics Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-2 bg-blue-100 rounded-lg">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Tornei</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $stats['tournaments_count'] }}</p>
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
                    <p class="text-sm font-medium text-gray-500">Assegnazioni</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $stats['assignments_count'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-2 bg-yellow-100 rounded-lg">
                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">In Attesa</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $stats['pending_assignments'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-2 bg-purple-100 rounded-lg">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Completate</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $stats['completed_assignments'] }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- User Details Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        {{-- Personal Information --}}
        <div class="lg:col-span-2">
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Informazioni Personali</h2>
                <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Nome Completo</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $user->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Email</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <a href="mailto:{{ $user->email }}" class="text-indigo-600 hover:text-indigo-500">
                                {{ $user->email }}
                            </a>
                        </dd>
                    </div>
                    @if($user->telefono)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Telefono</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <a href="tel:{{ $user->telefono }}" class="text-indigo-600 hover:text-indigo-500">
                                {{ $user->telefono }}
                            </a>
                        </dd>
                    </div>
                    @endif
                    @if($user->data_nascita)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Data di Nascita</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            {{ $user->data_nascita->format('d/m/Y') }}
                            <span class="text-gray-500">({{ $user->data_nascita->age }} anni)</span>
                        </dd>
                    </div>
                    @endif
                    @if($user->indirizzo)
                    <div class="sm:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">Indirizzo</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            {{ $user->indirizzo }}
                            @if($user->citta), {{ $user->citta }}@endif
                            @if($user->cap) {{ $user->cap }}@endif
                        </dd>
                    </div>
                    @endif
                    @if($user->codice_tessera)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Codice Tessera</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $user->codice_tessera }}</dd>
                    </div>
                    @endif
                </dl>
            </div>
        </div>

        {{-- Account Information --}}
        <div>
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Informazioni Account</h2>
                <dl class="space-y-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Tipo Utente</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            @switch($user->user_type)
                                @case('super_admin') Super Admin @break
                                @case('national_admin') Admin Nazionale (CRC) @break
                                @case('zone_admin') Admin Zona @break
                                @case('referee') Arbitro @break
                            @endswitch
                        </dd>
                    </div>
                    @if($user->zone)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Zona</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $user->zone->name }}</dd>
                    </div>
                    @endif
                    @if($user->livello_arbitro && $user->user_type === 'referee')
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Livello Arbitro</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ config('referee.referee_levels')[$user->livello_arbitro] ?? ucfirst($user->livello_arbitro) }}</dd>
                    </div>
                    @endif
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Stato</dt>
                        <dd class="mt-1">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                {{ $user->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $user->is_active ? 'Attivo' : 'Non Attivo' }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Ultimo Accesso</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            {{ $user->last_login_at ? $user->last_login_at->format('d/m/Y H:i') : 'Mai' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Registrato il</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $user->created_at->format('d/m/Y H:i') }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>

    {{-- Recent Activity --}}
    @if($user->user_type === 'referee' && $user->assignments->count() > 0)
    <div class="bg-white shadow rounded-lg overflow-hidden mb-8">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-900">Assegnazioni Recenti</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Torneo
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Date
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Stato
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Assegnato il
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($user->assignments()->with('tournament')->latest()->limit(10)->get() as $assignment)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">
                                {{ $assignment->tournament->name }}
                            </div>
                            <div class="text-sm text-gray-500">
                                {{ $assignment->tournament->club->name ?? 'N/A' }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ \Carbon\Carbon::parse($assignment->tournament->start_date)->format('d/m/Y') }} -
                            {{ \Carbon\Carbon::parse($assignment->tournament->end_date)->format('d/m/Y') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                @switch($assignment->status)
                                    @case('pending') bg-yellow-100 text-yellow-800 @break
                                    @case('accepted') bg-green-100 text-green-800 @break
                                    @case('rejected') bg-red-100 text-red-800 @break
                                    @default bg-gray-100 text-gray-800
                                @endswitch">
                                @switch($assignment->status)
                                    @case('pending') In Attesa @break
                                    @case('accepted') Accettato @break
                                    @case('rejected') Rifiutato @break
                                    @default {{ ucfirst($assignment->status) }}
                                @endswitch
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $assignment->created_at->format('d/m/Y') }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Tournaments Created (for admins) --}}
    @if(in_array($user->user_type, ['zone_admin', 'national_admin']) && $user->tournaments->count() > 0)
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-900">Tornei Creati</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Nome Torneo
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Categoria
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Date
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Stato
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Creato il
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($user->tournaments()->with(['category', 'club'])->latest()->limit(10)->get() as $tournament)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">
                                {{ $tournament->name }}
                            </div>
                            <div class="text-sm text-gray-500">
                                {{ $tournament->club->name ?? 'N/A' }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $tournament->category->name ?? 'N/A' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ \Carbon\Carbon::parse($tournament->start_date)->format('d/m/Y') }} -
                            {{ \Carbon\Carbon::parse($tournament->end_date)->format('d/m/Y') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                @switch($tournament->status)
                                    @case('draft') bg-gray-100 text-gray-800 @break
                                    @case('open') bg-green-100 text-green-800 @break
                                    @case('closed') bg-yellow-100 text-yellow-800 @break
                                    @case('assigned') bg-blue-100 text-blue-800 @break
                                    @case('completed') bg-purple-100 text-purple-800 @break
                                    @default bg-gray-100 text-gray-800
                                @endswitch">
                                {{ ucfirst($tournament->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $tournament->created_at->format('d/m/Y') }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Quick Actions --}}
    <div class="mt-8 bg-gray-50 rounded-lg p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Azioni Rapide</h3>
        <div class="flex flex-wrap gap-4">
            <button onclick="resetPassword({{ $user->id }})"
                    class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 transition duration-200 flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                </svg>
                Reset Password
            </button>

            @if($user->user_type === 'referee')
            <a href="{{ route('admin.referees.show', $user) }}"
               class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200 flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
                Vedi Profilo Arbitro
            </a>
            @endif

            <button onclick="toggleActive({{ $user->id }})"
                    class="{{ $user->is_active ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700' }} text-white px-4 py-2 rounded-lg transition duration-200 flex items-center"
                    {{ $user->id === auth()->id() ? 'disabled' : '' }}>
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"></path>
                </svg>
                {{ $user->is_active ? 'Disattiva' : 'Attiva' }}
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
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
</script>
@endpush
@endsection

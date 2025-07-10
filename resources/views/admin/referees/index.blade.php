@extends('layouts.admin')

@section('title', 'Gestione Arbitri')

@section('content')
<div class="container mx-auto px-4 py-6">
    {{-- Header con componente --}}
    <x-table-header
        title="Gestione Arbitri"
        description="Gestisci gli arbitri della tua zona"
        :create-route="route('admin.referees.create')"
        create-text="Nuovo Arbitro" />

    {{-- Filters --}}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Cerca</label>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Nome, email, codice..."
                       class="w-full border border-gray-300 rounded-md px-3 py-2">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Livello</label>
                <select name="level" class="w-full border border-gray-300 rounded-md px-3 py-2">
                    <option value="">Tutti i livelli</option>
                    @foreach($levels as $key => $label)
                        <option value="{{ $key }}" {{ request('level') == $key ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Zona</label>
                <select name="zone_id" class="w-full border border-gray-300 rounded-md px-3 py-2">
                    <option value="">Tutte le zone</option>
                    @foreach($zones as $zone)
                        <option value="{{ $zone->id }}" {{ request('zone_id') == $zone->id ? 'selected' : '' }}>
                            {{ $zone->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Stato</label>
                <select name="status" class="w-full border border-gray-300 rounded-md px-3 py-2">
                    <option value="">Tutti</option>
                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Attivi</option>
                    <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Non Attivi</option>
                </select>
            </div>

            <div class="flex items-end space-x-2">
                <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                    Filtra
                </button>
                <a href="{{ route('admin.referees.index') }}" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400">
                    Reset
                </a>
            </div>
        </form>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-lg shadow overflow-hidden">
        @if($referees->count() > 0)
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Arbitro</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Livello</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Zona</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stato</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Azioni</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($referees as $referee)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                {{-- Info arbitro --}}
                                <div class="text-sm font-medium text-gray-900">{{ $referee->name }}</div>
                                <div class="text-sm text-gray-500">{{ $referee->email }}</div>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                    {{ $levels[$referee->referee->level ?? ''] ?? 'N/A'  }}
                                </span>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $referee->zone->name ?? ($referee->referee->zone->name ?? 'N/A') }}
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap">
                                {{-- Status con componente --}}
                                <x-status-badge :status="$referee->is_active" />
                            </td>

<td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
    <x-table-actions-referee :referee="$referee" />
</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection

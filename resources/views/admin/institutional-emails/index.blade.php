{{-- File: resources/views/admin/institutional-emails/index.blade.php --}}
<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                📮 Email Istituzionali
            </h2>
            <div class="flex space-x-3">
                <a href="{{ route('notifications.index') }}"
                   class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    📧 Notifiche
                </a>
                <a href="{{ route('institutional-emails.create') }}"
                   class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                    ➕ Nuova Email
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Flash Messages --}}
            @if(session('success'))
                <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-400 rounded-lg">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">

                    {{-- Stats Header --}}
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-blue-600">{{ $institutionalEmails->total() }}</div>
                            <div class="text-sm text-blue-600">Totale Email</div>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-green-600">
                                {{ $institutionalEmails->where('is_active', true)->count() }}
                            </div>
                            <div class="text-sm text-green-600">Attive</div>
                        </div>
                        <div class="bg-yellow-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-yellow-600">
                                {{ $institutionalEmails->where('receive_all_notifications', true)->count() }}
                            </div>
                            <div class="text-sm text-yellow-600">Globali</div>
                        </div>
                        <div class="bg-purple-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-purple-600">
                                {{ $institutionalEmails->groupBy('category')->count() }}
                            </div>
                            <div class="text-sm text-purple-600">Categorie</div>
                        </div>
                    </div>

                    {{-- Filter Bar --}}
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <form method="GET" class="flex flex-wrap gap-4 items-end">
                            <div class="flex-1 min-w-48">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Categoria</label>
                                <select name="category" class="w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="">Tutte le categorie</option>
                                    @foreach(\App\Models\InstitutionalEmail::CATEGORIES as $key => $label)
                                        <option value="{{ $key }}" {{ request('category') === $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="flex-1 min-w-48">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Stato</label>
                                <select name="status" class="w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="">Tutti gli stati</option>
                                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Attive</option>
                                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inattive</option>
                                </select>
                            </div>

                            <div class="flex-1 min-w-48">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Ambito</label>
                                <select name="scope" class="w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="">Tutti</option>
                                    <option value="global" {{ request('scope') === 'global' ? 'selected' : '' }}>Globali</option>
                                    <option value="zonal" {{ request('scope') === 'zonal' ? 'selected' : '' }}>Zonali</option>
                                </select>
                            </div>

                            <div class="flex space-x-2">
                                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                    🔍 Filtra
                                </button>
                                <a href="{{ route('institutional-emails.index') }}" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                    🗑️ Reset
                                </a>
                            </div>
                        </form>
                    </div>

                    @if($institutionalEmails->count() > 0)
                        {{-- Emails Table --}}
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
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
                                            Ambito
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
                                    @foreach($institutionalEmails->groupBy('category') as $category => $categoryEmails)
                                        {{-- Category Header --}}
                                        <tr class="bg-gray-100">
                                            <td colspan="6" class="px-6 py-3 font-medium text-gray-900">
                                                📂 {{ \App\Models\InstitutionalEmail::CATEGORIES[$category] ?? ucfirst($category) }}
                                                ({{ $categoryEmails->count() }})
                                            </td>
                                        </tr>

                                        {{-- Category Emails --}}
                                        @foreach($categoryEmails as $email)
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center">
                                                        <div class="flex-shrink-0 h-10 w-10">
                                                            <div class="h-10 w-10 rounded-full {{ $email->is_active ? 'bg-green-100' : 'bg-gray-100' }} flex items-center justify-center">
                                                                @if($email->receive_all_notifications)
                                                                    🌍
                                                                @elseif($email->zone_id)
                                                                    🏷️
                                                                @else
                                                                    📧
                                                                @endif
                                                            </div>
                                                        </div>
                                                        <div class="ml-4">
                                                            <div class="text-sm font-medium text-gray-900">
                                                                {{ $email->name }}
                                                            </div>
                                                            <div class="text-sm text-gray-500">
                                                                {{ $email->email }}
                                                            </div>
                                                            @if($email->description)
                                                                <div class="text-xs text-gray-400 mt-1">
                                                                    {{ Str::limit($email->description, 50) }}
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </td>

                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-purple-100 text-purple-800">
                                                        {{ $email->category_label }}
                                                    </span>
                                                </td>

                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    @if($email->zone)
                                                        <div class="text-sm text-gray-900">{{ $email->zone->name }}</div>
                                                    @else
                                                        <span class="text-sm text-gray-500">Tutte le zone</span>
                                                    @endif
                                                </td>

                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    @if($email->receive_all_notifications)
                                                        <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">
                                                            🌍 Globale
                                                        </span>
                                                    @else
                                                        <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800">
                                                            🏷️ Specifico
                                                        </span>
                                                    @endif
                                                </td>

                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full
                                                        {{ $email->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                        {{ $email->status_label }}
                                                    </span>
                                                </td>

                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <div class="flex space-x-2">
                                                        <a href="{{ route('institutional-emails.edit', $email) }}"
                                                           class="text-indigo-600 hover:text-indigo-900">
                                                            ✏️ Modifica
                                                        </a>

                                                        <form method="POST" action="{{ route('institutional-emails.toggle', $email) }}" class="inline">
                                                            @csrf
                                                            <button type="submit"
                                                                    class="{{ $email->is_active ? 'text-yellow-600 hover:text-yellow-900' : 'text-green-600 hover:text-green-900' }}">
                                                                {{ $email->is_active ? '⏸️ Disattiva' : '▶️ Attiva' }}
                                                            </button>
                                                        </form>

                                                        <form method="POST" action="{{ route('institutional-emails.destroy', $email) }}" class="inline">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit"
                                                                    class="text-red-600 hover:text-red-900"
                                                                    onclick="return confirm('Sei sicuro di voler eliminare questa email istituzionale?')">
                                                                🗑️ Elimina
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Pagination --}}
                        <div class="mt-6">
                            {{ $institutionalEmails->links() }}
                        </div>

                    @else
                        {{-- Empty State --}}
                        <div class="text-center py-12">
                            <div class="text-6xl mb-4">📮</div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Nessuna email istituzionale trovata</h3>
                            <p class="text-gray-500 mb-6">Non ci sono email istituzionali che corrispondono ai criteri di ricerca.</p>
                            <a href="{{ route('institutional-emails.create') }}"
                               class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                                ➕ Crea Prima Email
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>

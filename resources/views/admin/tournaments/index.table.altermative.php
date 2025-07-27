{{-- Molto bello ma non lo uso --}}
{{-- Tournaments Table - RESPONSIVE --}}
    <div class="bg-white shadow rounded-lg overflow-hidden">
        {{-- Desktop Table --}}
        <div class="hidden md:block">
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
                            Circolo
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Categoria
                        </th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Arbitri
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
                    @forelse($tournaments as $tournament)
                    <tr class="hover:bg-gray-50 transition-colors duration-150">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900">
                                {{ $tournament->name }}
                            </div>
                            <div class="text-sm text-gray-500">
                                @if($tournament->days_until_deadline >= 0)
                                    <span class="text-xs {{ $tournament->days_until_deadline <= 3 ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
                                        ({{ $tournament->days_until_deadline }} giorni)
                                    </span>
                                @else
                                    <span class="text-xs text-gray-500">(scaduta)</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">
                                {{ $tournament->start_date->format('d/m') }} - {{ $tournament->end_date->format('d/m/Y') }}
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ $tournament->start_date->diffInDays($tournament->end_date) + 1 }} giorni
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm text-gray-900">{{ $tournament->club->name }}</div>
                            @if($isNationalAdmin)
                                <div class="text-xs text-gray-500">{{ $tournament->zone->name }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-3 h-3 rounded-full mr-2"
                                     style="background-color: {{ $tournament->tournamentType->calendar_color }}"></div>
                                <span class="text-sm text-gray-900">
                                    {{ $tournament->tournamentType->name }}
                                </span>
                            </div>
                            @if($tournament->tournamentType->is_national)
                                <span class="text-xs text-blue-600">Nazionale</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            <div class="text-sm text-gray-900">
                                {{ $tournament->assignments()->count() }} / {{ $tournament->required_referees }}
                            </div>
                            <div class="text-xs text-gray-500">
                                Disp: {{ $tournament->availabilities()->count() }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                bg-{{ $tournament->status_color }}-100 text-{{ $tournament->status_color }}-800">
                                {{ $tournament->status_label }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex space-x-2">
                                <a href="{{ route('admin.tournaments.show', $tournament) }}"
                                   class="text-indigo-600 hover:text-indigo-900">Visualizza</a>

                                <a href="{{ route('admin.assignments.assign-referees', $tournament) }}"
                                   class="bg-green-600 text-white px-3 py-1 rounded text-sm hover:bg-green-700">
                                    👥 Assegna Comitato
                                </a>

                                @if($tournament->assignments()->count() > 0)
                                    <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs">
                                        {{ $tournament->assignments()->count() }} assegnati
                                    </span>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                            <p class="text-gray-500">Nessun torneo trovato</p>
                            <p class="text-sm text-gray-400 mt-1">Prova a modificare i filtri di ricerca</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobile Cards --}}
        <div class="md:hidden">
            @forelse($tournaments as $tournament)
            <div class="border-b border-gray-200 p-4">
                <div class="flex justify-between items-start mb-2">
                    <h3 class="text-sm font-medium text-gray-900">{{ $tournament->name }}</h3>
                    <span class="px-2 py-1 text-xs font-semibold rounded-full
                        bg-{{ $tournament->status_color }}-100 text-{{ $tournament->status_color }}-800">
                        {{ $tournament->status_label }}
                    </span>
                </div>

                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Date:</span>
                        <span class="text-gray-900">{{ $tournament->start_date->format('d/m') }} - {{ $tournament->end_date->format('d/m/Y') }}</span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-gray-500">Circolo:</span>
                        <span class="text-gray-900">{{ $tournament->club->name }}</span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-gray-500">Categoria:</span>
                        <div class="flex items-center">
                            <div class="w-3 h-3 rounded-full mr-2"
                                 style="background-color: {{ $tournament->tournamentType->calendar_color }}"></div>
                            <span class="text-gray-900">{{ $tournament->tournamentType->name }}</span>
                        </div>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-gray-500">Arbitri:</span>
                        <span class="text-gray-900">
                            {{ $tournament->assignments()->count() }} / {{ $tournament->required_referees }}
                            <span class="text-xs text-gray-500">(Disp: {{ $tournament->availabilities()->count() }})</span>
                        </span>
                    </div>
                </div>

                <div class="mt-3 flex space-x-2">
                    <a href="{{ route('admin.tournaments.show', $tournament) }}"
                       class="text-indigo-600 hover:text-indigo-900 text-sm">Visualizza</a>

                    <a href="{{ route('admin.assignments.assign-referees', $tournament) }}"
                       class="bg-green-600 text-white px-2 py-1 rounded text-xs hover:bg-green-700">
                        Assegna
                    </a>

                    @if($tournament->assignments()->count() > 0)
                        <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs">
                            {{ $tournament->assignments()->count() }} assegnati
                        </span>
                    @endif
                </div>
            </div>
            @empty
            <div class="p-6 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                <p class="text-gray-500">Nessun torneo trovato</p>
                <p class="text-sm text-gray-400 mt-1">Prova a modificare i filtri di ricerca</p>
            </div>
            @endforelse
        </div>
    </div>

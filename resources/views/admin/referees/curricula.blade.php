@extends('layouts.admin')

@section('content')
    <div class="container mx-auto px-4">
        <div class="flex flex-wrap -mx-4">
            <div class="w-full px-4">
                <div class="bg-white rounded-lg shadow-md">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold">Curricula Arbitri</h3>
                    </div>
                    <div class="p-6">
                        <table class="min-w-full divide-y divide-gray-200 table-bordered">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Codice</th>
                                    <th>Livello Attuale</th>
                                    <th>Zona</th>
                                    <th>Tornei Totali</th>
                                    <th>Ultimo Torneo</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($referees as $referee)
                                    @php
                                        // $totalTournaments = Assignment::where('user_id', $referee->id)->count();

                                        // $lastAssignment = Assignment::with('tournament')
                                        //     ->where('user_id', $referee->id)
                                        //     ->orderBy('assigned_at', 'desc')
                                        //     ->first();

                                        $lastTournament = $lastAssignment ? $lastAssignment->tournament->name : null;
                                        $lastDate = $lastAssignment ? $lastAssignment->tournament->start_date : null;
                                    @endphp

                                    <tr>
                                        <td>{{ $referee->name }}</td>
                                        <td>{{ $referee->referee_code }}</td>
                                        <td>
                                            <span
                                                class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">{{ $referee->level }}</span>
                                        </td>
                                        <td>{{ $referee->zone->name ?? 'N/A' }}</td>
                                        <td>
                                            <span
                                                class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">{{ $totalTournaments }}</span>
                                        </td>
                                        <td>
                                            @if ($lastTournament)
                                                {{ $lastTournament }}<br>
                                                <small
                                                    class="text-gray-500">{{ \Carbon\Carbon::parse($lastDate)->format('d/m/Y') }}</small>
                                            @else
                                                <span class="text-gray-500">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.referee.curriculum', $referee->id) }}"
                                                class="px-4 py-2 rounded-lg font-medium transition-colors duration-200 px-3 py-1 text-sm bg-blue-600 hover:bg-blue-700 text-white">
                                                <i class="fas fa-eye"></i> Visualizza
                                            </a>
                                            <a href="{{ route('admin.referee.curriculum', $referee->id) }}?print=1"
                                                class="px-4 py-2 rounded-lg font-medium transition-colors duration-200 px-3 py-1 text-sm bg-gray-500 hover:bg-gray-600 text-white"
                                                target="_blank">
                                                <i class="fas fa-print"></i> Stampa
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        {{ $referees->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

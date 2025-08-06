@extends('layouts.referee')

@section('content')
    <div class="container mx-auto">
        <div class="flex justify-between items-center mb-4">
            <h1>Curriculum {{ $referee->name }}</h1>
            <div>
                @if (request()->get('print'))
                    <script>
                        window.print();
                    </script>
                @endif
                <button onclick="window.print()"
                    class="px-4 py-2 rounded-lg font-medium transition-colors duration-200 bg-blue-600 hover:bg-blue-700 text-white">
                    <i class="fas fa-print"></i> Stampa
                </button>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md mb-4">
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-1 gap-6">
                    <div class="md:">
                        <p><strong>Codice:</strong> {{ $referee->referee_code }}</p>
                        <p><strong>Email:</strong> {{ $referee->email }}</p>
                    </div>
                    <div class="md:w-6/12 px-4">
                        <p><strong>Telefono:</strong> {{ $referee->phone ?? 'N/A' }}</p>
                        <p><strong>Zona:</strong> {{ $referee->zone->name ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>
        </div>

        @foreach ($curriculumData as $year => $data)
            @if ($data['count'] > 0)
                <div class="bg-white rounded-lg shadow-md mb-4">
                    <div class="px-6 py-4 border-b border-gray-200 bg-blue-600 text-white">
                        <h3 class="mb-0">
                            Anno {{ $year }}
                            <span
                                class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600">{{ $data['count'] }}
                                tornei</span>
                            <span
                                class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Livello:
                                {{ $data['level'] }}</span>
                        </h3>
                    </div>
                    <div class="p-6">
                        <table class="min-w-full divide-y divide-gray-200 table-sm">
                            <thead>
                                <tr>
                                    <th width="15%">Data</th>
                                    <th width="35%">Torneo</th>
                                    <th width="30%">Circolo</th>
                                    <th width="20%">Ruolo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($data['assignments']->sortByDesc('start_date') as $assignment)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($assignment->start_date)->format('d/m') }} -
                                            {{ \Carbon\Carbon::parse($assignment->end_date)->format('d/m') }}</td>
                                        <td><strong>{{ $assignment->name }}</strong></td>
                                        <td>{{ $assignment->club_name }}</td>
                                        <td>
                                            <span
                                                class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $assignment->role == 'Direttore di Torneo' ? 'badge-success' : 'badge-info' }}">
                                                {{ $assignment->role }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @endforeach
    </div>

    <style media="print">
        .btn {
            display: none !important;
        }

        .card {
            page-break-inside: avoid;
        }

        body {
            font-size: 12pt;
        }
    </style>
@endsection

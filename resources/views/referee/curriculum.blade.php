{{-- USA IL LAYOUT DINAMICO --}}
@extends($layout ?? 'layouts.app')

@section('content')
<div class="container mx-auto">
    <div class="flex justify-between items-center mb-4">
        <h1>Curriculum {{ $referee->name }}</h1>
        <div>
            @if(request()->get('print'))
                <script>window.print();</script>
            @endif
            <button onclick="window.print()" class="px-4 py-2 rounded-lg font-medium transition-colors duration-200 bg-blue-600 hover:bg-blue-700 text-white">
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

@foreach($curriculumData as $yearData)
<div class="bg-white rounded-lg shadow-md mb-4">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3>Anno {{ $yearData['year'] }}
            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">{{ $yearData['level'] }}</span>
            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">{{ $yearData['total'] }} tornei</span>
        </h3>
    </div>
    <div class="p-6">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 table-sm">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Torneo</th>
                        <th>Circolo</th>
                        <th>Ruolo</th>
                        <th>Stato</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($yearData['assignments'] as $assignment)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($assignment->start_date)->format('d/m/Y') }}</td>
                        <td>{{ $assignment->name }}</td>
                        <td>{{ $assignment->club_name ?? 'N/D' }}</td>
                        <td>
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full badge-{{ $assignment->role =='Direttore di Torneo' ? 'primary' : ($assignment->role == 'Arbitro' ? 'success' : 'warning') }}">
                                {{ $assignment->role }}
                            </span>
                        </td>
                        <td>
                            @if($assignment->is_confirmed)
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Confermato</span>
                            @else
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Da confermare</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Riepilogo ruoli --}}
        <div class="mt-3">
            <strong>Riepilogo:</strong>
            TD: {{ $yearData['by_role']['td'] }} |
            Arbitro: {{ $yearData['by_role']['arbitro'] }} |
            Osservatore: {{ $yearData['by_role']['osservatore'] }}
        </div>
    </div>
</div>
@endforeach
</div>

<style media="print">
    .btn { display: none !important; }
    .card { page-break-inside: avoid; }
    body { font-size: 12pt; }
</style>
@endsection

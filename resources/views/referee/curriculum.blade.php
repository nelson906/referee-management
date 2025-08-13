{{-- USA IL LAYOUT DINAMICO --}}
@extends($layout ?? 'layouts.referee')

@section('content')
<div class="container mx-auto">
    <!-- contenuto esistente -->

    @foreach($curriculumData as $year => $data)
    @if($data['total'] > 0)
        <div class="bg-white rounded-lg shadow-md mb-4">
            <div class="px-6 py-4 border-b border-gray-200 bg-blue-600 text-white">
                <h3 class="mb-0">Anno {{ $year }} - {{ $data['total'] }} tornei - Livello: {{ $data['level'] }}</h3>
            </div>
            <div class="p-6">
                <table class="min-w-full divide-y divide-gray-200 table-sm">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Torneo</th>
                            <th>Circolo</th>
                            <th>Ruolo</th>
                        </tr>
                    </thead>
                    <tbody>

                        @foreach($data['assignments'] as $assignment)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($assignment->start_date)->format('d/m/Y') }}</td>
                                <td><strong>{{ $assignment->name }}</strong></td>
                                <td>{{ $assignment->club_name ?? 'N/A' }}</td>
                                <td>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $assignment->role == 'Direttore di Torneo' ? 'badge-success' : 'badge-info' }}">
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
@endsection

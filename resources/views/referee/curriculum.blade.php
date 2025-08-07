{{-- USA IL LAYOUT DINAMICO --}}
@extends($layout ?? 'layouts.referee')

@section('content')
<div class="container">
    <!-- contenuto esistente -->

@foreach($curriculumData as $year => $data)
    @if($data['count'] > 0)
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">Anno {{ $year }} - {{ $data['count'] }} tornei - Livello: {{ $data['level'] }}</h3>
            </div>
            <div class="card-body">
                <table class="table table-sm">
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
                                <td>{{ \Carbon\Carbon::parse($assignment->tournament->start_date)->format('d/m') }}</td>
                                <td><strong>{{ $assignment->tournament->name }}</strong></td>
                                <td>{{ $assignment->tournament->club->name ?? 'N/A' }}</td>
                                <td>
                                    <span class="badge {{ $assignment->role == 'Direttore di Torneo' ? 'badge-success' : 'badge-info' }}">
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

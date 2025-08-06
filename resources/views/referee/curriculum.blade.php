@extends('layouts.referee')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Curriculum {{ $referee->name }}</h1>
        <div>
            @if(request()->get('print'))
                <script>window.print();</script>
            @endif
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i> Stampa
            </button>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Codice:</strong> {{ $referee->referee_code }}</p>
                    <p><strong>Email:</strong> {{ $referee->email }}</p>
                </div>
                <div class="col-md-6">
                    <p><strong>Telefono:</strong> {{ $referee->phone ?? 'N/A' }}</p>
                    <p><strong>Zona:</strong> {{ $referee->zone->name ?? 'N/A' }}</p>
                </div>
            </div>
        </div>
    </div>

    @foreach($curriculumData as $year => $data)
        @if($data['count'] > 0)
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">
                        Anno {{ $year }}
                        <span class="badge badge-light">{{ $data['count'] }} tornei</span>
                        <span class="badge badge-warning">Livello: {{ $data['level'] }}</span>
                    </h3>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th width="15%">Data</th>
                                <th width="35%">Torneo</th>
                                <th width="30%">Circolo</th>
                                <th width="20%">Ruolo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data['assignments']->sortByDesc('start_date') as $assignment)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($assignment->start_date)->format('d/m') }} -
                                        {{ \Carbon\Carbon::parse($assignment->end_date)->format('d/m') }}</td>
                                    <td><strong>{{ $assignment->name }}</strong></td>
                                    <td>{{ $assignment->club_name }}</td>
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

<style media="print">
    .btn { display: none !important; }
    .card { page-break-inside: avoid; }
    body { font-size: 12pt; }
</style>
@endsection

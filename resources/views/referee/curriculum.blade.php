@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Curriculum {{ $referee->name }}</h1>
        <button onclick="window.print()" class="btn btn-primary">🖨️ Stampa</button>
    </div>

    @foreach($curriculumData as $year => $data)
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h3 class="mb-0">Anno {{ $year }} - Livello: {{ $data['level'] }}</h3>
        </div>
        <div class="card-body">
            <table class="table">
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
                        <td>{{ Carbon\Carbon::parse($assignment->start_date)->format('d/m/Y') }}</td>
                        <td>{{ $assignment->name }}</td>
                        <td>{{ $assignment->club_name ?? 'N/A' }}</td>
                        <td><strong>{{ $assignment->role }}</strong></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endforeach
</div>

<style media="print">
    .btn { display: none; }
    .card { page-break-inside: avoid; }
</style>
@endsection

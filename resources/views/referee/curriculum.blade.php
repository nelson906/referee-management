@extends('layouts.app')

@section('content')
<div class="container mx-auto">
    <div class="flex justify-between items-center mb-4">
        <h1>Curriculum {{ $referee->name }}</h1>
        <button onclick="window.print()" class="px-4 py-2 rounded-lg font-medium transition-colors duration-200 bg-blue-600 hover:bg-blue-700 text-white">🖨️ Stampa</button>
    </div>

    @foreach($curriculumData as $year => $data)
    <div class="bg-white rounded-lg shadow-md mb-4">
        <div class="px-6 py-4 border-b border-gray-200 bg-blue-600 text-white">
            <h3 class="mb-0">Anno {{ $year }} - Livello: {{ $data['level'] }}</h3>
        </div>
        <div class="p-6">
            <table class="min-w-full divide-y divide-gray-200">
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

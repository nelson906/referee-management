@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Curricula Arbitri</h3>
                </div>
                <div class="card-body">
                    <table class="table table-bordered table-striped">
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
                            @foreach($referees as $referee)
                                @php
                                    // Conta tornei totali
                                    $totalTournaments = 0;
                                    $lastTournament = null;
                                    $lastDate = null;

                                    for ($year = date('Y'); $year >= 2015; $year--) {
                                        if (Schema::hasTable("tournaments_{$year}")) {
                                            $count = DB::table('assignments as a')
                                                ->join("tournaments_{$year} as t", 'a.tournament_id', '=', 't.id')
                                                ->where('a.user_id', $referee->id)
                                                ->count();

                                            $totalTournaments += $count;

                                            if (!$lastTournament && $count > 0) {
                                                $last = DB::table('assignments as a')
                                                    ->join("tournaments_{$year} as t", 'a.tournament_id', '=', 't.id')
                                                    ->where('a.user_id', $referee->id)
                                                    ->orderBy('t.start_date', 'desc')
                                                    ->first();

                                                if ($last) {
                                                    $lastTournament = $last->name;
                                                    $lastDate = $last->start_date;
                                                }
                                            }
                                        }
                                    }
                                @endphp
                                <tr>
                                    <td>{{ $referee->name }}</td>
                                    <td>{{ $referee->referee_code }}</td>
                                    <td>
                                        <span class="badge badge-info">{{ $referee->level }}</span>
                                    </td>
                                    <td>{{ $referee->zone->name ?? 'N/A' }}</td>
                                    <td>
                                        <span class="badge badge-primary">{{ $totalTournaments }}</span>
                                    </td>
                                    <td>
                                        @if($lastTournament)
                                            {{ $lastTournament }}<br>
                                            <small class="text-muted">{{ \Carbon\Carbon::parse($lastDate)->format('d/m/Y') }}</small>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.referee.curriculum', $referee->id) }}"
                                           class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i> Visualizza
                                        </a>
                                        <a href="{{ route('admin.referee.curriculum', $referee->id) }}?print=1"
                                           class="btn btn-sm btn-secondary" target="_blank">
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

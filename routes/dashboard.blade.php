@extends('layouts.admin')

@section('title', 'Dashboard Statistiche')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <h1>📊 Dashboard Statistiche</h1>
                <a href="{{ route('admin.statistics.export') }}" class="btn btn-success">
                    <i class="fas fa-download"></i> Esporta CSV
                </a>
            </div>
        </div>
    </div>

    <!-- Cards con totali -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5>Disponibilità Totali</h5>
                    <h2>{{ $stats['disponibilita']->sum('totale_dichiarazioni') }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5>Assegnazioni Totali</h5>
                    <h2>{{ $stats['assegnazioni']->sum('totale_assegnazioni') }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5>Arbitri Attivi</h5>
                    <h2>{{ $stats['presenze_effettive']->count() }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5>Ruoli Diversi</h5>
                    <h2>{{ $stats['durata_per_ruolo']->count() }}</h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabelle statistiche -->
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>📋 Disponibilità per Zona</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Zona</th>
                                    <th>Totale</th>
                                    <th>Disponibili</th>
                                    <th>%</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($stats['disponibilita'] as $row)
                                <tr>
                                    <td>{{ $row->codice_zona }}</td>
                                    <td>{{ $row->totale_dichiarazioni }}</td>
                                    <td>{{ $row->disponibili }}</td>
                                    <td>
                                        <span class="badge badge-{{ $row->percentuale_disponibilita > 70 ? 'success' : ($row->percentuale_disponibilita > 50 ? 'warning' : 'danger') }}">
                                            {{ $row->percentuale_disponibilita }}%
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>✅ Assegnazioni per Zona</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Zona</th>
                                    <th>Totale</th>
                                    <th>Confermate</th>
                                    <th>%</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($stats['assegnazioni'] as $row)
                                <tr>
                                    <td>{{ $row->codice_zona }}</td>
                                    <td>{{ $row->totale_assegnazioni }}</td>
                                    <td>{{ $row->confermate }}</td>
                                    <td>
                                        <span class="badge badge-{{ $row->tasso_conferma > 80 ? 'success' : ($row->tasso_conferma > 60 ? 'warning' : 'danger') }}">
                                            {{ $row->tasso_conferma }}%
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

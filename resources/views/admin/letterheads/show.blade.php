@extends('layouts.admin')

@section('title', $letterhead->title)

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">{{ $letterhead->title }}</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a href="{{ route('admin.letterheads.index') }}">Letterheads</a>
                            </li>
                            <li class="breadcrumb-item active">{{ Str::limit($letterhead->title, 50) }}</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    @can('update', $letterhead)
                        <a href="{{ route('admin.letterheads.edit', $letterhead) }}" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Modifica
                        </a>
                    @endcan
                    <a href="{{ route('admin.letterheads.preview', $letterhead) }}"
                       class="btn btn-outline-secondary"
                       target="_blank">
                        <i class="fas fa-eye"></i> Anteprima
                    </a>
                    <a href="{{ route('admin.letterheads.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Indietro
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Informazioni Principali -->
        <div class="col-lg-8">
            <!-- Dettagli Base -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Informazioni Generali</h5>
                    <div>
                        @if($letterhead->is_active)
                            <span class="badge bg-success">Attiva</span>
                        @else
                            <span class="badge bg-danger">Inattiva</span>
                        @endif

                        @if($letterhead->is_default)
                            <span class="badge bg-warning text-dark">
                                <i class="fas fa-star"></i> Predefinita
                            </span>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-3">Titolo:</dt>
                        <dd class="col-sm-9">{{ $letterhead->title }}</dd>

                        @if($letterhead->description)
                            <dt class="col-sm-3">Descrizione:</dt>
                            <dd class="col-sm-9">{{ $letterhead->description }}</dd>
                        @endif

                        <dt class="col-sm-3">Zona:</dt>
                        <dd class="col-sm-9">
                            @if($letterhead->zone)
                                <span class="badge bg-info">{{ $letterhead->zone->name }}</span>
                            @else
                                <span class="badge bg-secondary">Globale</span>
                            @endif
                        </dd>

                        @if($letterhead->header_text)
                            <dt class="col-sm-3">Testo Header:</dt>
                            <dd class="col-sm-9">
                                <div class="border rounded p-2 bg-light">
                                    {!! nl2br(e($letterhead->header_text)) !!}
                                </div>
                            </dd>
                        @endif

                        @if($letterhead->footer_text)
                            <dt class="col-sm-3">Testo Footer:</dt>
                            <dd class="col-sm-9">
                                <div class="border rounded p-2 bg-light">
                                    {!! nl2br(e($letterhead->footer_text)) !!}
                                </div>
                            </dd>
                        @endif

                        <dt class="col-sm-3">Creata:</dt>
                        <dd class="col-sm-9">{{ $letterhead->created_at->format('d/m/Y H:i') }}</dd>

                        <dt class="col-sm-3">Ultima modifica:</dt>
                        <dd class="col-sm-9">
                            {{ $letterhead->updated_at->format('d/m/Y H:i') }}
                            @if($letterhead->updatedBy)
                                da <strong>{{ $letterhead->updatedBy->name }}</strong>
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>

            <!-- Informazioni di Contatto -->
            @if($letterhead->contact_info && array_filter($letterhead->contact_info))
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Informazioni di Contatto</h5>
                    </div>
                    <div class="card-body">
                        <dl class="row">
                            @if(!empty($letterhead->contact_info['address']))
                                <dt class="col-sm-3">Indirizzo:</dt>
                                <dd class="col-sm-9">{{ $letterhead->contact_info['address'] }}</dd>
                            @endif

                            @if(!empty($letterhead->contact_info['phone']))
                                <dt class="col-sm-3">Telefono:</dt>
                                <dd class="col-sm-9">
                                    <a href="tel:{{ $letterhead->contact_info['phone'] }}">
                                        {{ $letterhead->contact_info['phone'] }}
                                    </a>
                                </dd>
                            @endif

                            @if(!empty($letterhead->contact_info['email']))
                                <dt class="col-sm-3">Email:</dt>
                                <dd class="col-sm-9">
                                    <a href="mailto:{{ $letterhead->contact_info['email'] }}">
                                        {{ $letterhead->contact_info['email'] }}
                                    </a>
                                </dd>
                            @endif

                            @if(!empty($letterhead->contact_info['website']))
                                <dt class="col-sm-3">Sito Web:</dt>
                                <dd class="col-sm-9">
                                    <a href="{{ $letterhead->contact_info['website'] }}" target="_blank">
                                        {{ $letterhead->contact_info['website'] }}
                                        <i class="fas fa-external-link-alt fa-sm"></i>
                                    </a>
                                </dd>
                            @endif
                        </dl>

                        <!-- Contact Info Formatted -->
                        @if($letterhead->formatted_contact_info)
                            <div class="mt-3">
                                <small class="text-muted"><strong>Formato finale:</strong></small>
                                <div class="border rounded p-2 bg-light mt-1">
                                    <small>{{ $letterhead->formatted_contact_info }}</small>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Impostazioni Layout -->
            @if($letterhead->settings && array_filter($letterhead->settings))
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Impostazioni Layout</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Margini -->
                            @if(!empty($letterhead->settings['margins']))
                                <div class="col-md-6">
                                    <h6>Margini (mm)</h6>
                                    <ul class="list-unstyled">
                                        <li><strong>Alto:</strong> {{ $letterhead->settings['margins']['top'] ?? 20 }}mm</li>
                                        <li><strong>Basso:</strong> {{ $letterhead->settings['margins']['bottom'] ?? 20 }}mm</li>
                                        <li><strong>Sinistra:</strong> {{ $letterhead->settings['margins']['left'] ?? 25 }}mm</li>
                                        <li><strong>Destra:</strong> {{ $letterhead->settings['margins']['right'] ?? 25 }}mm</li>
                                    </ul>
                                </div>
                            @endif

                            <!-- Font -->
                            @if(!empty($letterhead->settings['font']))
                                <div class="col-md-6">
                                    <h6>Font</h6>
                                    <ul class="list-unstyled">
                                        <li><strong>Famiglia:</strong> {{ $letterhead->settings['font']['family'] ?? 'Arial' }}</li>
                                        <li><strong>Dimensione:</strong> {{ $letterhead->settings['font']['size'] ?? 11 }}pt</li>
                                        <li>
                                            <strong>Colore:</strong>
                                            <span class="d-inline-block rounded"
                                                  style="width: 20px; height: 20px; background-color: {{ $letterhead->settings['font']['color'] ?? '#000000' }}; vertical-align: middle;"></span>
                                            {{ $letterhead->settings['font']['color'] ?? '#000000' }}
                                        </li>
                                    </ul>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Logo -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Logo</h5>
                </div>
                <div class="card-body text-center">
                    @if($letterhead->logo_path)
                        <div class="mb-3">
                            <img src="{{ $letterhead->logo_url }}"
                                 alt="Logo {{ $letterhead->title }}"
                                 class="img-fluid border rounded"
                                 style="max-height: 200px;">
                        </div>
                        <div class="text-muted">
                            <small>{{ basename($letterhead->logo_path) }}</small>
                        </div>
                        @can('update', $letterhead)
                            <form method="POST"
                                  action="{{ route('admin.letterheads.remove-logo', $letterhead) }}"
                                  class="mt-2"
                                  onsubmit="return confirm('Sei sicuro di voler rimuovere il logo?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-trash"></i> Rimuovi Logo
                                </button>
                            </form>
                        @endcan
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-image fa-3x text-muted mb-2"></i>
                            <p class="text-muted">Nessun logo caricato</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Azioni Rapide -->
            @can('update', $letterhead)
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Azioni Rapide</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <!-- Toggle Status -->
                            <form method="POST" action="{{ route('admin.letterheads.toggle-active', $letterhead) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-outline-secondary w-100">
                                    @if($letterhead->is_active)
                                        <i class="fas fa-toggle-off"></i> Disattiva
                                    @else
                                        <i class="fas fa-toggle-on"></i> Attiva
                                    @endif
                                </button>
                            </form>

                            <!-- Set Default -->
                            @if(!$letterhead->is_default && $letterhead->is_active)
                                <form method="POST" action="{{ route('admin.letterheads.set-default', $letterhead) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-outline-warning w-100">
                                        <i class="fas fa-star"></i> Imposta come Predefinita
                                    </button>
                                </form>
                            @endif

                            <!-- Duplicate -->
                            @can('create', App\Models\Letterhead::class)
                                <form method="POST" action="{{ route('admin.letterheads.duplicate', $letterhead) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-info w-100">
                                        <i class="fas fa-copy"></i> Duplica
                                    </button>
                                </form>
                            @endcan
                        </div>
                    </div>
                </div>
            @endcan

            <!-- Statistiche d'Uso -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Statistiche</h5>
                </div>
                <div class="card-body">
                    <div class="text-center">
                        <div class="row">
                            <div class="col-6">
                                <div class="border-end">
                                    <div class="h4 mb-0 text-primary">{{ $letterhead->is_default ? '1' : '0' }}</div>
                                    <small class="text-muted">Predefinita</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="h4 mb-0 text-success">{{ $letterhead->is_active ? '1' : '0' }}</div>
                                <small class="text-muted">Attiva</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    @can('delete', $letterhead)
        @if(!$letterhead->is_default)
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card border-danger">
                        <div class="card-header bg-danger text-white">
                            <h5 class="card-title mb-0">Zona Pericolosa</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">
                                Una volta eliminata, questa letterhead non potrà essere recuperata.
                                Tutti i documenti che la utilizzano dovranno essere aggiornati manualmente.
                            </p>
                            <form method="POST"
                                  action="{{ route('admin.letterheads.destroy', $letterhead) }}"
                                  onsubmit="return confirm('ATTENZIONE: Questa azione è irreversibile. Sei sicuro di voler eliminare definitivamente questa letterhead?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-trash"></i> Elimina Letterhead
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endcan
</div>
@endsection

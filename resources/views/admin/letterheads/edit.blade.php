@extends('layouts.admin')

@section('title', 'Modifica Letterhead')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Modifica Letterhead</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a href="{{ route('admin.letterheads.index') }}">Letterheads</a>
                            </li>
                            <li class="breadcrumb-item">
                                <a href="{{ route('admin.letterheads.show', $letterhead) }}">{{ Str::limit($letterhead->title, 30) }}</a>
                            </li>
                            <li class="breadcrumb-item active">Modifica</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="{{ route('admin.letterheads.preview', $letterhead) }}"
                       class="btn btn-outline-info"
                       target="_blank">
                        <i class="fas fa-eye"></i> Anteprima
                    </a>
                    <a href="{{ route('admin.letterheads.show', $letterhead) }}" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Indietro
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Form -->
    <form method="POST" action="{{ route('admin.letterheads.update', $letterhead) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="row">
            <!-- Dati Principali -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Informazioni Generali</h5>
                    </div>
                    <div class="card-body">
                        <!-- Titolo -->
                        <div class="mb-3">
                            <label for="title" class="form-label">Titolo <span class="text-danger">*</span></label>
                            <input type="text"
                                   class="form-control @error('title') is-invalid @enderror"
                                   id="title"
                                   name="title"
                                   value="{{ old('title', $letterhead->title) }}"
                                   required>
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Descrizione -->
                        <div class="mb-3">
                            <label for="description" class="form-label">Descrizione</label>
                            <textarea class="form-control @error('description') is-invalid @enderror"
                                      id="description"
                                      name="description"
                                      rows="3">{{ old('description', $letterhead->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Zona -->
                        <div class="mb-3">
                            <label for="zone_id" class="form-label">Zona</label>
                            <select class="form-select @error('zone_id') is-invalid @enderror"
                                    id="zone_id"
                                    name="zone_id">
                                <option value="">Globale</option>
                                @foreach($zones as $zone)
                                    <option value="{{ $zone->id }}"
                                            {{ old('zone_id', $letterhead->zone_id) == $zone->id ? 'selected' : '' }}>
                                        {{ $zone->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('zone_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">
                                Lascia vuoto per una letterhead globale disponibile per tutte le zone
                            </div>
                        </div>

                        <!-- Header Text -->
                        <div class="mb-3">
                            <label for="header_text" class="form-label">Testo Header</label>
                            <textarea class="form-control @error('header_text') is-invalid @enderror"
                                      id="header_text"
                                      name="header_text"
                                      rows="3">{{ old('header_text', $letterhead->header_text) }}</textarea>
                            @error('header_text')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Footer Text -->
                        <div class="mb-3">
                            <label for="footer_text" class="form-label">Testo Footer</label>
                            <textarea class="form-control @error('footer_text') is-invalid @enderror"
                                      id="footer_text"
                                      name="footer_text"
                                      rows="3">{{ old('footer_text', $letterhead->footer_text) }}</textarea>
                            @error('footer_text')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Informazioni di Contatto -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Informazioni di Contatto</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="contact_address" class="form-label">Indirizzo</label>
                                    <input type="text"
                                           class="form-control @error('contact_info.address') is-invalid @enderror"
                                           id="contact_address"
                                           name="contact_info[address]"
                                           value="{{ old('contact_info.address', $letterhead->contact_info['address'] ?? '') }}">
                                    @error('contact_info.address')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="contact_phone" class="form-label">Telefono</label>
                                    <input type="text"
                                           class="form-control @error('contact_info.phone') is-invalid @enderror"
                                           id="contact_phone"
                                           name="contact_info[phone]"
                                           value="{{ old('contact_info.phone', $letterhead->contact_info['phone'] ?? '') }}">
                                    @error('contact_info.phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="contact_email" class="form-label">Email</label>
                                    <input type="email"
                                           class="form-control @error('contact_info.email') is-invalid @enderror"
                                           id="contact_email"
                                           name="contact_info[email]"
                                           value="{{ old('contact_info.email', $letterhead->contact_info['email'] ?? '') }}">
                                    @error('contact_info.email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="contact_website" class="form-label">Sito Web</label>
                                    <input type="url"
                                           class="form-control @error('contact_info.website') is-invalid @enderror"
                                           id="contact_website"
                                           name="contact_info[website]"
                                           value="{{ old('contact_info.website', $letterhead->contact_info['website'] ?? '') }}">
                                    @error('contact_info.website')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Logo -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Logo</h5>
                    </div>
                    <div class="card-body">
                        <!-- Logo attuale -->
                        @if($letterhead->logo_path)
                            <div class="mb-3">
                                <label class="form-label">Logo attuale:</label>
                                <div class="text-center">
                                    <img src="{{ $letterhead->logo_url }}"
                                         alt="Logo attuale"
                                         class="img-thumbnail"
                                         style="max-width: 200px;">
                                </div>
                                <div class="text-center mt-2">
                                    <small class="text-muted">{{ basename($letterhead->logo_path) }}</small>
                                </div>
                            </div>
                        @endif

                        <div class="mb-3">
                            <label for="logo" class="form-label">
                                {{ $letterhead->logo_path ? 'Sostituisci Logo' : 'Upload Logo' }}
                            </label>
                            <input type="file"
                                   class="form-control @error('logo') is-invalid @enderror"
                                   id="logo"
                                   name="logo"
                                   accept="image/*">
                            @error('logo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">
                                Formati supportati: JPG, PNG, GIF, SVG. Max 2MB.
                                {{ $letterhead->logo_path ? 'Lascia vuoto per mantenere il logo attuale.' : '' }}
                            </div>
                        </div>

                        <!-- Anteprima nuovo logo -->
                        <div id="logo-preview" style="display: none;">
                            <label class="form-label">Anteprima nuovo logo:</label>
                            <div class="text-center">
                                <img id="preview-image"
                                     src=""
                                     alt="Anteprima"
                                     class="img-thumbnail"
                                     style="max-width: 200px;">
                            </div>
                        </div>

                        <!-- Rimuovi logo esistente -->
                        @if($letterhead->logo_path)
                            <div class="text-center mt-3">
                                <button type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        onclick="removeLogo()">
                                    <i class="fas fa-trash"></i> Rimuovi Logo Attuale
                                </button>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Impostazioni -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Stato</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-check mb-3">
                            <input class="form-check-input"
                                   type="checkbox"
                                   id="is_active"
                                   name="is_active"
                                   value="1"
                                   {{ old('is_active', $letterhead->is_active) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">
                                Attiva
                            </label>
                        </div>

                        <div class="form-check">
                            <input class="form-check-input"
                                   type="checkbox"
                                   id="is_default"
                                   name="is_default"
                                   value="1"
                                   {{ old('is_default', $letterhead->is_default) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_default">
                                Imposta come predefinita
                            </label>
                            <div class="form-text">
                                Se selezionato, diventerà la letterhead predefinita per la zona selezionata
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Margini e Font -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Impostazioni Layout</h5>
                    </div>
                    <div class="card-body">
                        <!-- Margini -->
                        <h6>Margini (mm)</h6>
                        <div class="row">
                            <div class="col-6">
                                <div class="mb-2">
                                    <label for="margin_top" class="form-label">Alto</label>
                                    <input type="number"
                                           class="form-control form-control-sm"
                                           id="margin_top"
                                           name="settings[margins][top]"
                                           value="{{ old('settings.margins.top', $letterhead->settings['margins']['top'] ?? 20) }}"
                                           min="0" max="100">
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-2">
                                    <label for="margin_bottom" class="form-label">Basso</label>
                                    <input type="number"
                                           class="form-control form-control-sm"
                                           id="margin_bottom"
                                           name="settings[margins][bottom]"
                                           value="{{ old('settings.margins.bottom', $letterhead->settings['margins']['bottom'] ?? 20) }}"
                                           min="0" max="100">
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3">
                                    <label for="margin_left" class="form-label">Sinistra</label>
                                    <input type="number"
                                           class="form-control form-control-sm"
                                           id="margin_left"
                                           name="settings[margins][left]"
                                           value="{{ old('settings.margins.left', $letterhead->settings['margins']['left'] ?? 25) }}"
                                           min="0" max="100">
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3">
                                    <label for="margin_right" class="form-label">Destra</label>
                                    <input type="number"
                                           class="form-control form-control-sm"
                                           id="margin_right"
                                           name="settings[margins][right]"
                                           value="{{ old('settings.margins.right', $letterhead->settings['margins']['right'] ?? 25) }}"
                                           min="0" max="100">
                                </div>
                            </div>
                        </div>

                        <!-- Font -->
                        <h6>Font</h6>
                        <div class="mb-2">
                            <label for="font_family" class="form-label">Famiglia</label>
                            <select class="form-select form-select-sm"
                                    id="font_family"
                                    name="settings[font][family]">
                                <option value="Arial" {{ old('settings.font.family', $letterhead->settings['font']['family'] ?? 'Arial') === 'Arial' ? 'selected' : '' }}>Arial</option>
                                <option value="Times New Roman" {{ old('settings.font.family', $letterhead->settings['font']['family'] ?? '') === 'Times New Roman' ? 'selected' : '' }}>Times New Roman</option>
                                <option value="Helvetica" {{ old('settings.font.family', $letterhead->settings['font']['family'] ?? '') === 'Helvetica' ? 'selected' : '' }}>Helvetica</option>
                                <option value="Courier" {{ old('settings.font.family', $letterhead->settings['font']['family'] ?? '') === 'Courier' ? 'selected' : '' }}>Courier</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <div class="mb-2">
                                    <label for="font_size" class="form-label">Dimensione</label>
                                    <input type="number"
                                           class="form-control form-control-sm"
                                           id="font_size"
                                           name="settings[font][size]"
                                           value="{{ old('settings.font.size', $letterhead->settings['font']['size'] ?? 11) }}"
                                           min="8" max="24">
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-2">
                                    <label for="font_color" class="form-label">Colore</label>
                                    <input type="color"
                                           class="form-control form-control-color"
                                           id="font_color"
                                           name="settings[font][color]"
                                           value="{{ old('settings.font.color', $letterhead->settings['font']['color'] ?? '#000000') }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Azioni -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="d-flex justify-content-between">
                    <a href="{{ route('admin.letterheads.show', $letterhead) }}" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Annulla
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Aggiorna Letterhead
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Modal per rimozione logo -->
<div class="modal fade" id="removeLogoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Rimuovi Logo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Sei sicuro di voler rimuovere il logo attuale?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                <form method="POST" action="{{ route('admin.letterheads.remove-logo', $letterhead) }}" style="display: inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Rimuovi</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Anteprima logo
    const logoInput = document.getElementById('logo');
    const logoPreview = document.getElementById('logo-preview');
    const previewImage = document.getElementById('preview-image');

    logoInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImage.src = e.target.result;
                logoPreview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else {
            logoPreview.style.display = 'none';
        }
    });
});

// Funzione per mostrare modal rimozione logo
function removeLogo() {
    new bootstrap.Modal(document.getElementById('removeLogoModal')).show();
}
</script>
@endpush

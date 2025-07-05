{{-- Form Errors --}}
@if ($errors->any())
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
        <p class="font-bold mb-2">Errori nel form:</p>
        <ul class="list-disc list-inside">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    {{-- Informazioni Base --}}
    <div class="col-span-2">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Informazioni Base</h3>
    </div>

    {{-- Nome --}}
    <div>
        <label for="name" class="block text-sm font-medium text-gray-700">
            Nome Categoria <span class="text-red-500">*</span>
        </label>
        <input type="text"
               name="name"
               id="name"
               value="{{ old('name', $tournamentCategory->name ?? '') }}"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('name') border-red-500 @enderror"
               required>
        @error('name')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Codice --}}
    <div>
        <label for="code" class="block text-sm font-medium text-gray-700">
            Codice <span class="text-red-500">*</span>
        </label>
        <input type="text"
               name="code"
               id="code"
               value="{{ old('code', $tournamentCategory->code ?? '') }}"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm uppercase @error('code') border-red-500 @enderror"
               pattern="[A-Za-z0-9_-]+"
               title="Solo lettere, numeri, trattini e underscore"
               required>
        <p class="mt-1 text-xs text-gray-500">Es: T18, GN-72, CI</p>
        @error('code')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Descrizione --}}
    <div class="col-span-2">
        <label for="description" class="block text-sm font-medium text-gray-700">
            Descrizione
        </label>
        <textarea name="description"
                  id="description"
                  rows="3"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('description') border-red-500 @enderror">{{ old('description', $tournamentCategory->description ?? '') }}</textarea>
        @error('description')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Configurazione Livello --}}
    <div class="col-span-2 mt-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Configurazione Livello</h3>
    </div>

    {{-- Livello Categoria --}}
    <div>
        <label for="level" class="block text-sm font-medium text-gray-700">
            Livello Categoria <span class="text-red-500">*</span>
        </label>
        <select name="level"
                id="level"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('level') border-red-500 @enderror"
                required>
            @foreach($categoryLevels as $value => $label)
                <option value="{{ $value }}"
                    {{ old('level', $tournamentCategory->level ?? 'zonale') == $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('level')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Categoria Nazionale --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-3">
            Visibilità
        </label>
        <div class="space-y-2">
            <label class="inline-flex items-center">
                <input type="checkbox"
                       name="is_national"
                       id="is_national"
                       value="1"
                       {{ old('is_national', $tournamentCategory->is_national ?? false) ? 'checked' : '' }}
                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <span class="ml-2 text-sm text-gray-700">
                    Categoria Nazionale (visibile a tutte le zone)
                </span>
            </label>
        </div>
    </div>

    {{-- Zone Visibilità (solo se non nazionale) --}}
    <div class="col-span-2" id="zones-visibility-container" style="{{ old('is_national', $tournamentCategory->is_national ?? false) ? 'display:none' : '' }}">
        <label class="block text-sm font-medium text-gray-700 mb-2">
            Zone di Visibilità
        </label>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-2 p-4 bg-gray-50 rounded-md">
            @foreach($zones as $zone)
                <label class="inline-flex items-center">
                    <input type="checkbox"
                           name="visibility_zones[]"
                           value="{{ $zone->id }}"
                           {{ in_array($zone->id, old('visibility_zones', $tournamentCategory->settings['visibility_zones'] ?? [])) ? 'checked' : '' }}
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <span class="ml-2 text-sm text-gray-700">{{ $zone->name }}</span>
                </label>
            @endforeach
        </div>
        <p class="mt-1 text-xs text-gray-500">Seleziona le zone che possono vedere questa categoria</p>
    </div>

    {{-- Configurazione Arbitri --}}
    <div class="col-span-2 mt-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Configurazione Arbitri</h3>
    </div>

    {{-- Livello Arbitro Richiesto --}}
    <div>
        <label for="required_referee_level" class="block text-sm font-medium text-gray-700">
            Livello Arbitro Minimo <span class="text-red-500">*</span>
        </label>
        <select name="required_referee_level"
                id="required_referee_level"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('required_referee_level') border-red-500 @enderror"
                required>
            @foreach($refereeLevels as $value => $label)
                <option value="{{ $value }}"
                    {{ old('required_referee_level', $tournamentCategory->required_referee_level ?? 'aspirante') == $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('required_referee_level')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Numero Arbitri --}}
    <div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="min_referees" class="block text-sm font-medium text-gray-700">
                    Numero Minimo <span class="text-red-500">*</span>
                </label>
                <input type="number"
                       name="min_referees"
                       id="min_referees"
                       min="1"
                       max="10"
                       value="{{ old('min_referees', $tournamentCategory->min_referees ?? 1) }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('min_referees') border-red-500 @enderror"
                       required>
                @error('min_referees')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="max_referees" class="block text-sm font-medium text-gray-700">
                    Numero Massimo <span class="text-red-500">*</span>
                </label>
                <input type="number"
                       name="max_referees"
                       id="max_referees"
                       min="1"
                       max="10"
                       value="{{ old('max_referees', $tournamentCategory->max_referees ?? 1) }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('max_referees') border-red-500 @enderror"
                       required>
                @error('max_referees')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    {{-- Requisiti Speciali --}}
    <div class="col-span-2">
        <label for="special_requirements" class="block text-sm font-medium text-gray-700">
            Requisiti Speciali
        </label>
        <textarea name="special_requirements"
                  id="special_requirements"
                  rows="3"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('special_requirements') border-red-500 @enderror"
                  placeholder="Es: Richiesta esperienza in tornei maggiori, certificazione specifica, ecc.">{{ old('special_requirements', $tournamentCategory->special_requirements ?? '') }}</textarea>
        @error('special_requirements')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Impostazioni Aggiuntive --}}
    <div class="col-span-2 mt-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Impostazioni Aggiuntive</h3>
    </div>

    {{-- Ordine Visualizzazione --}}
    <div>
        <label for="sort_order" class="block text-sm font-medium text-gray-700">
            Ordine Visualizzazione
        </label>
        <input type="number"
               name="sort_order"
               id="sort_order"
               min="0"
               value="{{ old('sort_order', $tournamentCategory->sort_order ?? 0) }}"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('sort_order') border-red-500 @enderror">
        <p class="mt-1 text-xs text-gray-500">Ordine crescente (0 = primo)</p>
        @error('sort_order')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Stato Attivo --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-3">
            Stato
        </label>
        <div class="space-y-2">
            <label class="inline-flex items-center">
                <input type="checkbox"
                       name="is_active"
                       id="is_active"
                       value="1"
                       {{ old('is_active', $tournamentCategory->is_active ?? true) ? 'checked' : '' }}
                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <span class="ml-2 text-sm text-gray-700">
                    Categoria Attiva
                </span>
            </label>
            <p class="text-xs text-gray-500">Le categorie non attive non sono disponibili per i nuovi tornei</p>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Toggle zones visibility based on national checkbox
document.getElementById('is_national').addEventListener('change', function() {
    const zonesContainer = document.getElementById('zones-visibility-container');
    if (this.checked) {
        zonesContainer.style.display = 'none';
        // Uncheck all zones
        document.querySelectorAll('input[name="visibility_zones[]"]').forEach(checkbox => {
            checkbox.checked = false;
        });
    } else {
        zonesContainer.style.display = 'block';
    }
});

// Validate min/max referees
document.getElementById('min_referees').addEventListener('change', function() {
    const maxInput = document.getElementById('max_referees');
    if (parseInt(maxInput.value) < parseInt(this.value)) {
        maxInput.value = this.value;
    }
    maxInput.min = this.value;
});

// Auto uppercase for code
document.getElementById('code').addEventListener('input', function() {
    this.value = this.value.toUpperCase();
});
</script>
@endpush

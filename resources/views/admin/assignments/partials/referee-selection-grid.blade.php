{{-- Referee Selection Grid Partial - admin/assignments/partials/referee-selection-grid.blade.php --}}
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    @foreach($referees as $referee)
        @php
            $checkboxName = "referees[{$type}_{$referee->id}]";
            $hasConflicts = $referee->has_conflicts ?? false;

            // Determine styling based on type
            $cardClass = match($type) {
                'available' => 'border-green-200 hover:border-green-300 bg-green-50',
                'possible' => 'border-yellow-200 hover:border-yellow-300 bg-yellow-50',
                'national' => 'border-blue-200 hover:border-blue-300 bg-blue-50',
                default => 'border-gray-200 hover:border-gray-300 bg-white'
            };

            $badgeClass = match($type) {
                'available' => 'bg-green-100 text-green-800',
                'possible' => 'bg-yellow-100 text-yellow-800',
                'national' => 'bg-blue-100 text-blue-800',
                default => 'bg-gray-100 text-gray-800'
            };
        @endphp

        <div class="border {{ $cardClass }} rounded-lg p-4 transition-all duration-200 hover:shadow-md {{ $hasConflicts ? 'ring-2 ring-red-300' : '' }}">
            <div class="flex items-start space-x-3">
                {{-- Checkbox --}}
                <div class="flex-shrink-0 pt-1">
                    <input type="checkbox"
                           name="{{ $checkboxName }}[selected]"
                           value="1"
                           id="referee_{{ $type }}_{{ $referee->id }}"
                           class="h-5 w-5 text-green-600 focus:ring-green-500 border-gray-300 rounded">
                </div>

                {{-- Referee Info --}}
                <div class="flex-grow min-w-0">
                    <label for="referee_{{ $type }}_{{ $referee->id }}" class="block cursor-pointer">
                        <h4 class="font-medium text-gray-900 truncate">{{ $referee->name }}</h4>
                        <div class="mt-1 space-y-1 text-sm text-gray-600">
                            <p><strong>Codice:</strong> {{ $referee->referee->referee_code ?? 'N/A' }}</p>
                            <p><strong>Livello:</strong>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $badgeClass }}">
                                    {{ $referee->referee->level_label ?? 'N/A' }}
                                </span>
                            </p>
                            @if($type !== 'national')
                                <p><strong>Zona:</strong> {{ $referee->zone->name ?? 'N/A' }}</p>
                            @endif
                        </div>
                    </label>

                    {{-- Conflicts Warning --}}
                    @if($hasConflicts)
                        <div class="mt-2 p-2 bg-red-100 border border-red-200 rounded text-xs">
                            <p class="font-medium text-red-800">⚠️ Conflitti:</p>
                            @foreach($referee->conflicts as $conflict)
                                <p class="text-red-700">
                                    • {{ $conflict->tournament->name }}
                                    ({{ $conflict->tournament->start_date->format('d/m') }})
                                </p>
                            @endforeach
                        </div>
                    @endif

                    {{-- Assignment Details (shown when selected) --}}
                    <div class="mt-3 space-y-3 referee-details" style="display: none;">
                        {{-- Hidden inputs for form data --}}
                        <input type="hidden" name="{{ $checkboxName }}[user_id]" value="{{ $referee->id }}">

                        {{-- Role Selection --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Ruolo</label>
                            <select name="{{ $checkboxName }}[role]"
                                    class="w-full rounded-md border-gray-300 text-xs focus:border-green-500 focus:ring-green-500">
                                <option value="Arbitro">Arbitro</option>
                                <option value="Direttore di Torneo">Direttore di Torneo</option>
                                <option value="Osservatore">Osservatore</option>
                            </select>
                        </div>

                        {{-- Notes --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Note</label>
                            <textarea name="{{ $checkboxName }}[notes]"
                                      rows="2"
                                      placeholder="Note opzionali..."
                                      class="w-full rounded-md border-gray-300 text-xs focus:border-green-500 focus:ring-green-500"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

@if($referees->count() === 0)
    <div class="text-center py-8 text-gray-500">
        <p>Nessun arbitro disponibile in questa categoria.</p>
    </div>
@endif

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show/hide referee details when checkbox is toggled
    document.querySelectorAll('input[type="checkbox"][name^="referees["]').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            const detailsDiv = this.closest('.border').querySelector('.referee-details');
            if (this.checked) {
                detailsDiv.style.display = 'block';
                // Focus on role select for quick assignment
                detailsDiv.querySelector('select').focus();
            } else {
                detailsDiv.style.display = 'none';
                // Clear form data
                detailsDiv.querySelectorAll('input, textarea, select').forEach(el => {
                    if (el.tagName === 'SELECT') {
                        el.selectedIndex = 0;
                    } else {
                        el.value = '';
                    }
                });
            }
        });
    });
});
</script>
@endpush

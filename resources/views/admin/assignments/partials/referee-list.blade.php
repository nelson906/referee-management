{{-- Referee List Partial --}}
<div class="grid grid-cols-1 gap-4">
    @foreach($referees as $index => $referee)
        @php
            $checkboxName = "referees[{$type}_{$referee->id}]";
            $hasConflicts = $referee->has_conflicts ?? false;

            // Determine styling based on type
            $cardClass = match($type) {
                'available' => 'border-green-200 hover:border-green-300',
                'possible' => 'border-yellow-200 hover:border-yellow-300',
                'national' => 'border-blue-200 hover:border-blue-300',
                default => 'border-gray-200 hover:border-gray-300'
            };

            $badgeClass = match($type) {
                'available' => 'bg-green-100 text-green-800',
                'possible' => 'bg-yellow-100 text-yellow-800',
                'national' => 'bg-blue-100 text-blue-800',
                default => 'bg-gray-100 text-gray-800'
            };
        @endphp

        <div class="border {{ $cardClass }} rounded-lg p-4 {{ $hasConflicts ? 'bg-red-50' : 'bg-white' }}">
            <div class="flex items-start space-x-4">
                {{-- Checkbox --}}
                <div class="flex-shrink-0 pt-1">
                    <input type="checkbox"
                           name="{{ $checkboxName }}[selected]"
                           value="1"
                           id="referee_{{ $type }}_{{ $referee->id }}"
                           class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded">
                </div>

                {{-- Referee Info --}}
                <div class="flex-grow">
                    <div class="flex items-start justify-between">
                        <div>
                            <label for="referee_{{ $type }}_{{ $referee->id }}" class="block">
                                <h4 class="font-medium text-gray-900 cursor-pointer">{{ $referee->name }}</h4>
                                <div class="mt-1 space-y-1 text-sm text-gray-600">
                                    <p><strong>Codice:</strong> {{ $referee->referee->referee_code ?? 'N/A' }}</p>
                                    <p><strong>Livello:</strong>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $badgeClass }}">
                                            {{ $referee->referee->level_label ?? 'N/A' }}
                                        </span>
                                    </p>
                                    <p><strong>Zona:</strong> {{ $referee->zone->name ?? 'N/A' }}</p>
                                </div>
                            </label>

                            {{-- Conflicts Warning --}}
                            @if($hasConflicts)
                                <div class="mt-2 p-2 bg-red-100 border border-red-200 rounded">
                                    <p class="text-sm font-medium text-red-800">⚠️ Conflitti di Date:</p>
                                    @foreach($referee->conflicts as $conflict)
                                        <p class="text-xs text-red-700">
                                            • {{ $conflict->tournament->name }}
                                            ({{ $conflict->tournament->start_date->format('d/m') }} - {{ $conflict->tournament->end_date->format('d/m') }})
                                        </p>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Assignment Details (shown when selected) --}}
                    <div class="mt-4 space-y-3 referee-details" style="display: none;">
                        {{-- Hidden inputs for form data --}}
                        <input type="hidden" name="{{ $checkboxName }}[user_id]" value="{{ $referee->id }}">

                        {{-- Role Selection --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Ruolo</label>
                            <select name="{{ $checkboxName }}[role]"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 text-sm">
                                <option value="Arbitro">Arbitro</option>
                                <option value="Direttore di Torneo">Direttore di Torneo</option>
                                <option value="Osservatore">Osservatore</option>
                            </select>
                        </div>

                        {{-- Notes --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Note (opzionali)</label>
                            <textarea name="{{ $checkboxName }}[notes]"
                                      rows="2"
                                      placeholder="Note per questa assegnazione..."
                                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 text-sm"></textarea>
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
            } else {
                detailsDiv.style.display = 'none';
            }
        });
    });
});
</script>
@endpush

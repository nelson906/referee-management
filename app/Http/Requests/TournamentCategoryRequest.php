<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\TournamentCategory;

class TournamentCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Solo super admin può gestire le categorie
        return $this->user()->user_type === 'super_admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $categoryId = $this->route('tournament_category')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tournament_categories')->ignore($categoryId),
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('tournament_categories')->ignore($categoryId),
            ],
            'description' => 'nullable|string|max:500',
            'is_national' => 'boolean',
            'level' => ['required', Rule::in(array_keys(TournamentCategory::CATEGORY_LEVELS))],
            'required_referee_level' => ['required', Rule::in(array_keys(TournamentCategory::REFEREE_LEVELS))],
            'min_referees' => 'required|integer|min:1|max:10',
            'max_referees' => 'required|integer|min:1|max:10|gte:min_referees',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'special_requirements' => 'nullable|string|max:1000',
            'visibility_zones' => 'nullable|array',
            'visibility_zones.*' => 'exists:zones,id',
            'notification_templates' => 'nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Il nome della categoria è obbligatorio.',
            'name.unique' => 'Esiste già una categoria con questo nome.',
            'code.required' => 'Il codice categoria è obbligatorio.',
            'code.unique' => 'Questo codice è già utilizzato da un\'altra categoria.',
            'code.alpha_dash' => 'Il codice può contenere solo lettere, numeri, trattini e underscore.',
            'level.required' => 'Il livello della categoria è obbligatorio.',
            'level.in' => 'Il livello selezionato non è valido.',
            'required_referee_level.required' => 'Il livello arbitro richiesto è obbligatorio.',
            'required_referee_level.in' => 'Il livello arbitro selezionato non è valido.',
            'min_referees.required' => 'Il numero minimo di arbitri è obbligatorio.',
            'min_referees.min' => 'Deve essere richiesto almeno un arbitro.',
            'max_referees.gte' => 'Il numero massimo di arbitri deve essere maggiore o uguale al minimo.',
            'visibility_zones.*.exists' => 'Una o più zone selezionate non sono valide.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Converti checkbox in boolean
        $this->merge([
            'is_national' => $this->has('is_national'),
            'is_active' => $this->has('is_active'),
        ]);

        // Se è nazionale, rimuovi visibility_zones
        if ($this->is_national) {
            $this->request->remove('visibility_zones');
        }

        // Converti code in uppercase
        if ($this->has('code')) {
            $this->merge([
                'code' => strtoupper($this->code)
            ]);
        }

        // Assicura che min e max referees siano interi
        if ($this->has('min_referees')) {
            $this->merge([
                'min_referees' => (int) $this->min_referees
            ]);
        }
        if ($this->has('max_referees')) {
            $this->merge([
                'max_referees' => (int) $this->max_referees
            ]);
        }
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'nome categoria',
            'code' => 'codice',
            'description' => 'descrizione',
            'is_national' => 'categoria nazionale',
            'level' => 'livello',
            'required_referee_level' => 'livello arbitro richiesto',
            'min_referees' => 'numero minimo arbitri',
            'max_referees' => 'numero massimo arbitri',
            'sort_order' => 'ordine visualizzazione',
            'is_active' => 'stato attivo',
            'special_requirements' => 'requisiti speciali',
            'visibility_zones' => 'zone di visibilità',
        ];
    }
}

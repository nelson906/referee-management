<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LetterTemplate extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'type',
        'subject',
        'body',
        'variables',
        'is_active',
        'zone_id',
        'tournament_category_id',
        'description',
        'settings',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    /**
     * Template types
     */
    const TYPE_CONVOCATION = 'convocation';
    const TYPE_ASSIGNMENT = 'assignment';
    const TYPE_CIRCLE = 'circle';
    const TYPE_GENERAL = 'general';

    const TYPES = [
        self::TYPE_CONVOCATION => 'Convocazione Arbitro',
        self::TYPE_ASSIGNMENT => 'Notifica Assegnazione',
        self::TYPE_CIRCLE => 'Comunicazione Circolo',
        self::TYPE_GENERAL => 'Generale',
    ];

    /**
     * Available variables by type
     */
    const AVAILABLE_VARIABLES = [
        'common' => [
            'tournament_name' => 'Nome Torneo',
            'tournament_dates' => 'Date Torneo',
            'tournament_category' => 'Categoria Torneo',
            'circle_name' => 'Nome Circolo',
            'circle_address' => 'Indirizzo Circolo',
            'zone_name' => 'Nome Zona',
            'current_date' => 'Data Corrente',
            'current_year' => 'Anno Corrente',
        ],
        'convocation' => [
            'referee_name' => 'Nome Arbitro',
            'referee_code' => 'Codice Arbitro',
            'referee_level' => 'Livello Arbitro',
            'role' => 'Ruolo Assegnato',
            'assignment_notes' => 'Note Assegnazione',
        ],
        'assignment' => [
            'referee_name' => 'Nome Arbitro',
            'referee_email' => 'Email Arbitro',
            'assigned_date' => 'Data Assegnazione',
            'assigned_by' => 'Assegnato da',
        ],
        'circle' => [
            'contact_person' => 'Persona di Contatto',
            'referee_list' => 'Lista Arbitri',
            'total_referees' => 'Totale Arbitri',
        ],
    ];

    /**
     * Get the zone that the template belongs to.
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * Get the tournament category that the template belongs to.
     */
    public function tournamentCategory(): BelongsTo
    {
        return $this->belongsTo(TournamentCategory::class);
    }

    /**
     * Scope a query to only include active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include templates for a specific type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to only include templates for a specific zone.
     */
    public function scopeForZone($query, $zoneId)
    {
        return $query->where(function ($q) use ($zoneId) {
            $q->whereNull('zone_id')
              ->orWhere('zone_id', $zoneId);
        });
    }

    /**
     * Scope a query to only include templates for a specific category.
     */
    public function scopeForCategory($query, $categoryId)
    {
        return $query->where(function ($q) use ($categoryId) {
            $q->whereNull('tournament_category_id')
              ->orWhere('tournament_category_id', $categoryId);
        });
    }

    /**
     * Get the type label
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Get available variables for this template type
     */
    public function getAvailableVariablesAttribute(): array
    {
        $variables = self::AVAILABLE_VARIABLES['common'] ?? [];

        if (isset(self::AVAILABLE_VARIABLES[$this->type])) {
            $variables = array_merge($variables, self::AVAILABLE_VARIABLES[$this->type]);
        }

        return $variables;
    }

    /**
     * Get variables as formatted list
     */
    public function getVariablesListAttribute(): array
    {
        $list = [];
        foreach ($this->available_variables as $key => $label) {
            $list[] = [
                'variable' => '{{' . $key . '}}',
                'description' => $label,
            ];
        }
        return $list;
    }

    /**
     * Get scope display
     */
    public function getScopeDisplayAttribute(): string
    {
        $scope = [];

        if ($this->zone) {
            $scope[] = 'Zona: ' . $this->zone->name;
        }

        if ($this->tournamentCategory) {
            $scope[] = 'Categoria: ' . $this->tournamentCategory->name;
        }

        return !empty($scope) ? implode(' - ', $scope) : 'Globale';
    }

    /**
     * Preview template with sample data
     */
    public function preview(array $data = []): array
    {
        // Merge with sample data
        $sampleData = [
            'tournament_name' => 'Torneo di Esempio',
            'tournament_dates' => '01/07 - 03/07/2025',
            'tournament_category' => 'Open Nazionale',
            'circle_name' => 'Golf Club Esempio',
            'circle_address' => 'Via Esempio 123, Città',
            'zone_name' => 'Zona Centro',
            'current_date' => now()->format('d/m/Y'),
            'current_year' => now()->year,
            'referee_name' => 'Mario Rossi',
            'referee_code' => 'ARB001',
            'referee_level' => 'Nazionale',
            'role' => 'Arbitro',
        ];

        $data = array_merge($sampleData, $data);

        // Replace variables
        $subject = $this->subject;
        $body = $this->body;

        foreach ($data as $key => $value) {
            $subject = str_replace('{{' . $key . '}}', $value, $subject);
            $body = str_replace('{{' . $key . '}}', $value, $body);
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }

    /**
     * Clone template
     */
    public function cloneTemplate(array $overrides = []): self
    {
        $clone = $this->replicate();
        $clone->name = $this->name . ' (Copia)';
        $clone->is_active = false;

        foreach ($overrides as $key => $value) {
            $clone->$key = $value;
        }

        $clone->save();

        return $clone;
    }
}

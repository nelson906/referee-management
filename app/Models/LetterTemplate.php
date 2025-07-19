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
        'tournament_type_id',
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
    const TYPE_club = 'club';
    const TYPE_GENERAL = 'general';

    const TYPES = [
        self::TYPE_CONVOCATION => 'Convocazione Arbitro',
        self::TYPE_ASSIGNMENT => 'Notifica Assegnazione',
        self::TYPE_club => 'Comunicazione Circolo',
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
            'club_name' => 'Nome Circolo',
            'club_address' => 'Indirizzo Circolo',
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
        'club' => [
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
     * Get the tournament type that the template belongs to.
     */
    public function tournamentType(): BelongsTo
    {
        return $this->belongsTo(TournamentType::class);
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
            $q->whereNull('tournament_type_id')
              ->orWhere('tournament_type_id', $categoryId);
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
            'club_name' => 'Golf Club Esempio',
            'club_address' => 'Via Esempio 123, Città',
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
    <?php
// File: app/Models/LetterTemplate.php - AGGIUNTE AL MODEL ESISTENTE

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LetterTemplate extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'type',
        'subject',
        'body',
        'zone_id',
        'tournament_type_id',
        'is_active',
        'is_default',
        'variables',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'variables' => 'array',
    ];

    /**
     * Available template types
     */
    const TYPES = [
        'assignment' => 'Notifica Assegnazione',
        'convocation' => 'Convocazione',
        'club' => 'Comunicazione Circolo',
        'institutional' => 'Comunicazione Istituzionale'
    ];

    /**
     * Available variables for templates
     */
    const AVAILABLE_VARIABLES = [
        '{{tournament_name}}' => 'Nome del torneo',
        '{{tournament_dates}}' => 'Date del torneo',
        '{{tournament_category}}' => 'Categoria del torneo',
        '{{club_name}}' => 'Nome del circolo',
        '{{club_address}}' => 'Indirizzo del circolo',
        '{{club_phone}}' => 'Telefono del circolo',
        '{{club_email}}' => 'Email del circolo',
        '{{zone_name}}' => 'Nome della zona',
        '{{referee_name}}' => 'Nome dell\'arbitro',
        '{{referee_email}}' => 'Email dell\'arbitro',
        '{{referee_phone}}' => 'Telefono dell\'arbitro',
        '{{assignment_role}}' => 'Ruolo assegnazione',
        '{{assigned_date}}' => 'Data di assegnazione',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    /**
     * Get the zone that owns the letter template.
     */
    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * Get the tournament type that owns the letter template.
     */
    public function tournamentType()
    {
        return $this->belongsTo(TournamentType::class);
    }

    // ========================================
    // ACCESSORS & MUTATORS
    // ========================================

    /**
     * Get the type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }

    /**
     * Get the status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return $this->is_active ? 'Attivo' : 'Inattivo';
    }

    /**
     * Get the status color for UI.
     */
    public function getStatusColorAttribute(): string
    {
        return $this->is_active ? 'green' : 'red';
    }

    /**
     * Get the scope label (zone, tournament type, or global).
     */
    public function getScopeLabelAttribute(): string
    {
        if ($this->tournament_type_id) {
            return 'Categoria Torneo';
        } elseif ($this->zone_id) {
            return 'Zonale';
        } else {
            return 'Globale';
        }
    }

    /**
     * Get the default label.
     */
    public function getDefaultLabelAttribute(): string
    {
        return $this->is_default ? 'Predefinito' : 'Standard';
    }

    /**
     * Get the variables used in the template.
     */
    public function getUsedVariablesAttribute(): array
    {
        $usedVariables = [];
        $content = $this->subject . ' ' . $this->body;

        foreach (self::AVAILABLE_VARIABLES as $variable => $description) {
            if (strpos($content, $variable) !== false) {
                $usedVariables[$variable] = $description;
            }
        }

        return $usedVariables;
    }

    /**
     * Get template preview with sample data.
     */
    public function getPreviewSubjectAttribute(): string
    {
        return $this->replaceWithSampleData($this->subject);
    }

    /**
     * Get template preview with sample data.
     */
    public function getPreviewBodyAttribute(): string
    {
        return $this->replaceWithSampleData($this->body);
    }

    // ========================================
    // SCOPES
    // ========================================

    /**
     * Scope a query to only include active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include templates of a specific type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to only include templates for a specific zone.
     */
    public function scopeForZone($query, int $zoneId)
    {
        return $query->where(function ($q) use ($zoneId) {
            $q->where('zone_id', $zoneId)
              ->orWhereNull('zone_id');
        });
    }

    /**
     * Scope a query to only include templates for a specific tournament type.
     */
    public function scopeForTournamentType($query, int $tournamentTypeId)
    {
        return $query->where(function ($q) use ($tournamentTypeId) {
            $q->where('tournament_type_id', $tournamentTypeId)
              ->orWhereNull('tournament_type_id');
        });
    }

    /**
     * Scope a query to only include default templates.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope a query to only include global templates.
     */
    public function scopeGlobal($query)
    {
        return $query->whereNull('zone_id')
                    ->whereNull('tournament_type_id');
    }

    // ========================================
    // METHODS
    // ========================================

    /**
     * Get the best matching template for a specific context.
     */
    public static function getBestMatch(string $type, ?int $zoneId = null, ?int $tournamentTypeId = null): ?self
    {
        $query = static::active()->ofType($type);

        // Try to find most specific match first
        if ($tournamentTypeId) {
            $template = $query->clone()
                             ->where('tournament_type_id', $tournamentTypeId)
                             ->where('is_default', true)
                             ->first();
            if ($template) return $template;
        }

        if ($zoneId) {
            $template = $query->clone()
                             ->where('zone_id', $zoneId)
                             ->where('is_default', true)
                             ->first();
            if ($template) return $template;
        }

        // Fallback to global default
        return $query->global()
                    ->where('is_default', true)
                    ->first();
    }

    /**
     * Replace variables with actual data.
     */
    public function replaceVariables(array $data): array
    {
        $subject = $this->subject;
        $body = $this->body;

        foreach ($data as $variable => $value) {
            $subject = str_replace($variable, $value, $subject);
            $body = str_replace($variable, $value, $body);
        }

        return [
            'subject' => $subject,
            'body' => $body
        ];
    }

    /**
     * Replace variables with sample data for preview.
     */
    private function replaceWithSampleData(string $text): string
    {
        $sampleData = [
            '{{tournament_name}}' => 'Campionato Regionale di Golf',
            '{{tournament_dates}}' => '15/06/2024 - 16/06/2024',
            '{{tournament_category}}' => 'Torneo Nazionale',
            '{{club_name}}' => 'Golf Club Roma',
            '{{club_address}}' => 'Via del Golf, 123 - Roma',
            '{{club_phone}}' => '+39 06 1234567',
            '{{club_email}}' => 'info@golfclub.roma.it',
            '{{zone_name}}' => 'Zona Lazio',
            '{{referee_name}}' => 'Mario Rossi',
            '{{referee_email}}' => 'mario.rossi@email.com',
            '{{referee_phone}}' => '+39 333 1234567',
            '{{assignment_role}}' => 'Direttore di Torneo',
            '{{assigned_date}}' => now()->format('d/m/Y'),
        ];

        foreach ($sampleData as $variable => $value) {
            $text = str_replace($variable, $value, $text);
        }

        return $text;
    }

    /**
     * Toggle active status.
     */
    public function toggleActive(): bool
    {
        $this->is_active = !$this->is_active;
        return $this->save();
    }

    /**
     * Set as default template for the type/zone.
     */
    public function setAsDefault(): bool
    {
        // Unset other defaults
        static::where('type', $this->type)
             ->where('zone_id', $this->zone_id)
             ->where('tournament_type_id', $this->tournament_type_id)
             ->where('id', '!=', $this->id)
             ->update(['is_default' => false]);

        $this->is_default = true;
        return $this->save();
    }

    /**
     * Duplicate the template.
     */
    public function duplicate(string $newName = null): self
    {
        $duplicate = $this->replicate();
        $duplicate->name = $newName ?? ($this->name . ' (Copia)');
        $duplicate->is_default = false;
        $duplicate->save();

        return $duplicate;
    }

    /**
     * Validate template variables.
     */
    public function validateVariables(): array
    {
        $errors = [];
        $content = $this->subject . ' ' . $this->body;

        // Find all variables in the content
        preg_match_all('/\{\{[^}]+\}\}/', $content, $matches);

        foreach ($matches[0] as $variable) {
            if (!array_key_exists($variable, self::AVAILABLE_VARIABLES)) {
                $errors[] = "Variabile non riconosciuta: {$variable}";
            }
        }

        return $errors;
    }

    /**
     * Get statistics for letter templates.
     */
    public static function getStatistics(): array
    {
        return [
            'total' => static::count(),
            'active' => static::active()->count(),
            'inactive' => static::where('is_active', false)->count(),
            'default' => static::where('is_default', true)->count(),
            'by_type' => static::selectRaw('type, COUNT(*) as count')
                              ->groupBy('type')
                              ->pluck('count', 'type')
                              ->toArray(),
            'by_zone' => static::with('zone')
                              ->get()
                              ->groupBy('zone.name')
                              ->map(function ($templates) {
                                  return $templates->count();
                              })
                              ->toArray(),
        ];
    }

    /**
     * Get usage statistics for the template.
     */
    public function getUsageStatistics(): array
    {
        $notifications = \App\Models\Notification::where('template_used', $this->name)
                                                ->selectRaw('status, COUNT(*) as count')
                                                ->groupBy('status')
                                                ->pluck('count', 'status')
                                                ->toArray();

        return [
            'total_sent' => array_sum($notifications),
            'by_status' => $notifications,
            'last_used' => \App\Models\Notification::where('template_used', $this->name)
                                                 ->latest('created_at')
                                                 ->value('created_at'),
        ];
    }
}
}

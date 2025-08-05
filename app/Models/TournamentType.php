<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name
 * @property string $short_name
 * @property string|null $description
 * @property bool $is_national
 * @property string $level
 * @property string $required_level
 * @property int $min_referees
 * @property int $max_referees
 * @property int $sort_order
 * @property bool $is_active
 * @property array<array-key, mixed>|null $settings
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $requires_approval
 * @property int $priority_level
 * @property int $active
 * @property-read array $display_info
 * @property-read string $formatted_name
 * @property-read array $notification_templates
 * @property-read string $required_referee_level
 * @property-read string|null $special_requirements
 * @property-read mixed $visibility_zones
 * @property-read TournamentType|null $tournamentType
 * @property-read TournamentType|null $tournament_type
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tournament> $tournaments
 * @property-read int|null $tournaments_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentType active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentType national()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentType ordered()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentType query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentType whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentType whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentType whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentType whereIsNational($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentType whereLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentType whereMaxReferees($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentType whereMinReferees($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentType whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentType wherePriorityLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentType whereRequiredLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentType whereRequiresApproval($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentType whereSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentType whereShortName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentType whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentType whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentType zonal()
 * @mixin \Eloquent
 */
class TournamentType extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'tournament_types';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'short_name',
        'description',
        'is_national',
        'required_level',
        'level',
        'sort_order',
        'is_active',
        'settings',
        'min_referees',
        'max_referees',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_national' => 'boolean',
        'is_active' => 'boolean',
        'settings' => 'array',
        'sort_order' => 'integer',
        'min_referees' => 'integer',
        'max_referees' => 'integer',
    ];

    /**
     * Default settings structure
     *
     * @var array
     */
    protected $attributes = [
        'settings' => '{}',
        'is_active' => true,
        'sort_order' => 0,
        'level' => 'zonale',
        'min_referees' => 1,
        'max_referees' => 1,
    ];

    /**
     * Referee levels
     */
    const REFEREE_LEVELS = [
        'aspirante' => 'Aspirante',
        'primo_livello' => 'Primo Livello',
        'regionale' => 'Regionale',
        'nazionale' => 'Nazionale',
        'internazionale' => 'Internazionale',
    ];

    /**
     * Category levels
     */
    const CATEGORY_LEVELS = [
        'zonale' => 'Zonale',
        'nazionale' => 'Nazionale',
    ];

    /**
     * Boot method to keep JSON settings in sync with physical columns
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            // ✅ FIXED: Gestisce correttamente sia array che stringa JSON
            $settings = $model->settings;

            // Se settings è una stringa JSON, convertila in array
            if (is_string($settings)) {
                $settings = json_decode($settings, true) ?: [];
            }

            // Se settings è null, usa array vuoto
            if (!is_array($settings)) {
                $settings = [];
            }

            // Sync physical columns to JSON settings
            $settings['min_referees'] = $model->min_referees;
            $settings['max_referees'] = $model->max_referees;

            // Riassegna come array (il cast lo convertirà in JSON automaticamente)
            $model->settings = $settings;
        });
    }

    /**
     * Get the tournaments for the type.
     */
    public function tournaments(): HasMany
    {
        return $this->hasMany(Tournament::class, 'tournament_type_id');
    }

    /**
     * Scope a query to only include active types.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include national types.
     */
    public function scopeNational($query)
    {
        return $query->where('is_national', true);
    }

    /**
     * Scope a query to only include zonal types.
     */
    public function scopeZonal($query)
    {
        return $query->where('is_national', false);
    }

    /**
     * Scope a query to order by sort order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('name'); // Solo nome
    }



    /**
     * Get the required referee level from settings (for backward compatibility)
     */
    public function getRequiredRefereeLevelAttribute(): string
    {
        // ✅ FIXED: Gestisce correttamente l'accesso a settings
        $settings = $this->getSettingsAsArray();
        return $settings['required_referee_level'] ?? $this->required_level ?? 'aspirante';
    }

    /**
     * Get the visibility zones setting
     */
    public function getVisibilityZonesAttribute()
    {
        $settings = $this->getSettingsAsArray();
        return $settings['visibility_zones'] ?? ($this->is_national ? 'all' : 'own');
    }

    /**
     * Check if type requires specific referee level
     */
    public function requiresRefereeLevel(string $level): bool
    {
        // Se level è null, considera come aspirante
        if ($level === null) {
            $level = 'aspirante';
        }
        $levels = array_keys(self::REFEREE_LEVELS);
        $requiredIndex = array_search($this->required_referee_level, $levels);
        $checkIndex = array_search($level, $levels);

        return $checkIndex !== false && $requiredIndex !== false && $checkIndex >= $requiredIndex;
    }

    /**
     * Get notification templates for this type
     */
    public function getNotificationTemplatesAttribute(): array
    {
        $settings = $this->getSettingsAsArray();
        return $settings['notification_templates'] ?? [];
    }

    /**
     * Get special requirements
     */
    public function getSpecialRequirementsAttribute(): ?string
    {
        $settings = $this->getSettingsAsArray();
        return $settings['special_requirements'] ?? null;
    }

    /**
     * ✅ NEW: Helper method to get settings as array safely
     */
    private function getSettingsAsArray(): array
    {
        $settings = $this->settings;

        if (is_string($settings)) {
            return json_decode($settings, true) ?: [];
        }

        return is_array($settings) ? $settings : [];
    }

    /**
     * Update settings and sync with physical columns
     */
    public function updateSettings(array $settings): void
    {
        $currentSettings = $this->getSettingsAsArray();
        $newSettings = array_merge($currentSettings, $settings);

        // Update physical columns if provided in settings
        if (isset($settings['min_referees'])) {
            $this->min_referees = $settings['min_referees'];
        }
        if (isset($settings['max_referees'])) {
            $this->max_referees = $settings['max_referees'];
        }

        $this->settings = $newSettings;
        $this->save();
    }

    /**
     * Get available for zones
     */
    public function isAvailableForZone($zoneId): bool
    {
        if ($this->is_national || $this->visibility_zones === 'all') {
            return true;
        }

        if (is_array($this->visibility_zones)) {
            return in_array($zoneId, $this->visibility_zones);
        }

        return false;
    }

    /**
     * Get formatted name with level
     */
    public function getFormattedNameAttribute(): string
    {
        $prefix = $this->is_national ? '[NAZ] ' : '[ZONA] ';
        return $prefix . $this->name;
    }

    /**
     * Check if can be deleted
     */
    public function canBeDeleted(): bool
    {
        return !$this->tournaments()->exists();
    }

    /**
     * Get display information
     */
    public function getDisplayInfoAttribute(): array
    {
        return [
            'formatted_name' => $this->formatted_name,
            'referee_range' => "{$this->min_referees}-{$this->max_referees} arbitri",
            'required_level' => self::REFEREE_LEVELS[$this->required_referee_level] ?? 'Non specificato',
            'visibility' => $this->is_national ? 'Nazionale' : 'Zonale',
        ];
    }

    /**
     * Get the tournament type.
     */
    public function tournamentType(): BelongsTo
    {
        return $this->belongsTo(TournamentType::class, 'tournament_type_id');
    }

    // Alias per compatibilità se necessario
    public function tournament_type(): BelongsTo
    {
        return $this->tournamentType();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentCategory extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'description',
        'is_national',
        'required_level',
        'level',
        'sort_order',
        'is_active',
        'settings',
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
    ];

    /**
     * Referee levels
     */
    const REFEREE_LEVELS = [
        'aspirante' => 'Aspirante',
        '1_livello' => 'Primo Livello',
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
     * Get the tournaments for the category.
     */
    public function tournaments(): HasMany
    {
        return $this->hasMany(Tournament::class, 'tournament_category_id');
    }

    /**
     * Scope a query to only include active categories.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include national categories.
     */
    public function scopeNational($query)
    {
        return $query->where('is_national', true);
    }

    /**
     * Scope a query to only include zonal categories.
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
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Get the minimum number of referees required
     */
    public function getMinRefereesAttribute(): int
    {
        return $this->settings['min_referees'] ?? 1;
    }

    /**
     * Get the maximum number of referees allowed
     */
    public function getMaxRefereesAttribute(): int
    {
        return $this->settings['max_referees'] ?? $this->min_referees;
    }

    /**
     * Get the required referee level
     */
    public function getRequiredRefereeLevelAttribute(): string
    {
        return $this->settings['required_referee_level'] ?? 'aspirante';
    }

    /**
     * Get the visibility zones setting
     */
    public function getVisibilityZonesAttribute()
    {
        return $this->settings['visibility_zones'] ?? ($this->is_national ? 'all' : 'own');
    }

    /**
     * Check if category requires specific referee level
     */
    public function requiresRefereeLevel(string $level): bool
    {
        $levels = array_keys(self::REFEREE_LEVELS);
        $requiredIndex = array_search($this->required_referee_level, $levels);
        $checkIndex = array_search($level, $levels);

        return $checkIndex >= $requiredIndex;
    }

    /**
     * Get notification templates for this category
     */
    public function getNotificationTemplatesAttribute(): array
    {
        return $this->settings['notification_templates'] ?? [];
    }

    /**
     * Get special requirements
     */
    public function getSpecialRequirementsAttribute(): ?string
    {
        return $this->settings['special_requirements'] ?? null;
    }

    /**
     * Update settings
     */
    public function updateSettings(array $settings): void
    {
        $this->settings = array_merge($this->settings ?? [], $settings);
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
     * Get color for calendar display
     */
    public function getCalendarColorAttribute(): string
    {
        // Definisci colori per codici specifici
        $colorMap = [
            'T18' => '#3B82F6',      // Blue
            'S14' => '#8B5CF6',      // Purple
            'GN-36' => '#EC4899',    // Pink
            'GN-54' => '#EF4444',    // Red
            'GN-72' => '#F59E0B',    // Amber
            'GN-72/54' => '#F97316', // Orange
            'CI' => '#84CC16',       // Lime
            'CNZ' => '#14B8A6',      // Teal
            'TNZ' => '#06B6D4',      // Cyan
        ];

        // Se è nazionale, usa indigo
        if ($this->is_national) {
            return '#4F46E5';
        }

        // Altrimenti usa il colore mappato o verde di default
        return $colorMap[$this->code] ?? '#10B981';
    }
}

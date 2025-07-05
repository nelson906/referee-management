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
     * Boot method to keep JSON settings in sync with physical columns
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            // Sync physical columns to JSON settings
            $settings = $model->settings ?? [];
            $settings['min_referees'] = $model->min_referees;
            $settings['max_referees'] = $model->max_referees;
            $model->settings = $settings;
        });
    }

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
     * Get the required referee level from settings (for backward compatibility)
     */
    public function getRequiredRefereeLevelAttribute(): string
    {
        return $this->settings['required_referee_level'] ?? $this->required_level ?? 'aspirante';
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

        return $checkIndex !== false && $requiredIndex !== false && $checkIndex >= $requiredIndex;
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
     * Update settings and sync with physical columns
     */
    public function updateSettings(array $settings): void
    {
        $currentSettings = $this->settings ?? [];
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
}

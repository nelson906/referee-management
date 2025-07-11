<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Referee extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'zone_id',
        'referee_code',
        'level',
        'category',
        'certified_date',
        'phone',
        'address',
        'city',
        'postal_code',
        'tax_code',
        'is_active',
        'profile_completed_at',
        'preferences',
        'badge_number',
        'first_certification_date',
        'last_renewal_date',
        'expiry_date',
        'qualifications',
        'languages',
        'available_for_international',
        'specializations',
        'total_tournaments',
        'tournaments_current_year',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'certified_date' => 'date',
        'first_certification_date' => 'date',
        'last_renewal_date' => 'date',
        'expiry_date' => 'date',
        'profile_completed_at' => 'datetime',
        'is_active' => 'boolean',
        'available_for_international' => 'boolean',
        'preferences' => 'array',
        'qualifications' => 'array',
        'languages' => 'array',
        'total_tournaments' => 'integer',
        'tournaments_current_year' => 'integer',
    ];

    /**
     * Referee levels
     */
    const LEVEL_ASPIRANTE = 'aspirante';
    const LEVEL_PRIMO_LIVELLO = 'primo_livello';
    const LEVEL_REGIONALE = 'regionale';
    const LEVEL_NAZIONALE = 'nazionale';
    const LEVEL_INTERNAZIONALE = 'internazionale';

    const REFEREE_LEVELS = [
        self::LEVEL_ASPIRANTE => 'Aspirante',
        self::LEVEL_PRIMO_LIVELLO => 'Primo Livello',
        self::LEVEL_REGIONALE => 'Regionale',
        self::LEVEL_NAZIONALE => 'Nazionale',
        self::LEVEL_INTERNAZIONALE => 'Internazionale',
    ];

    /**
     * Referee categories
     */
    const CATEGORY_MASCHILE = 'maschile';
    const CATEGORY_FEMMINILE = 'femminile';
    const CATEGORY_MISTO = 'misto';

    const CATEGORIES = [
        self::CATEGORY_MASCHILE => 'Maschile',
        self::CATEGORY_FEMMINILE => 'Femminile',
        self::CATEGORY_MISTO => 'Misto',
    ];

    /**
     * Get the user that owns the referee profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the zone that the referee belongs to.
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * Get the availabilities for the referee.
     */
    public function availabilities(): HasMany
    {
        return $this->hasMany(Availability::class, 'user_id', 'user_id');
    }

    /**
     * Get the assignments for the referee.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'user_id', 'user_id');
    }

    /**
     * Scope a query to only include active referees.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include referees from a specific zone.
     */
    public function scopeInZone($query, $zoneId)
    {
        return $query->where('zone_id', $zoneId);
    }

    /**
     * Scope a query to only include national level referees.
     */
    public function scopeNationalLevel($query)
    {
        return $query->whereIn('level', [self::LEVEL_NAZIONALE, self::LEVEL_INTERNAZIONALE]);
    }

    /**
     * Check if referee can access national tournaments.
     */
    public function canAccessNationalTournaments(): bool
    {
        return in_array($this->level, [self::LEVEL_NAZIONALE, self::LEVEL_INTERNAZIONALE]);
    }

    /**
     * Check if referee has completed their profile.
     */
    public function hasCompletedProfile(): bool
    {
        return !is_null($this->profile_completed_at) &&
            !is_null($this->referee_code) &&
            !is_null($this->level) &&
            !is_null($this->certified_date) &&
            !is_null($this->phone);
    }

    /**
     * Mark profile as completed.
     */
    public function markProfileAsCompleted(): void
    {
        $this->update(['profile_completed_at' => now()]);
    }

    /**
     * Get the referee's level label.
     */
    public function getLevelLabelAttribute(): string
    {
        return self::REFEREE_LEVELS[$this->level] ?? ucfirst($this->level ?? '');
    }

    /**
     * Get the referee's category label.
     */
    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst($this->category ?? '');
    }

    /**
     * Get the referee's full name with code.
     */
    public function getFullNameWithCodeAttribute(): string
    {
        return "{$this->user->name} ({$this->referee_code})";
    }

    /**
     * Get upcoming assignments count.
     */
    public function getUpcomingAssignmentsCountAttribute(): int
    {
        return $this->assignments()
            ->whereHas('tournament', function ($q) {
                $q->where('start_date', '>=', now());
            })
            ->count();
    }

    /**
     * Get this year's assignments count.
     */
    public function getThisYearAssignmentsCountAttribute(): int
    {
        return $this->assignments()
            ->whereHas('tournament', function ($q) {
                $q->whereYear('start_date', now()->year);
            })
            ->count();
    }

    /**
     * Get availability count for current year.
     */
    public function getThisYearAvailabilitiesCountAttribute(): int
    {
        return $this->availabilities()
            ->whereHas('tournament', function ($q) {
                $q->whereYear('start_date', now()->year);
            })
            ->count();
    }

    /**
     * Generate unique referee code.
     */
    public static function generateRefereeCode(): string
    {
        do {
            $code = 'ARB' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (self::where('referee_code', $code)->exists());

        return $code;
    }

    /**
     * Check if certification is expiring soon.
     */
    public function isCertificationExpiringSoon(int $days = 30): bool
    {
        if (!$this->expiry_date) {
            return false;
        }

        return $this->expiry_date->lte(now()->addDays($days));
    }

    /**
     * Check if certification is expired.
     */
    public function isCertificationExpired(): bool
    {
        if (!$this->expiry_date) {
            return false;
        }

        return $this->expiry_date->lt(now());
    }

    public function isProfileComplete(): bool
    {
        // Se è stato marcato come completato (dall'admin o dal form), è completo
        if ($this->profile_completed_at !== null) {
            return true;
        }

        // Controllo minimale: solo campi veramente essenziali
        return !empty($this->user->name) &&
            !empty($this->user->email) &&
            !empty($this->level) &&
            !empty($this->zone_id);
    }

    // Aggiungi anche questo metodo helper:
    public function markAsCompleted()
    {
        $this->update(['profile_completed_at' => now()]);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'referee_code',
        'level',
        'category',
        'zone_id',
        'phone',
        'notes',
        'is_active',
        'user_type',
        'certified_date',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'certified_date' => 'date',
    ];

    /**
     * User types
     */
    const TYPE_REFEREE = 'referee';
    const TYPE_ADMIN = 'admin';
    const TYPE_NATIONAL_ADMIN = 'national_admin';
    const TYPE_SUPER_ADMIN = 'super_admin';

    const USER_TYPES = [
        self::TYPE_REFEREE => 'Arbitro',
        self::TYPE_ADMIN => 'Admin Zona',
        self::TYPE_NATIONAL_ADMIN => 'Admin CRC',
        self::TYPE_SUPER_ADMIN => 'Super Admin',
    ];

    /**
     * Referee levels
     */
    const LEVEL_ASPIRANT = 'aspirante';
    const LEVEL_FIRST = 'primo_livello';
    const LEVEL_REGIONAL = 'regionale';
    const LEVEL_NATIONAL = 'nazionale';
    const LEVEL_INTERNATIONAL = 'internazionale';
    const LEVEL_ARCHIVE = 'archivio';

    const REFEREE_LEVELS = [
        self::LEVEL_ASPIRANT => 'Aspirante',
        self::LEVEL_FIRST => 'Primo Livello',
        self::LEVEL_REGIONAL => 'Regionale',
        self::LEVEL_NATIONAL => 'Nazionale',
        self::LEVEL_INTERNATIONAL => 'Internazionale',
        self::LEVEL_ARCHIVE => 'Archivio',
    ];

    /**
     * Get the zone that the user belongs to.
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * Get the referee details.
     */
    public function refereeDetails(): HasOne
    {
        return $this->hasOne(RefereeDetail::class);
    }

    /**
     * Get the availabilities declared by the user.
     */
    public function availabilities(): HasMany
    {
        return $this->hasMany(Availability::class);
    }

    /**
     * Get the assignments for the user.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * Get the assignments made by this user (as admin).
     */
    public function assignmentsMade(): HasMany
    {
        return $this->hasMany(Assignment::class, 'assigned_by');
    }

    /**
     * Scope a query to only include active users.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include referees.
     */
    public function scopeReferees($query)
    {
        return $query->where('user_type', self::TYPE_REFEREE);
    }

    /**
     * Scope a query to only include admins (all types).
     */
    public function scopeAdmins($query)
    {
        return $query->whereIn('user_type', [
            self::TYPE_ADMIN,
            self::TYPE_NATIONAL_ADMIN,
            self::TYPE_SUPER_ADMIN
        ]);
    }

    /**
     * Scope a query to only include users from a specific zone.
     */
    public function scopeFromZone($query, $zoneId)
    {
        return $query->where('zone_id', $zoneId);
    }

    /**
     * Scope a query to only include referees of a specific level.
     */
    public function scopeOfLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Scope a query to only include national/international referees.
     */
    public function scopeNationalReferees($query)
    {
        return $query->whereIn('level', [self::LEVEL_NATIONAL, self::LEVEL_INTERNATIONAL]);
    }

    /**
     * Check if user is a referee
     */
    public function isReferee(): bool
    {
        return $this->user_type === self::TYPE_REFEREE;
    }

    /**
     * Check if user is an admin (any type)
     */
    public function isAdmin(): bool
    {
        return in_array($this->user_type, [
            self::TYPE_ADMIN,
            self::TYPE_NATIONAL_ADMIN,
            self::TYPE_SUPER_ADMIN
        ]);
    }

    /**
     * Check if user is a zone admin
     */
    public function isZoneAdmin(): bool
    {
        return $this->user_type === self::TYPE_ADMIN;
    }

    /**
     * Check if user is a national admin
     */
    public function isNationalAdmin(): bool
    {
        return $this->user_type === self::TYPE_NATIONAL_ADMIN;
    }

    /**
     * Check if user is a super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->user_type === self::TYPE_SUPER_ADMIN;
    }

    /**
     * Check if user can manage zone
     */
    public function canManageZone($zoneId): bool
    {
        if ($this->isSuperAdmin() || $this->isNationalAdmin()) {
            return true;
        }

        if ($this->isZoneAdmin() && $this->zone_id == $zoneId) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can view tournament
     */
    public function canViewTournament(Tournament $tournament): bool
    {
        // Admins can view based on zone access
        if ($this->isAdmin()) {
            return $this->canManageZone($tournament->zone_id);
        }

        // Referees can view if tournament is visible to them
        if ($this->isReferee()) {
            // National tournaments are visible to all
            if ($tournament->tournamentCategory->is_national) {
                return true;
            }

            // Zone tournaments are visible to zone referees
            return $this->zone_id === $tournament->zone_id;
        }

        return false;
    }

    /**
     * Get user type label
     */
    public function getUserTypeLabelAttribute(): string
    {
        return self::USER_TYPES[$this->user_type] ?? $this->user_type;
    }

    /**
     * Get referee level label
     */
    public function getLevelLabelAttribute(): string
    {
        return self::REFEREE_LEVELS[$this->level] ?? $this->level;
    }

    /**
     * Get full display name with code
     */
    public function getFullNameAttribute(): string
    {
        if ($this->referee_code) {
            return "{$this->name} ({$this->referee_code})";
        }
        return $this->name;
    }

    /**
     * Get upcoming assignments
     */
    public function getUpcomingAssignmentsAttribute()
    {
        return $this->assignments()
            ->upcoming()
            ->with(['tournament.club', 'tournament.zone'])
            ->orderBy('tournaments.start_date')
            ->get();
    }

    /**
     * Get statistics for referee
     */
    public function getRefereeStatisticsAttribute(): array
    {
        if (!$this->isReferee()) {
            return [];
        }

        return [
            'total_availabilities' => $this->availabilities()->count(),
            'total_assignments' => $this->assignments()->count(),
            'confirmed_assignments' => $this->assignments()->confirmed()->count(),
            'upcoming_assignments' => $this->assignments()->upcoming()->count(),
            'completed_assignments' => $this->assignments()->whereHas('tournament', function ($q) {
                $q->where('status', 'completed');
            })->count(),
            'current_year_assignments' => $this->assignments()
                ->whereYear('assigned_at', now()->year)
                ->count(),
        ];
    }

    /**
     * Check if can be assigned to tournament
     */
    public function canBeAssignedToTournament(Tournament $tournament): bool
    {
        if (!$this->isReferee() || !$this->is_active) {
            return false;
        }

        // Check if already assigned
        if ($this->assignments()->where('tournament_id', $tournament->id)->exists()) {
            return false;
        }

        // Check level requirement
        if (!$tournament->tournamentCategory->requiresRefereeLevel($this->level)) {
            return false;
        }

        // Check zone for non-national tournaments
        if (!$tournament->tournamentCategory->is_national && $this->zone_id !== $tournament->zone_id) {
            return false;
        }

        return true;
    }
    /**
     * Check if user has completed their profile.
     * Add this method to your app/Models/User.php file
     */
    public function hasCompletedProfile(): bool
    {
        // Per gli admin, considerali sempre con profilo completo
        if ($this->isAdmin()) {
            return true;
        }

        // Per gli arbitri, verifica campi obbligatori
        if ($this->isReferee()) {
            return !empty($this->name) &&
                   !empty($this->email) &&
                   !empty($this->referee_code) &&
                   !empty($this->level) &&
                   !empty($this->zone_id) &&
                   !empty($this->phone);
        }

        // Default: profilo completo se ha nome ed email
        return !empty($this->name) && !empty($this->email);
    }

}

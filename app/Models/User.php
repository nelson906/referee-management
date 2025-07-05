<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'user_type',
        'zone_id',
        'phone',
        'city',
        'is_active',
        'email_verified_at',
        'last_login_at',
        'preferences',
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
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
        'preferences' => 'array',
        'password' => 'hashed',
    ];

    /**
     * User types
     */
    const TYPE_SUPER_ADMIN = 'super_admin';
    const TYPE_NATIONAL_ADMIN = 'national_admin';
    const TYPE_ADMIN = 'admin';
    const TYPE_REFEREE = 'referee';

    const USER_TYPES = [
        self::TYPE_SUPER_ADMIN => 'Super Admin',
        self::TYPE_NATIONAL_ADMIN => 'Amministratore Nazionale',
        self::TYPE_ADMIN => 'Amministratore Zona',
        self::TYPE_REFEREE => 'Arbitro',
    ];

    /**
     * Get the referee details (if user is a referee).
     */
    public function referee(): HasOne
    {
        return $this->hasOne(Referee::class);
    }

    /**
     * Get the zone that the user belongs to.
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * Get the availabilities for the user.
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
     * Get the notifications for the user.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'assignment_id');
    }

    /**
     * Get assignments created by this user (for admins).
     */
    public function createdAssignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'assigned_by_id');
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
     * Scope a query to only include admins.
     */
    public function scopeAdmins($query)
    {
        return $query->whereIn('user_type', [self::TYPE_ADMIN, self::TYPE_NATIONAL_ADMIN]);
    }

    /**
     * Scope a query to only include users from a specific zone.
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
     * Check if user is a super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->user_type === self::TYPE_SUPER_ADMIN;
    }

    /**
     * Check if user is a national admin.
     */
    public function isNationalAdmin(): bool
    {
        return $this->user_type === self::TYPE_NATIONAL_ADMIN;
    }

    /**
     * Check if user is an admin (zone or national).
     */
    public function isAdmin(): bool
    {
        return in_array($this->user_type, [self::TYPE_ADMIN, self::TYPE_NATIONAL_ADMIN]);
    }

    /**
     * Check if user is a referee.
     */
    public function isReferee(): bool
    {
        return $this->user_type === self::TYPE_REFEREE;
    }

    /**
     * Check if referee can access national tournaments.
     */
    public function canAccessNationalTournaments(): bool
    {
        if (!$this->isReferee() || !$this->referee) {
            return false;
        }

        return $this->referee->canAccessNationalTournaments();
    }

    /**
     * Check if user has completed their profile.
     */
    public function hasCompletedProfile(): bool
    {
        if (!$this->isReferee()) {
            return true; // Non-referees don't need extended profile
        }

        return $this->referee && $this->referee->hasCompletedProfile();
    }

    /**
     * Mark profile as completed.
     */
    public function markProfileAsCompleted(): void
    {
        if ($this->isReferee() && $this->referee) {
            $this->referee->markProfileAsCompleted();
        }
    }

    /**
     * Get the user's full name with referee code.
     */
    public function getFullNameWithCodeAttribute(): string
    {
        if ($this->isReferee() && $this->referee && $this->referee->referee_code) {
            return "{$this->name} ({$this->referee->referee_code})";
        }

        return $this->name;
    }

    /**
     * Get the user's level label.
     */
    public function getLevelLabelAttribute(): string
    {
        if ($this->isReferee() && $this->referee) {
            return $this->referee->level_label;
        }

        return '';
    }

    /**
     * Get the user's category label.
     */
    public function getCategoryLabelAttribute(): string
    {
        if ($this->isReferee() && $this->referee) {
            return $this->referee->category_label;
        }

        return '';
    }

    /**
     * Get the user's type label.
     */
    public function getUserTypeLabelAttribute(): string
    {
        return self::USER_TYPES[$this->user_type] ?? ucfirst($this->user_type);
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
     * Update last login timestamp.
     */
    public function updateLastLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }
}

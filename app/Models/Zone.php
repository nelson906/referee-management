<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Zone extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'description',
        'region',
        'contact_person',
        'contact_email',
        'contact_phone',
        'address',
        'city',
        'postal_code',
        'is_active',
        'is_national',
        'sort_order',
        'settings',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'is_national' => 'boolean',
        'sort_order' => 'integer',
        'settings' => 'array',
    ];

    /**
     * Default settings structure
     */
    protected $attributes = [
        'settings' => '{}',
        'is_active' => true,
        'is_national' => false,
        'sort_order' => 0,
    ];

    /**
     * Get the users (referees and admins) for the zone.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the referees for the zone.
     */
    public function referees(): HasMany
    {
        return $this->hasMany(User::class)->referees();
    }

    /**
     * Get the active referees for the zone.
     */
    public function activeReferees(): HasMany
    {
        return $this->hasMany(User::class)->referees()->active();
    }

    /**
     * Get the zone admins.
     */
    public function admins(): HasMany
    {
        return $this->hasMany(User::class)->where('user_type', User::TYPE_ADMIN);
    }

    /**
     * Get the clubs in the zone.
     */
    public function clubs(): HasMany
    {
        return $this->hasMany(Club::class);
    }

    /**
     * Get the tournaments in the zone.
     */
    public function tournaments(): HasMany
    {
        return $this->hasMany(Tournament::class);
    }

    /**
     * Get the institutional emails for the zone.
     */
    public function institutionalEmails(): HasMany
    {
        return $this->hasMany(InstitutionalEmail::class);
    }

    /**
     * Get the letter templates for the zone.
     */
    public function letterTemplates(): HasMany
    {
        return $this->hasMany(LetterTemplate::class);
    }

    /**
     * Scope a query to only include active zones.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include national zones.
     */
    public function scopeNational($query)
    {
        return $query->where('is_national', true);
    }

    /**
     * Scope a query to only include regional zones.
     */
    public function scopeRegional($query)
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
     * Get the zone's full address.
     */
    public function getFullAddressAttribute(): string
    {
        $address = collect([
            $this->address,
            $this->postal_code ? $this->postal_code . ' ' . $this->city : $this->city,
        ])->filter()->implode(', ');

        return $address;
    }

    /**
     * Get the zone's contact info.
     */
    public function getContactInfoAttribute(): string
    {
        $info = [];

        if ($this->contact_person) {
            $info[] = $this->contact_person;
        }

        if ($this->contact_email) {
            $info[] = $this->contact_email;
        }

        if ($this->contact_phone) {
            $info[] = $this->contact_phone;
        }

        return implode(' - ', $info);
    }

    /**
     * Get referees count by level.
     */
    public function getRefereesByLevel(): array
    {
        $referees = $this->activeReferees()
            ->selectRaw('level, COUNT(*) as count')
            ->groupBy('level')
            ->pluck('count', 'level')
            ->toArray();

        // Ensure all levels are present
        $levels = array_keys(User::REFEREE_LEVELS);
        $result = [];

        foreach ($levels as $level) {
            $result[$level] = $referees[$level] ?? 0;
        }

        return $result;
    }

    /**
     * Get this year's tournament count.
     */
    public function getThisYearTournamentsCountAttribute(): int
    {
        return $this->tournaments()
            ->whereYear('start_date', now()->year)
            ->count();
    }

    /**
     * Get this year's assignments count.
     */
    public function getThisYearAssignmentsCountAttribute(): int
    {
        return Assignment::whereHas('tournament', function ($q) {
                $q->where('zone_id', $this->id)
                  ->whereYear('start_date', now()->year);
            })
            ->count();
    }

    /**
     * Get upcoming tournaments count.
     */
    public function getUpcomingTournamentsCountAttribute(): int
    {
        return $this->tournaments()
            ->where('start_date', '>=', now())
            ->count();
    }

    /**
     * Check if zone has active admin.
     */
    public function hasActiveAdmin(): bool
    {
        return $this->admins()->active()->exists();
    }

    /**
     * Get statistics for the zone.
     */
    public function getStatistics(): array
    {
        return [
            'total_referees' => $this->activeReferees()->count(),
            'referees_by_level' => $this->getRefereesByLevel(),
            'total_clubs' => $this->clubs()->active()->count(),
            'tournaments_this_year' => $this->this_year_tournaments_count,
            'assignments_this_year' => $this->this_year_assignments_count,
            'upcoming_tournaments' => $this->upcoming_tournaments_count,
        ];
    }

    /**
     * Check if zone can be deleted.
     */
    public function canBeDeleted(): bool
    {
        // Cannot delete if has users, clubs, or tournaments
        return $this->users()->count() === 0 &&
               $this->clubs()->count() === 0 &&
               $this->tournaments()->count() === 0;
    }

    /**
     * Deactivate zone and related entities.
     */
    public function deactivate(): void
    {
        $this->update(['is_active' => false]);

        // Deactivate related users
        $this->users()->update(['is_active' => false]);

        // Deactivate related clubs
        $this->clubs()->update(['is_active' => false]);
    }

    /**
     * Activate zone and related entities.
     */
    public function activate(): void
    {
        $this->update(['is_active' => true]);

        // Note: We don't automatically activate users and clubs
        // as they might have been deactivated for other reasons
    }
}

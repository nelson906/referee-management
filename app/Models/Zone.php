<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name
 * @property string|null $code
 * @property string|null $description
 * @property bool $is_national
 * @property int $is_active
 * @property string|null $header_document_path
 * @property \Illuminate\Support\Carbon|null $header_updated_at
 * @property int|null $header_updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $admins
 * @property-read int|null $admins_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Club> $clubs
 * @property-read int|null $clubs_count
 * @property-read int $active_circles_count
 * @property-read int $active_clubs_count
 * @property-read int $active_referees_count
 * @property-read int $active_tournaments_count
 * @property-read array $referees_by_level
 * @property-read array $statistics
 * @property-read array $tournaments_by_category
 * @property-read int $upcoming_tournaments_count
 * @property-read \App\Models\User|null $headerUpdatedBy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InstitutionalEmail> $institutionalEmails
 * @property-read int|null $institutional_emails_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LetterTemplate> $letterTemplates
 * @property-read int|null $letter_templates_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Letterhead> $letterheads
 * @property-read int|null $letterheads_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $referees
 * @property-read int|null $referees_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tournament> $tournaments
 * @property-read int|null $tournaments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Zone active()
 * @method static \Database\Factories\ZoneFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Zone newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Zone newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Zone ordered()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Zone query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Zone regional()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Zone whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Zone whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Zone whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Zone whereHeaderDocumentPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Zone whereHeaderUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Zone whereHeaderUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Zone whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Zone whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Zone whereIsNational($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Zone whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Zone whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Zone extends Model
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
        'header_document_path',
        'header_updated_at',
        'header_updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_national' => 'boolean',
        'header_updated_at' => 'datetime',
    ];

    /**
     * Get the users in this zone.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the referees in this zone.
     */
    public function referees(): HasMany
    {
        return $this->hasMany(User::class)->where('user_type', 'referee');
    }

    /**
     * Get the admins in this zone.
     */
    public function admins(): HasMany
    {
        return $this->hasMany(User::class)->where('user_type', 'admin');
    }

    /**
     * Get the clubs in this zone.
     */
    public function clubs(): HasMany
    {
        return $this->hasMany(Club::class);
    }

    /**
     * Get the tournaments in this zone.
     */
    public function tournaments(): HasMany
    {
        return $this->hasMany(Tournament::class);
    }

    /**
     * Get the institutional emails for this zone.
     */
    public function institutionalEmails(): HasMany
    {
        return $this->hasMany(InstitutionalEmail::class);
    }

    /**
     * Get the letter templates for this zone.
     */
    public function letterTemplates(): HasMany
    {
        return $this->hasMany(LetterTemplate::class);
    }

    /**
     * Get the letterheads for this zone.
     */
    public function letterheads(): HasMany
    {
        return $this->hasMany(Letterhead::class);
    }

    /**
     * Get the user who last updated the header.
     */
    public function headerUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'header_updated_by');
    }

    /**
     * Scope a query to only include non-national zones.
     */
    public function scopeRegional($query)
    {
        return $query->where('is_national', false);
    }

    /**
     * Get active referees count
     */
    public function getActiveRefereesCountAttribute(): int
    {
        return $this->referees()->where('is_active', true)->count();
    }

    /**
     * Get active clubs count
     */
    public function getActiveClubsCountAttribute(): int
    {
        return $this->clubs()->where('is_active', true)->count();
    }

    /**
     * DEPRECATED: Use getActiveClubsCountAttribute() instead
     */
    public function getActiveCirclesCountAttribute(): int
    {
        return $this->getActiveClubsCountAttribute();
    }

    /**
     * Get upcoming tournaments count
     */
    public function getUpcomingTournamentsCountAttribute(): int
    {
        return $this->tournaments()->upcoming()->count();
    }

    /**
     * Get active tournaments count
     */
    public function getActiveTournamentsCountAttribute(): int
    {
        return $this->tournaments()->active()->count();
    }

    /**
     * Get statistics for the zone
     */
    public function getStatisticsAttribute(): array
    {
        return [
            'total_referees' => $this->referees()->count(),
            'active_referees' => $this->active_referees_count,
            'total_clubs' => $this->clubs()->count(),
            'active_clubs' => $this->active_clubs_count,
            'total_circles' => $this->clubs()->count(), // For backward compatibility
            'active_circles' => $this->active_clubs_count, // For backward compatibility
            'total_tournaments' => $this->tournaments()->count(),
            'upcoming_tournaments' => $this->upcoming_tournaments_count,
            'active_tournaments' => $this->active_tournaments_count,
            'completed_tournaments' => $this->tournaments()->where('status', 'completed')->count(),
        ];
    }

    /**
     * Get referee statistics by level
     */
    public function getRefereesByLevelAttribute(): array
    {
        return $this->referees()
            ->where('is_active', true)
            ->get()
            ->groupBy('level')
            ->map(function ($referees) {
                return $referees->count();
            })
            ->toArray();
    }

    /**
     * Get tournaments by category
     */
    public function getTournamentsByCategoryAttribute(): array
    {
        return $this->tournaments()
            ->with('tournamentType')
            ->get()
            ->groupBy('tournamentType.name')
            ->map(function ($tournaments) {
                return $tournaments->count();
            })
            ->toArray();
    }

    /**
 * Scope per ottenere solo le zone attive
 */
public function scopeActive($query)
{
    return $query->where('is_active', true);
}

/**
 * Scope per ordinare le zone per nome
 */
public function scopeOrdered($query)
{
    return $query->orderBy('name');
}
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $code
 * @property int $zone_id
 * @property string|null $address
 * @property string|null $city
 * @property string|null $postal_code
 * @property string|null $province
 * @property string|null $region
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $website
 * @property string|null $contact_person
 * @property string|null $contact_phone
 * @property string|null $contact_email
 * @property bool $is_active
 * @property string|null $federation_code
 * @property int|null $founded_year
 * @property int|null $holes_count
 * @property int|null $par
 * @property string|null $course_rating
 * @property int|null $slope_rating
 * @property string|null $settings
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property-read int $active_tournaments_count
 * @property-read array $contact_info
 * @property-read string $display_name
 * @property-read string $full_address
 * @property-read int $upcoming_tournaments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tournament> $tournaments
 * @property-read int|null $tournaments_count
 * @property-read \App\Models\Zone $zone
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club active()
 * @method static \Database\Factories\ClubFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club fromZone($zoneId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club ordered()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club search($search)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereContactEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereContactPerson($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereContactPhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereCourseRating($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereFederationCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereFoundedYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereHolesCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club wherePar($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club wherePostalCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereProvince($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereRegion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereSlopeRating($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereWebsite($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereZoneId($value)
 * @mixin \Eloquent
 */
class Club extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'clubs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'city',
        'province',
        'email',
        'phone',
        'address',
        'contact_person',
        'zone_id',
        'notes',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the zone that the club belongs to.
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * Get the tournaments hosted by the club.
     */
    public function tournaments(): HasMany
    {
        return $this->hasMany(Tournament::class, 'club_id');
    }

    /**
     * Scope a query to only include active clubs.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include clubs from a specific zone.
     */
    public function scopeFromZone($query, $zoneId)
    {
        return $query->where('zone_id', $zoneId);
    }

    /**
     * Get the full address
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->province
        ]);

        return implode(', ', $parts);
    }

    /**
     * Get upcoming tournaments count
     */
    public function getUpcomingTournamentsCountAttribute(): int
    {
        return $this->tournaments()
            ->upcoming()
            ->count();
    }

    /**
     * Get active tournaments count
     */
    public function getActiveTournamentsCountAttribute(): int
    {
        return $this->tournaments()
            ->active()
            ->count();
    }

    /**
     * Check if club has any active tournaments
     */
    public function hasActiveTournaments(): bool
    {
        return $this->tournaments()
            ->active()
            ->exists();
    }

    /**
     * Get formatted contact info
     */
    public function getContactInfoAttribute(): array
    {
        return [
            'person' => $this->contact_person,
            'email' => $this->email,
            'phone' => $this->phone,
        ];
    }

    /**
     * Search clubs by name or code
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('code', 'like', "%{$search}%")
              ->orWhere('city', 'like', "%{$search}%");
        });
    }

    /**
     * Order by name
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('name');
    }

    /**
     * Get display name with code
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->name} ({$this->code})";
    }

    /**
     * Check if can be deleted
     */
    public function canBeDeleted(): bool
    {
        return !$this->tournaments()->exists();
    }
}

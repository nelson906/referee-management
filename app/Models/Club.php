<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

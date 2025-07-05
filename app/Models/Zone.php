<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
     * Get the circles in this zone.
     */
    public function circles(): HasMany
    {
        return $this->hasMany(Circle::class);
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
     * Get active circles count
     */
    public function getActiveCirclesCountAttribute(): int
    {
        return $this->circles()->where('is_active', true)->count();
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
            'total_circles' => $this->circles()->count(),
            'active_circles' => $this->active_circles_count,
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
            ->with('tournamentCategory')
            ->get()
            ->groupBy('tournamentCategory.name')
            ->map(function ($tournaments) {
                return $tournaments->count();
            })
            ->toArray();
    }

    /**
     * Check if zone has custom letterhead
     */
    public function hasCustomLetterhead(): bool
    {
        return $this->letterheads()->where('is_active', true)->exists();
    }

    /**
     * Get default letterhead for zone
     */
    public function getDefaultLetterheadAttribute(): ?Letterhead
    {
        return $this->letterheads()
            ->where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->first();
    }

    /**
     * Get header document URL
     */
    public function getHeaderDocumentUrlAttribute(): ?string
    {
        if (!$this->header_document_path) {
            return null;
        }

        return asset('storage/' . $this->header_document_path);
    }

    /**
     * Update header document
     */
    public function updateHeaderDocument(string $path, $userId = null): void
    {
        $this->update([
            'header_document_path' => $path,
            'header_updated_at' => now(),
            'header_updated_by' => $userId ?? auth()->id(),
        ]);
    }
}

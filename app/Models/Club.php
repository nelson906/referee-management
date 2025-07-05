<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Club extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     * Uses 'circles' table since that's our database structure
     *
     * @var string
     */
    protected $table = 'circles';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'zone_id',
        'address',
        'city',
        'postal_code',
        'province',
        'region',
        'phone',
        'email',
        'website',
        'contact_person',
        'contact_phone',
        'contact_email',
        'is_active',
        'federation_code',
        'settings',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    /**
     * Default settings structure
     */
    protected $attributes = [
        'settings' => '{}',
        'is_active' => true,
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
        return $this->hasMany(Tournament::class, 'circle_id');
    }
    }

    /**
     * Get the upcoming tournaments hosted by the club.
     */
    public function upcomingTournaments(): HasMany
    {
        return $this->hasMany(Tournament::class)->where('start_date', '>=', now());
    }

    /**
     * Get the past tournaments hosted by the club.
     */
    public function pastTournaments(): HasMany
    {
        return $this->hasMany(Tournament::class)->where('end_date', '<', now());
    }

    /**
     * Scope a query to only include active clubs.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include clubs in a specific zone.
     */
    public function scopeInZone($query, $zoneId)
    {
        return $query->where('zone_id', $zoneId);
    }

    /**
     * Scope a query to search clubs by name or code.
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
     * Get the club's full address.
     */
    public function getFullAddressAttribute(): string
    {
        $addressParts = collect([
            $this->address,
            $this->postal_code ? $this->postal_code . ' ' . $this->city : $this->city,
            $this->province ? '(' . $this->province . ')' : null,
        ])->filter()->toArray();

        return implode(', ', $addressParts);
    }

    /**
     * Get the club's primary contact info.
     */
    public function getPrimaryContactAttribute(): string
    {
        if ($this->contact_person) {
            $contact = $this->contact_person;

            if ($this->contact_phone) {
                $contact .= ' - ' . $this->contact_phone;
            }

            if ($this->contact_email) {
                $contact .= ' - ' . $this->contact_email;
            }

            return $contact;
        }

        // Fallback to general contact info
        $contact = [];

        if ($this->phone) {
            $contact[] = $this->phone;
        }

        if ($this->email) {
            $contact[] = $this->email;
        }

        return implode(' - ', $contact);
    }

    /**
     * Get the club's display name with code.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->code) {
            return "{$this->name} ({$this->code})";
        }

        return $this->name;
    }

    /**
     * Get this year's tournaments count.
     */
    public function getThisYearTournamentsCountAttribute(): int
    {
        return $this->tournaments()
            ->whereYear('start_date', now()->year)
            ->count();
    }

    /**
     * Get upcoming tournaments count.
     */
    public function getUpcomingTournamentsCountAttribute(): int
    {
        return $this->upcomingTournaments()->count();
    }

    /**
     * Get total assignments count for this club.
     */
    public function getTotalAssignmentsCountAttribute(): int
    {
        return Assignment::whereHas('tournament', function ($q) {
            $q->where('club_id', $this->id);
        })->count();
    }

    /**
     * Get this year's assignments count.
     */
    public function getThisYearAssignmentsCountAttribute(): int
    {
        return Assignment::whereHas('tournament', function ($q) {
            $q->where('club_id', $this->id)
              ->whereYear('start_date', now()->year);
        })->count();
    }

    /**
     * Check if club has valid contact information.
     */
    public function hasValidContactInfo(): bool
    {
        return !empty($this->email) || !empty($this->contact_email);
    }

    /**
     * Get best available email for notifications.
     */
    public function getBestEmailAttribute(): ?string
    {
        return $this->contact_email ?: $this->email;
    }

    /**
     * Get best available phone for contact.
     */
    public function getBestPhoneAttribute(): ?string
    {
        return $this->contact_phone ?: $this->phone;
    }

    /**
     * Check if club can host tournaments.
     */
    public function canHostTournaments(): bool
    {
        return $this->is_active &&
               $this->hasValidContactInfo() &&
               !empty($this->address) &&
               !empty($this->city);
    }

    /**
     * Get club statistics.
     */
    public function getStatistics(): array
    {
        return [
            'total_tournaments' => $this->tournaments()->count(),
            'tournaments_this_year' => $this->this_year_tournaments_count,
            'upcoming_tournaments' => $this->upcoming_tournaments_count,
            'total_assignments' => $this->total_assignments_count,
            'assignments_this_year' => $this->this_year_assignments_count,
        ];
    }

    /**
     * Get tournaments by status.
     */
    public function getTournamentsByStatus(): array
    {
        return $this->tournaments()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    /**
     * Check if club can be deleted.
     */
    public function canBeDeleted(): bool
    {
        // Cannot delete if has tournaments
        return $this->tournaments()->count() === 0;
    }

    /**
     * Deactivate club.
     */
    public function deactivate(): void
    {
        $this->update(['is_active' => false]);

        // Mark future tournaments as cancelled or draft
        $this->tournaments()
            ->where('start_date', '>', now())
            ->whereIn('status', ['open', 'assigned'])
            ->update(['status' => 'draft']);
    }

    /**
     * Generate unique club code.
     */
    public static function generateClubCode(string $name, ?string $city = null): string
    {
        // Create base code from name
        $baseCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 3));

        // Add city initial if available
        if ($city) {
            $baseCode .= strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $city), 0, 1));
        }

        // Ensure minimum length
        if (strlen($baseCode) < 3) {
            $baseCode = str_pad($baseCode, 3, 'X');
        }

        // Check for uniqueness and add number if needed
        $code = $baseCode;
        $counter = 1;

        while (self::where('code', $code)->exists()) {
            $code = $baseCode . str_pad($counter, 2, '0', STR_PAD_LEFT);
            $counter++;
        }

        return $code;
    }
}

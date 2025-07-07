<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Tournament extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'availability_deadline',
        'club_id',
        'tournament_category_id', // Changed from tournament_type_id
        'zone_id',
        'notes',
        'status',
        'convocation_letter',
        'club_letter',
        'letters_generated_at',
        'convocation_file_path',
        'convocation_file_name',
        'convocation_generated_at',
        'club_letter_file_path',
        'club_letter_file_name',
        'club_letter_generated_at',
        'documents_last_updated_by',
        'document_version',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'availability_deadline' => 'date',
        'letters_generated_at' => 'datetime',
        'convocation_generated_at' => 'datetime',
        'club_letter_generated_at' => 'datetime',
        'document_version' => 'integer',
    ];

    /**
     * Tournament statuses
     */
    const STATUS_DRAFT = 'draft';
    const STATUS_OPEN = 'open';
    const STATUS_CLOSED = 'closed';
    const STATUS_ASSIGNED = 'assigned';
    const STATUS_COMPLETED = 'completed';

    const STATUSES = [
        self::STATUS_DRAFT => 'Bozza',
        self::STATUS_OPEN => 'Aperto',
        self::STATUS_CLOSED => 'Chiuso',
        self::STATUS_ASSIGNED => 'Assegnato',
        self::STATUS_COMPLETED => 'Completato',
    ];

    /**
     * Get the club that hosts the tournament.
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * Get the tournament category.
     * Renamed from tournamentType
     */
    public function tournamentCategory(): BelongsTo
    {
        return $this->belongsTo(TournamentCategory::class);
    }

    /**
     * Alias for backward compatibility
     */
    public function tournament_type()
    {
        return $this->tournamentCategory();
    }

    /**
     * Get the zone.
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * Get the user who last updated documents.
     */
    public function documentsLastUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'documents_last_updated_by');
    }

    /**
     * Get the availabilities for the tournament.
     */
    public function availabilities(): HasMany
    {
        return $this->hasMany(Availability::class);
    }

    /**
     * Get the assignments for the tournament.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * Get available referees (those who declared availability)
     */
    public function availableReferees()
    {
        return $this->belongsToMany(User::class, 'availabilities')
                    ->withPivot('notes', 'submitted_at')
                    ->withTimestamps();
    }

   /**
 * Get assigned referees
 */
public function assignedReferees()
{
    return $this->belongsToMany(User::class, 'assignments')
                ->withPivot('role', 'is_confirmed', 'assigned_at', 'assigned_by_id', 'notes')
                ->withTimestamps();
}


    /**
     * Scope a query to only include tournaments visible to a specific zone.
     */
    public function scopeVisibleToZone($query, $zoneId)
    {
        return $query->where(function ($q) use ($zoneId) {
            $q->where('zone_id', $zoneId)
              ->orWhereHas('tournamentCategory', function ($q) {
                  $q->where('is_national', true);
              });
        });
    }

    /**
     * Scope a query to only include active tournaments.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_CLOSED, self::STATUS_ASSIGNED]);
    }

    /**
     * Scope a query to only include upcoming tournaments.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('start_date', '>=', Carbon::today());
    }

    /**
     * Scope a query to only include past tournaments.
     */
    public function scopePast($query)
    {
        return $query->where('end_date', '<', Carbon::today());
    }

    /**
     * Scope a query to only include tournaments open for availability.
     */
    public function scopeOpenForAvailability($query)
    {
        return $query->where('status', self::STATUS_OPEN)
                     ->where('availability_deadline', '>=', Carbon::today());
    }

    /**
     * Check if tournament is editable
     */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_OPEN]);
    }

    /**
     * Check if tournament is open for availability declarations
     */
    public function isOpenForAvailability(): bool
    {
        return $this->status === self::STATUS_OPEN
               && $this->availability_deadline >= Carbon::today();
    }

    /**
     * Check if tournament needs referees
     */
    public function needsReferees(): bool
    {
        $requiredReferees = $this->tournamentCategory->min_referees ?? 1;
        $assignedReferees = $this->assignments()->count();

        return $assignedReferees < $requiredReferees;
    }

    /**
     * Get the number of required referees
     */
    public function getRequiredRefereesAttribute(): int
    {
        return $this->tournamentCategory->min_referees ?? 1;
    }

    /**
     * Get the maximum number of referees allowed
     */
    public function getMaxRefereesAttribute(): int
    {
        return $this->tournamentCategory->max_referees ?? $this->required_referees;
    }

    /**
     * Check if a referee can be assigned
     */
    public function canAssignReferee(User $referee): bool
    {
        // Check if already assigned
        if ($this->assignments()->where('user_id', $referee->id)->exists()) {
            return false;
        }

        // Check if max referees reached
        if ($this->assignments()->count() >= $this->max_referees) {
            return false;
        }

        // Check referee level
        if (!$this->tournamentCategory->requiresRefereeLevel($referee->level)) {
            return false;
        }

        return true;
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Get status color for UI
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_DRAFT => 'gray',
            self::STATUS_OPEN => 'green',
            self::STATUS_CLOSED => 'yellow',
            self::STATUS_ASSIGNED => 'blue',
            self::STATUS_COMPLETED => 'gray',
            default => 'gray',
        };
    }

    /**
     * Get formatted date range
     */
    public function getDateRangeAttribute(): string
    {
        if ($this->start_date->equalTo($this->end_date)) {
            return $this->start_date->format('d/m/Y');
        }

        return $this->start_date->format('d/m/Y') . ' - ' . $this->end_date->format('d/m/Y');
    }

    /**
     * Get days until availability deadline
     */
    public function getDaysUntilDeadlineAttribute(): int
    {
        return Carbon::today()->diffInDays($this->availability_deadline, false);
    }

    /**
     * Update tournament status based on conditions
     */
    public function updateStatus(): void
    {
        // If past deadline and still open, close it
        if ($this->status === self::STATUS_OPEN && $this->availability_deadline < Carbon::today()) {
            $this->update(['status' => self::STATUS_CLOSED]);
        }

        // If has enough assignments and closed, mark as assigned
        if ($this->status === self::STATUS_CLOSED && $this->assignments()->count() >= $this->required_referees) {
            $this->update(['status' => self::STATUS_ASSIGNED]);
        }

        // If tournament date has passed and assigned, mark as completed
        if ($this->status === self::STATUS_ASSIGNED && $this->end_date < Carbon::today()) {
            $this->update(['status' => self::STATUS_COMPLETED]);
        }
    }

    /**
     * Generate document version number
     */
    public function incrementDocumentVersion(): void
    {
        $this->increment('document_version');
        $this->update(['documents_last_updated_by' => auth()->id()]);
    }

}

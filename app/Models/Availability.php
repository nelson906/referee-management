<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $tournament_id
 * @property int|null $is_available
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Assignment|null $assignment
 * @property-read int $days_since_submission
 * @property-read string $status
 * @property-read string $status_color
 * @property-read string $status_label
 * @property-read \App\Models\User $referee
 * @property-read \App\Models\Tournament $tournament
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Availability forOpenTournaments()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Availability forUpcomingTournaments()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Availability newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Availability newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Availability query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Availability unassigned()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Availability whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Availability whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Availability whereIsAvailable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Availability whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Availability whereSubmittedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Availability whereTournamentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Availability whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Availability whereUserId($value)
 * @mixin \Eloquent
 */
class Availability extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'tournament_id',
        'notes',
        'submitted_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    /**
     * Get the user (referee) who declared availability.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    /**
     * Alias for backward compatibility - referee is actually user
     */
    public function referee(): BelongsTo
    {
        return $this->user();
    }

    /**
     * Get the tournament for the availability.
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * Scope a query to only include availabilities for upcoming tournaments.
     */
    public function scopeForUpcomingTournaments($query)
    {
        return $query->whereHas('tournament', function ($q) {
            $q->upcoming();
        });
    }

    /**
     * Scope a query to only include availabilities for open tournaments.
     */
    public function scopeForOpenTournaments($query)
    {
        return $query->whereHas('tournament', function ($q) {
            $q->where('status', 'open');
        });
    }

    /**
     * Scope a query to only include unassigned availabilities.
     */
    public function scopeUnassigned($query)
    {
        return $query->whereDoesntHave('tournament.assignments', function ($q) {
            $q->where('user_id', $this->user_id);
        });
    }

    /**
     * Check if availability has been converted to assignment
     */
    public function hasBeenAssigned(): bool
    {
        return Assignment::where('tournament_id', $this->tournament_id)
            ->where('user_id', $this->user_id)
            ->exists();
    }

    /**
     * Get the assignment if exists
     */
    public function getAssignmentAttribute(): ?Assignment
    {
        return Assignment::where('tournament_id', $this->tournament_id)
            ->where('user_id', $this->user_id)
            ->first();
    }

    /**
     * Check if availability can be withdrawn
     */
    public function canBeWithdrawn(): bool
    {
        // Cannot withdraw if already assigned
        if ($this->hasBeenAssigned()) {
            return false;
        }

        // Cannot withdraw if tournament is not open
        if ($this->tournament->status !== 'open') {
            return false;
        }

        // Cannot withdraw if past deadline
        if ($this->tournament->availability_deadline < now()) {
            return false;
        }

        return true;
    }

    /**
     * Get days since submission
     */
    public function getDaysSinceSubmissionAttribute(): int
    {
        return $this->submitted_at->diffInDays(now());
    }

    /**
     * Get submission status
     */
    public function getStatusAttribute(): string
    {
        if ($this->hasBeenAssigned()) {
            return 'assigned';
        }

        if ($this->tournament->status === 'completed') {
            return 'not_selected';
        }

        if ($this->tournament->status === 'assigned') {
            return 'not_selected';
        }

        return 'pending';
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'assigned' => 'Assegnato',
            'not_selected' => 'Non selezionato',
            'pending' => 'In attesa',
            default => 'Sconosciuto',
        };
    }

    /**
     * Get status color
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'assigned' => 'green',
            'not_selected' => 'gray',
            'pending' => 'yellow',
            default => 'gray',
        };
    }


}

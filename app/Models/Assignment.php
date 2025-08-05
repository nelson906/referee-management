<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tournament_id
 * @property int $user_id
 * @property int $assigned_by_id
 * @property string $role
 * @property string|null $notes
 * @property bool $is_confirmed
 * @property string|null $confirmed_at
 * @property \Illuminate\Support\Carbon $assigned_at
 * @property int $notification_sent
 * @property string|null $notification_sent_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $assignedBy
 * @property-read string $status_color
 * @property-read string $status_label
 * @property-read \App\Models\User $referee
 * @property-read \App\Models\Tournament $tournament
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assignment confirmed()
 * @method static \Database\Factories\AssignmentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assignment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assignment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assignment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assignment unconfirmed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assignment upcoming()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assignment whereAssignedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assignment whereAssignedById($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assignment whereConfirmedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assignment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assignment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assignment whereIsConfirmed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assignment whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assignment whereNotificationSent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assignment whereNotificationSentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assignment whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assignment whereTournamentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assignment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assignment whereUserId($value)
 * @mixin \Eloquent
 */
class Assignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tournament_id',
        'role',
        'is_confirmed',
        'assigned_at',
        'assigned_by_id', // CORRETTO: usa assigned_by_id
        'notes',
    ];

    protected $casts = [
        'is_confirmed' => 'boolean',
        'assigned_at' => 'datetime',
    ];

    // Assignment roles
    const ROLE_REFEREE = 'Arbitro';
    const ROLE_TOURNAMENT_DIRECTOR = 'Direttore di Torneo';
    const ROLE_OBSERVER = 'Osservatore';

    const ROLES = [
        self::ROLE_REFEREE => 'Arbitro',
        self::ROLE_TOURNAMENT_DIRECTOR => 'Direttore di Torneo',
        self::ROLE_OBSERVER => 'Osservatore',
    ];

    /**
     * Get the referee (user) for the assignment.
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
    return $this->belongsTo(User::class, foreignKey: 'user_id'); // Stesso user
    }

    /**
     * Get the tournament for the assignment.
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * Get the user who made the assignment.
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_id'); // Cambiato da 'assigned_by'
    }

    /**
     * Scope a query to only include confirmed assignments.
     */
    public function scopeConfirmed($query)
    {
        return $query->where('is_confirmed', true);
    }

    /**
     * Scope a query to only include unconfirmed assignments.
     */
    public function scopeUnconfirmed($query)
    {
        return $query->where('is_confirmed', false);
    }

    /**
     * Scope a query to only include assignments for upcoming tournaments.
     */
    public function scopeUpcoming($query)
    {
        return $query->whereHas('tournament', function ($q) {
            $q->upcoming();
        });
    }

    /**
     * Check if assignment can be confirmed by the referee
     */
    public function canBeConfirmed(): bool
    {
        return !$this->is_confirmed &&
            $this->tournament->status === 'assigned' &&
            $this->tournament->start_date >= now();
    }

    /**
     * Get status label for UI
     */
    public function getStatusLabelAttribute(): string
    {
        if ($this->is_confirmed) {
            return 'Confermato';
        }

        if ($this->tournament->start_date < now()) {
            return 'Scaduto';
        }

        return 'In attesa di conferma';
    }

    /**
     * Get status color for UI
     */
    public function getStatusColorAttribute(): string
    {
        if ($this->is_confirmed) {
            return 'green';
        }

        if ($this->tournament->start_date < now()) {
            return 'gray';
        }

        return 'yellow';
    }

}

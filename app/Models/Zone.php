<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Assignment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tournament_id',
        'user_id',
        'assigned_by_id',
        'role',
        'notes',
        'is_confirmed',
        'confirmed_at',
        'assigned_at',
        'notification_sent',
        'notification_sent_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_confirmed' => 'boolean',
        'notification_sent' => 'boolean',
        'confirmed_at' => 'datetime',
        'assigned_at' => 'datetime',
        'notification_sent_at' => 'datetime',
    ];

    /**
     * Boot method to set default values
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($assignment) {
            if (!$assignment->assigned_at) {
                $assignment->assigned_at = now();
            }
        });
    }

    /**
     * Assignment roles
     */
    const ROLE_REFEREE = 'Arbitro';
    const ROLE_TOURNAMENT_DIRECTOR = 'Direttore di Torneo';
    const ROLE_OBSERVER = 'Osservatore';

    const ROLES = [
        self::ROLE_REFEREE => 'Arbitro',
        self::ROLE_TOURNAMENT_DIRECTOR => 'Direttore di Torneo',
        self::ROLE_OBSERVER => 'Osservatore',
    ];

    /**
     * Get the tournament that the assignment belongs to.
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * Get the user (referee) assigned.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who created the assignment.
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }

    /**
     * Get the notifications for the assignment.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
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
     * Scope a query to only include assignments for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include assignments for a specific tournament.
     */
    public function scopeForTournament($query, $tournamentId)
    {
        return $query->where('tournament_id', $tournamentId);
    }

    /**
     * Scope a query to only include assignments for a specific role.
     */
    public function scopeForRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Scope a query to only include assignments for upcoming tournaments.
     */
    public function scopeUpcoming($query)
    {
        return $query->whereHas('tournament', function ($q) {
            $q->where('start_date', '>=', now());
        });
    }

    /**
     * Scope a query to only include assignments for past tournaments.
     */
    public function scopePast($query)
    {
        return $query->whereHas('tournament', function ($q) {
            $q->where('end_date', '<', now());
        });
    }

    /**
     * Scope a query to include assignments in a specific zone.
     */
    public function scopeInZone($query, $zoneId)
    {
        return $query->whereHas('tournament', function ($q) use ($zoneId) {
            $q->where('zone_id', $zoneId);
        });
    }

    /**
     * Scope a query to include assignments for a specific year.
     */
    public function scopeForYear($query, $year = null)
    {
        $year = $year ?: now()->year;

        return $query->whereHas('tournament', function ($q) use ($year) {
            $q->whereYear('start_date', $year);
        });
    }

    /**
     * Confirm the assignment.
     */
    public function confirm(): void
    {
        $this->update([
            'is_confirmed' => true,
            'confirmed_at' => now(),
        ]);
    }

    /**
     * Mark assignment as notified.
     */
    public function markAsNotified(): void
    {
        $this->update([
            'notification_sent' => true,
            'notification_sent_at' => now(),
        ]);
    }

    /**
     * Check if assignment has been notified.
     */
    public function hasBeenNotified(): bool
    {
        return $this->notification_sent;
    }

    /**
     * Get the latest notification.
     */
    public function getLatestNotificationAttribute(): ?Notification
    {
        return $this->notifications()->latest()->first();
    }

    /**
     * Get days until tournament.
     */
    public function getDaysUntilTournamentAttribute(): int
    {
        return now()->diffInDays($this->tournament->start_date, false);
    }

    /**
     * Check if needs confirmation reminder.
     */
    public function needsConfirmationReminder(): bool
    {
        if ($this->is_confirmed) {
            return false;
        }

        // Send reminder if tournament is within 7 days and not confirmed
        return $this->days_until_tournament <= 7 && $this->days_until_tournament > 0;
    }

    /**
     * Get role badge color.
     */
    public function getRoleBadgeColorAttribute(): string
    {
        return match($this->role) {
            self::ROLE_REFEREE => 'blue',
            self::ROLE_TOURNAMENT_DIRECTOR => 'purple',
            self::ROLE_OBSERVER => 'gray',
            default => 'gray',
        };
    }

    /**
     * Get assignment status.
     */
    public function getStatusAttribute(): string
    {
        if ($this->tournament->status === 'completed') {
            return 'completed';
        }

        if ($this->is_confirmed) {
            return 'confirmed';
        }

        if ($this->days_until_tournament < 0) {
            return 'expired';
        }

        return 'pending';
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'completed' => 'Completato',
            'confirmed' => 'Confermato',
            'expired' => 'Scaduto',
            'pending' => 'In attesa',
            default => 'Sconosciuto',
        };
    }

    /**
     * Get status color.
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'completed' => 'gray',
            'confirmed' => 'green',
            'expired' => 'red',
            'pending' => 'yellow',
            default => 'gray',
        };
    }

    /**
     * Check if assignment can be confirmed.
     */
    public function canBeConfirmed(): bool
    {
        return !$this->is_confirmed &&
               $this->tournament->status !== 'completed' &&
               $this->days_until_tournament >= 0;
    }

    /**
     * Check if assignment can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return $this->tournament->status !== 'completed' &&
               $this->days_until_tournament > 0;
    }

    /**
     * Get assignment summary for notifications.
     */
    public function getSummaryAttribute(): string
    {
        return sprintf(
            '%s - %s (%s) - %s',
            $this->tournament->name,
            $this->tournament->date_range,
            $this->tournament->club->name,
            $this->role
        );
    }

    /**
     * Check if this is the main referee (not observer or director).
     */
    public function isMainReferee(): bool
    {
        return $this->role === self::ROLE_REFEREE;
    }

    /**
     * Check if assignment is for a national tournament.
     */
    public function isNationalTournament(): bool
    {
        return $this->tournament->tournamentCategory->is_national;
    }

    /**
     * Get assignment priority (for sorting).
     */
    public function getPriorityAttribute(): int
    {
        // Priority based on role and confirmation status
        $priority = 0;

        if ($this->role === self::ROLE_TOURNAMENT_DIRECTOR) {
            $priority += 100;
        } elseif ($this->role === self::ROLE_REFEREE) {
            $priority += 50;
        }

        if (!$this->is_confirmed) {
            $priority += 10;
        }

        if ($this->isNationalTournament()) {
            $priority += 5;
        }

        return $priority;
    }
}

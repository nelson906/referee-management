<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
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
        'role',
        'is_confirmed',
        'assigned_at',
        'assigned_by',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_confirmed' => 'boolean',
        'assigned_at' => 'datetime',
    ];

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
     * Get the referee (user) for the assignment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
        return $this->belongsTo(User::class, 'assigned_by');
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
            $q->upcoming();
        });
    }

    /**
     * Scope a query to only include assignments for past tournaments.
     */
    public function scopePast($query)
    {
        return $query->whereHas('tournament', function ($q) {
            $q->past();
        });
    }

    /**
     * Confirm the assignment
     */
    public function confirm(): void
    {
        $this->update(['is_confirmed' => true]);
    }

    /**
     * Check if assignment has been notified
     */
    public function hasBeenNotified(): bool
    {
        return $this->notifications()->sent()->exists();
    }

    /**
     * Get the latest notification
     */
    public function getLatestNotificationAttribute(): ?Notification
    {
        return $this->notifications()->latest()->first();
    }

    /**
     * Get days until tournament
     */
    public function getDaysUntilTournamentAttribute(): int
    {
        return now()->diffInDays($this->tournament->start_date, false);
    }

    /**
     * Check if needs confirmation reminder
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
     * Get role badge color
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
     * Get assignment status
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
     * Get status label
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
     * Get status color
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
}

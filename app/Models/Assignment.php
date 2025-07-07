<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

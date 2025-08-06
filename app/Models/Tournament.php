<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon $end_date
 * @property \Illuminate\Support\Carbon|null $availability_deadline
 * @property int $club_id
 * @property int $tournament_type_id
 * @property int $zone_id
 * @property string|null $description
 * @property string|null $notes
 * @property string $status
 * @property string|null $convocation_letter
 * @property string|null $club_letter
 * @property \Illuminate\Support\Carbon|null $letters_generated_at
 * @property string|null $convocation_file_path
 * @property string|null $convocation_file_name
 * @property \Illuminate\Support\Carbon|null $convocation_generated_at
 * @property string|null $club_letter_file_path
 * @property string|null $club_letter_file_name
 * @property \Illuminate\Support\Carbon|null $club_letter_generated_at
 * @property int|null $documents_last_updated_by
 * @property int $document_version
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $assignedReferees
 * @property-read int|null $assigned_referees_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Assignment> $assignments
 * @property-read int|null $assignments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Availability> $availabilities
 * @property-read int|null $availabilities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $availableReferees
 * @property-read int|null $available_referees_count
 * @property-read \App\Models\Club $club
 * @property-read \App\Models\User|null $documentsLastUpdatedBy
 * @property-read string $date_range
 * @property-read int $days_until_deadline
 * @property-read int $max_referees
 * @property-read array $notification_status
 * @property-read int $required_referees
 * @property-read string $status_color
 * @property-read string $status_label
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TournamentNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \App\Models\TournamentType $tournamentType
 * @property-read \App\Models\Zone $zone
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament active()
 * @method static \Database\Factories\TournamentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament notified()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament openForAvailability()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament past()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament readyForNotification()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament upcoming()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament visibleToZone($zoneId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereAvailabilityDeadline($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereClubId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereClubLetter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereClubLetterFileName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereClubLetterFilePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereClubLetterGeneratedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereConvocationFileName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereConvocationFilePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereConvocationGeneratedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereConvocationLetter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereDocumentVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereDocumentsLastUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereLettersGeneratedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereTournamentTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereZoneId($value)
 * @mixin \Eloquent
 */
class Tournament extends Model
{
    use HasFactory;

    public function getTable()
    {
        $year = session('selected_year', date('Y'));

        // Se esiste gare_YYYY, usa quella
        if (Schema::hasTable("gare_{$year}")) {
            return "gare_{$year}";
        }

        // Altrimenti usa tournaments (la VIEW)
        return 'tournaments';
    }

    protected $table = 'tournaments'; // tabella base

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
        'tournament_type_id',
        'zone_id', // ✅ tournament_type_id
        'notes',
        'status', // ... rest of fields
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
        'convocation_file_path',
        'convocation_file_name',
        'club_letter_file_path',
        'club_letter_file_name'
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
     * Get the tournament type (NEW primary relationship)
     */
    public function tournamentType(): BelongsTo
    {
        return $this->belongsTo(TournamentType::class);
    }

    /**
     * Alias for backward compatibility
     */
    public function tournamentCategory()
    {
        return $this->tournamentType();
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
     * Scope a query to only include tournaments visible to a specific zone.
     */
    public function scopeVisibleToZone($query, $zoneId)
    {
        return $query->where(function ($q) use ($zoneId) {
            $q->where('zone_id', $zoneId)
                ->orWhereHas('tournamentType', function ($q) {
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
        $requiredReferees = $this->tournamentType->min_referees ?? 1;
        $assignedReferees = $this->assignments()->count();

        return $assignedReferees < $requiredReferees;
    }

    /**
     * Get the number of required referees
     */
    public function getRequiredRefereesAttribute(): int
    {
        return $this->tournamentType->min_referees ?? 1;
    }

    /**
     * Get the maximum number of referees allowed
     */
    public function getMaxRefereesAttribute(): int
    {
        return $this->tournamentType->max_referees ?? $this->required_referees;
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
        if (!$this->tournamentType->requiresRefereeLevel($referee->level)) {
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
        return match ($this->status) {
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

    /**
     * Get available referees (those who declared availability)
     */
    public function availableReferees()
    {
        return $this->belongsToMany(User::class, 'availabilities', 'tournament_id', 'user_id')
            ->withPivot('notes', 'submitted_at')
            ->withTimestamps();
    }

    /**
     * Get assigned referees
     */
    public function assignedReferees()
    {
        return $this->belongsToMany(User::class, 'assignments', 'tournament_id', 'user_id')
            ->withPivot('role', 'is_confirmed', 'assigned_at', 'assigned_by_id', 'notes')
            ->withTimestamps();
    }



    /**
     * 📧 Relazione con notifiche torneo (nuovo sistema)
     */
    public function notifications()
    {
        return $this->hasMany(\App\Models\TournamentNotification::class);
    }

    /**
     * 📧 Verifica se ha notifiche inviate
     */
    public function hasNotifications(): bool
    {
        return $this->notifications()->exists();
    }

    /**
     * 📧 Ultima notifica inviata
     */
    public function lastNotification()
    {
        return $this->notifications()->latest('sent_at')->first();
    }

    /**
     * 📊 Stato notifiche per dashboard
     */
    public function getNotificationStatusAttribute(): array
    {
        $lastNotification = $this->lastNotification();

        if (!$lastNotification) {
            return [
                'status' => 'not_sent',
                'status_text' => '⏳ Non inviato',
                'class' => 'badge-warning',
                'can_send' => $this->assignments->isNotEmpty(),
                'can_resend' => false
            ];
        }

        $statusConfig = [
            'sent' => ['✅ Inviato', 'badge-success'],
            'partial' => ['⚠️ Parziale', 'badge-warning'],
            'failed' => ['❌ Fallito', 'badge-danger'],
            'pending' => ['⏳ In attesa', 'badge-info']
        ];

        [$text, $class] = $statusConfig[$lastNotification->status] ?? ['❓ Sconosciuto', 'badge-secondary'];

        return [
            'status' => $lastNotification->status,
            'status_text' => $text,
            'class' => $class,
            'last_sent' => $lastNotification->sent_at,
            'recipients_count' => $lastNotification->total_recipients,
            'can_send' => false,
            'can_resend' => $lastNotification->canBeResent()
        ];
    }

    /**
     * 📊 Scope: Tornei con notifiche inviate
     */
    public function scopeNotified($query)
    {
        return $query->whereHas('notifications');
    }

    /**
     * 📊 Scope: Tornei senza notifiche ma con assegnazioni (pronti per notifica)
     */
    public function scopeReadyForNotification($query)
    {
        return $query->whereDoesntHave('notifications')
            ->whereHas('assignments');
    }

    public static function fromYear($year)
    {
        $instance = new static;
        $instance->setTable("tournaments_{$year}");
        return $instance;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Notification extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'assignment_id',
        'recipient_type',
        'recipient_email',
        'subject',
        'body',
        'template_used',
        'status',
        'sent_at',
        'error_message',
        'retry_count',
        'attachments',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'sent_at' => 'datetime',
        'retry_count' => 'integer',
        'attachments' => 'array',
    ];

    /**
     * Notification statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    const STATUSES = [
        self::STATUS_PENDING => 'In attesa',
        self::STATUS_SENT => 'Inviata',
        self::STATUS_FAILED => 'Fallita',
        self::STATUS_CANCELLED => 'Annullata',
    ];

    /**
     * Recipient types
     */
    const TYPE_REFEREE = 'referee';
    const TYPE_club = 'club';
    const TYPE_INSTITUTIONAL = 'institutional';

    const RECIPIENT_TYPES = [
        self::TYPE_REFEREE => 'Arbitro',
        self::TYPE_club => 'Circolo',
        self::TYPE_INSTITUTIONAL => 'Istituzionale',
    ];

    /**
     * Maximum retry attempts
     */
    const MAX_RETRY_ATTEMPTS = 3;

    /**
     * Get the assignment that this notification belongs to.
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /**
     * Scope a query to only include sent notifications.
     */
    public function scopeSent($query)
    {
        return $query->where('status', self::STATUS_SENT);
    }

    /**
     * Scope a query to only include pending notifications.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope a query to only include failed notifications.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope a query to only include notifications for a specific recipient type.
     */
    public function scopeForRecipientType($query, string $type)
    {
        return $query->where('recipient_type', $type);
    }

    /**
     * Scope a query to only include notifications that can be retried.
     */
    public function scopeRetryable($query)
    {
        return $query->where('status', self::STATUS_FAILED)
                    ->where('retry_count', '<', self::MAX_RETRY_ATTEMPTS);
    }

    /**
     * Scope a query to only include recent notifications.
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Mark notification as sent.
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
            'error_message' => null,
        ]);
    }

    /**
     * Mark notification as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'retry_count' => $this->retry_count + 1,
        ]);
    }

    /**
     * Mark notification as cancelled.
     */
    public function markAsCancelled(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
        ]);
    }

    /**
     * Reset notification for retry.
     */
    public function resetForRetry(): void
    {
        $this->update([
            'status' => self::STATUS_PENDING,
            'error_message' => null,
        ]);
    }

    /**
     * Check if notification can be retried.
     */
    public function canBeRetried(): bool
    {
        return $this->status === self::STATUS_FAILED &&
               $this->retry_count < self::MAX_RETRY_ATTEMPTS;
    }

    /**
     * Check if notification has exceeded max retry attempts.
     */
    public function hasExceededMaxRetries(): bool
    {
        return $this->retry_count >= self::MAX_RETRY_ATTEMPTS;
    }

    /**
     * Get recipient type label.
     */
    public function getRecipientTypeLabelAttribute(): string
    {
        return self::RECIPIENT_TYPES[$this->recipient_type] ?? ucfirst($this->recipient_type);
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    /**
     * Get status color for UI.
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_SENT => 'green',
            self::STATUS_PENDING => 'yellow',
            self::STATUS_FAILED => 'red',
            self::STATUS_CANCELLED => 'gray',
            default => 'gray',
        };
    }

    /**
     * Get time since sent/created.
     */
    public function getTimeSinceAttribute(): string
    {
        $date = $this->sent_at ?? $this->created_at;
        return $date->diffForHumans();
    }

    /**
     * Get days since creation.
     */
    public function getDaysSinceCreationAttribute(): int
    {
        return $this->created_at->diffInDays(now());
    }

    /**
     * Check if notification is recent.
     */
    public function isRecent(int $days = 7): bool
    {
        return $this->days_since_creation <= $days;
    }

    /**
     * Get attachment count.
     */
    public function getAttachmentCountAttribute(): int
    {
        return $this->attachments ? count($this->attachments) : 0;
    }

    /**
     * Check if notification has attachments.
     */
    public function hasAttachments(): bool
    {
        return $this->attachment_count > 0;
    }

    /**
     * Get attachment names.
     */
    public function getAttachmentNamesAttribute(): array
    {
        if (!$this->attachments) {
            return [];
        }

        return array_keys($this->attachments);
    }

    /**
     * Get attachment paths.
     */
    public function getAttachmentPathsAttribute(): array
    {
        if (!$this->attachments) {
            return [];
        }

        return array_values($this->attachments);
    }

    /**
     * Get template display name.
     */
    public function getTemplateDisplayNameAttribute(): string
    {
        return $this->template_used ?: 'Template predefinito';
    }

    /**
     * Get notification priority for queue processing.
     */
    public function getPriorityAttribute(): int
    {
        $priority = 0;

        // Higher priority for referees
        if ($this->recipient_type === self::TYPE_REFEREE) {
            $priority += 10;
        }

        // Higher priority for urgent tournaments (within 3 days)
        if ($this->assignment) {
            $daysUntil = now()->diffInDays($this->assignment->tournament->start_date, false);
            if ($daysUntil <= 3) {
                $priority += 20;
            }
        }

        // Higher priority for retries
        if ($this->status === self::STATUS_FAILED) {
            $priority += 5;
        }

        return $priority;
    }

    /**
     * Get summary for logging/display.
     */
    public function getSummaryAttribute(): string
    {
        $summary = "{$this->recipient_type_label} - {$this->recipient_email}";

        if ($this->assignment) {
            $summary .= " - {$this->assignment->tournament->name}";
        }

        return $summary;
    }

    /**
     * Create notification for assignment.
     */
    public static function createForAssignment(Assignment $assignment, array $data): self
    {
        return self::create(array_merge($data, [
            'assignment_id' => $assignment->id,
        ]));
    }

    /**
     * Get failed notifications requiring attention.
     */
    public static function getFailedNotificationsRequiringAttention()
    {
        return self::failed()
            ->where('retry_count', '>=', self::MAX_RETRY_ATTEMPTS)
            ->where('created_at', '>=', now()->subDays(7))
            ->with(['assignment.tournament', 'assignment.user'])
            ->get();
    }

    /**
     * Get notifications statistics.
     */
    public static function getStatistics(int $days = 30): array
    {
        $query = self::where('created_at', '>=', now()->subDays($days));

        return [
            'total' => $query->count(),
            'sent' => $query->where('status', self::STATUS_SENT)->count(),
            'pending' => $query->where('status', self::STATUS_PENDING)->count(),
            'failed' => $query->where('status', self::STATUS_FAILED)->count(),
            'by_type' => $query->selectRaw('recipient_type, COUNT(*) as count')
                              ->groupBy('recipient_type')
                              ->pluck('count', 'recipient_type')
                              ->toArray(),
        ];
    }
}

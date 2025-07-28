<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'assignment_id',
        'recipient_type',
        'recipient_email',
        'subject',
        'body',
        'template_used',
        'status',
        'priority',
        'sent_at',
        'error_message',
        'attachments',
        'retry_count',
        'metadata',
        'attachments'
];

    protected $casts = [
        'sent_at' => 'datetime',
        'attachments' => 'array',
        'metadata' => 'array',
        'retry_count' => 'integer',
    ];

    /**
     * Notification statuses
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Notification priorities
     */
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';

    /**
     * Recipient types
     */
    public const TYPE_REFEREE = 'referee';
    public const TYPE_CLUB = 'club';
    public const TYPE_INSTITUTIONAL = 'institutional';
    public const TYPE_CUSTOM = 'custom';

    /**
     * Relationship with Assignment
     */
    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }

    /**
     * Scope for pending notifications
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for sent notifications
     */
    public function scopeSent($query)
    {
        return $query->where('status', self::STATUS_SENT);
    }

    /**
     * Scope for failed notifications
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope for specific recipient type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('recipient_type', $type);
    }

    /**
     * Scope for specific priority
     */
    public function scopeWithPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Mark notification as sent
     */
    public function markAsSent()
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
            'error_message' => null,
        ]);
    }

    /**
     * Mark notification as failed
     */
    public function markAsFailed(string $errorMessage)
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'retry_count' => $this->retry_count + 1,
        ]);
    }

    /**
     * Reset notification for retry
     */
    public function resetForRetry()
    {
        $this->update([
            'status' => self::STATUS_PENDING,
            'error_message' => null,
        ]);
    }

    /**
     * Check if notification can be retried
     */
    public function canBeRetried(): bool
    {
        return $this->status === self::STATUS_FAILED && $this->retry_count < 3;
    }

    /**
     * Get status badge color
     */
    public function getStatusBadgeColorAttribute()
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'bg-yellow-100 text-yellow-800',
            self::STATUS_SENT => 'bg-green-100 text-green-800',
            self::STATUS_FAILED => 'bg-red-100 text-red-800',
            self::STATUS_CANCELLED => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Get status display name
     */
    public function getStatusDisplayAttribute()
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'In attesa',
            self::STATUS_SENT => 'Inviata',
            self::STATUS_FAILED => 'Fallita',
            self::STATUS_CANCELLED => 'Annullata',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get recipient type display name
     */
    public function getRecipientTypeDisplayAttribute()
    {
        return match ($this->recipient_type) {
            self::TYPE_REFEREE => 'Arbitro',
            self::TYPE_CLUB => 'Circolo',
            self::TYPE_INSTITUTIONAL => 'Istituzionale',
            self::TYPE_CUSTOM => 'Personalizzato',
            default => ucfirst($this->recipient_type),
        };
    }

    /**
     * Get priority badge color
     */
    public function getPriorityBadgeColorAttribute()
    {
        return match ($this->priority) {
            self::PRIORITY_HIGH => 'bg-red-100 text-red-800',
            self::PRIORITY_NORMAL => 'bg-blue-100 text-blue-800',
            self::PRIORITY_LOW => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Get priority display name
     */
    public function getPriorityDisplayAttribute()
    {
        return match ($this->priority) {
            self::PRIORITY_HIGH => 'Alta',
            self::PRIORITY_NORMAL => 'Normale',
            self::PRIORITY_LOW => 'Bassa',
            default => ucfirst($this->priority),
        };
    }

    /**
     * Get formatted sent date
     */
    public function getFormattedSentDateAttribute()
    {
        return $this->sent_at ? $this->sent_at->format('d/m/Y H:i') : '-';
    }


    /**
     * Get tournament information through assignment
     */
    public function getTournamentAttribute()
    {
        return $this->assignment?->tournament;
    }

    /**
     * Get referee information through assignment
     */
    public function getRefereeAttribute()
    {
        return $this->assignment?->user;
    }

    /**
     * Check if notification has attachments
     */
    public function hasAttachments(): bool
    {
        return !empty($this->attachments);
    }

    /**
     * Get attachment count
     */
    public function getAttachmentCount(): int
    {
        return count($this->attachments ?? []);
    }

    /**
     * Add metadata
     */
    public function addMetadata(string $key, $value): void
    {
        $metadata = $this->metadata ?? [];
        $metadata[$key] = $value;
        $this->update(['metadata' => $metadata]);
    }

    /**
     * Get metadata value
     */
    public function getMetadata(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }
}

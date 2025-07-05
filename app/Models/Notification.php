<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'sent_at',
        'status',
        'tracking_info',
        'attachments',
        'error_message',
        'retry_count',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'sent_at' => 'datetime',
        'attachments' => 'array',
        'retry_count' => 'integer',
    ];

    /**
     * Notification statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';

    const STATUSES = [
        self::STATUS_PENDING => 'In attesa',
        self::STATUS_SENT => 'Inviata',
        self::STATUS_FAILED => 'Fallita',
    ];

    /**
     * Recipient types
     */
    const RECIPIENT_REFEREE = 'referee';
    const RECIPIENT_CIRCLE = 'circle';
    const RECIPIENT_INSTITUTIONAL = 'institutional';

    const RECIPIENT_TYPES = [
        self::RECIPIENT_REFEREE => 'Arbitro',
        self::RECIPIENT_CIRCLE => 'Circolo',
        self::RECIPIENT_INSTITUTIONAL => 'Istituzionale',
    ];

    /**
     * Get the assignment that owns the notification.
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /**
     * Scope a query to only include pending notifications.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope a query to only include sent notifications.
     */
    public function scopeSent($query)
    {
        return $query->where('status', self::STATUS_SENT);
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
     * Get the status label
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Get the status color for UI
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'yellow',
            self::STATUS_SENT => 'green',
            self::STATUS_FAILED => 'red',
            default => 'gray',
        };
    }

    /**
     * Get the recipient type label
     */
    public function getRecipientTypeLabelAttribute(): string
    {
        return self::RECIPIENT_TYPES[$this->recipient_type] ?? $this->recipient_type;
    }

    /**
     * Check if notification can be resent
     */
    public function canBeResent(): bool
    {
        return $this->status === self::STATUS_FAILED && $this->retry_count < 3;
    }

    /**
     * Get attachment URLs
     */
    public function getAttachmentUrlsAttribute(): array
    {
        if (!$this->attachments) {
            return [];
        }

        $urls = [];
        foreach ($this->attachments as $type => $path) {
            $urls[$type] = asset('storage/' . $path);
        }

        return $urls;
    }

    /**
     * Mark as sent
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
     * Mark as failed
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
     * Get time since sent
     */
    public function getTimeSinceSentAttribute(): ?string
    {
        if (!$this->sent_at) {
            return null;
        }

        return $this->sent_at->diffForHumans();
    }
}

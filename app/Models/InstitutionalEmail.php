<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstitutionalEmail extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'description',
        'is_active',
        'zone_id',
        'category',
        'receive_all_notifications',
        'notification_types',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'receive_all_notifications' => 'boolean',
        'notification_types' => 'array',
    ];

    /**
     * Categories
     */
    const CATEGORY_FEDERATION = 'federazione';
    const CATEGORY_COMMITTEE = 'comitato';
    const CATEGORY_ZONE = 'zona';
    const CATEGORY_OTHER = 'altro';

    const CATEGORIES = [
        self::CATEGORY_FEDERATION => 'Federazione',
        self::CATEGORY_COMMITTEE => 'Comitato',
        self::CATEGORY_ZONE => 'Zona',
        self::CATEGORY_OTHER => 'Altro',
    ];

    /**
     * Notification types
     */
    const NOTIFICATION_TYPES = [
        'assignment' => 'Assegnazioni',
        'availability' => 'Disponibilità',
        'tournament_created' => 'Nuovi Tornei',
        'tournament_updated' => 'Modifiche Tornei',
        'referee_registered' => 'Nuovi Arbitri',
        'reports' => 'Report',
    ];

    /**
     * Get the zone that the institutional email belongs to.
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * Scope a query to only include active emails.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include emails for a specific zone.
     */
    public function scopeForZone($query, $zoneId)
    {
        return $query->where(function ($q) use ($zoneId) {
            $q->whereNull('zone_id')
              ->orWhere('zone_id', $zoneId);
        });
    }

    /**
     * Scope a query to only include emails that should receive a specific notification type.
     */
    public function scopeForNotificationType($query, string $type)
    {
        return $query->where(function ($q) use ($type) {
            $q->where('receive_all_notifications', true)
              ->orWhereJsonContains('notification_types', $type);
        });
    }

    /**
     * Get the category label
     */
    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    /**
     * Check if should receive notification type
     */
    public function shouldReceiveNotificationType(string $type): bool
    {
        if ($this->receive_all_notifications) {
            return true;
        }

        return in_array($type, $this->notification_types ?? []);
    }

    /**
     * Get notification types labels
     */
    public function getNotificationTypesLabelsAttribute(): array
    {
        if ($this->receive_all_notifications) {
            return ['Tutte le notifiche'];
        }

        $labels = [];
        foreach ($this->notification_types ?? [] as $type) {
            $labels[] = self::NOTIFICATION_TYPES[$type] ?? $type;
        }

        return $labels;
    }

    /**
     * Get display name with category
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name . ' (' . $this->category_label . ')';
    }

    /**
     * Get zone display
     */
    public function getZoneDisplayAttribute(): string
    {
        return $this->zone ? $this->zone->name : 'Tutte le zone';
    }


}

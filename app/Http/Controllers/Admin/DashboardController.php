<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Letterhead extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'zone_id',
        'title',
        'logo_path',
        'header_text',
        'header_content',
        'footer_text',
        'footer_content',
        'contact_info',
        'is_active',
        'is_default',
        'settings',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'contact_info' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'settings' => 'array',
    ];

    /**
     * Default settings structure
     */
    protected $attributes = [
        'settings' => '{}',
        'contact_info' => '{}',
        'is_active' => true,
        'is_default' => false,
    ];

    /**
     * Get the zone that owns the letterhead.
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * Scope a query to only include active letterheads.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include default letterheads.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope a query to get letterhead for a specific zone.
     */
    public function scopeForZone($query, $zoneId)
    {
        return $query->where(function ($q) use ($zoneId) {
            $q->where('zone_id', $zoneId)
              ->orWhereNull('zone_id');
        });
    }

    /**
     * Get the logo URL
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo_path) {
            return null;
        }

        return Storage::url($this->logo_path);
    }

    /**
     * Get formatted contact info
     */
    public function getFormattedContactInfoAttribute(): string
    {
        $info = $this->contact_info ?? [];
        $parts = [];

        if (!empty($info['address'])) {
            $parts[] = $info['address'];
        }

        if (!empty($info['phone'])) {
            $parts[] = "Tel: " . $info['phone'];
        }

        if (!empty($info['email'])) {
            $parts[] = "Email: " . $info['email'];
        }

        if (!empty($info['website'])) {
            $parts[] = "Web: " . $info['website'];
        }

        return implode(' | ', $parts);
    }

    /**
     * Get scope display
     */
    public function getScopeDisplayAttribute(): string
    {
        return $this->zone ? $this->zone->name : 'Globale';
    }

    /**
     * Set as default for zone
     */
    public function setAsDefault(): void
    {
        // Remove default from other letterheads in same zone
        self::where('zone_id', $this->zone_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        // Set this as default
        $this->update(['is_default' => true]);
    }

    /**
     * Get margin settings
     */
    public function getMarginSettingsAttribute(): array
    {
        return $this->settings['margins'] ?? [
            'top' => 20,
            'bottom' => 20,
            'left' => 25,
            'right' => 25,
        ];
    }

    /**
     * Get font settings
     */
    public function getFontSettingsAttribute(): array
    {
        return $this->settings['font'] ?? [
            'family' => 'Arial',
            'size' => 11,
            'color' => '#000000',
        ];
    }

    /**
     * Clone letterhead
     */
    public function cloneLetterhead(array $overrides = []): self
    {
        $clone = $this->replicate();
        $clone->title = $this->title . ' (Copia)';
        $clone->is_default = false;
        $clone->is_active = false;

        foreach ($overrides as $key => $value) {
            $clone->$key = $value;
        }

        // Copy logo if exists
        if ($this->logo_path && Storage::exists($this->logo_path)) {
            $extension = pathinfo($this->logo_path, PATHINFO_EXTENSION);
            $newPath = 'letterheads/logo_' . uniqid() . '.' . $extension;
            Storage::copy($this->logo_path, $newPath);
            $clone->logo_path = $newPath;
        }

        $clone->save();

        return $clone;
    }

    /**
     * Delete logo file when deleting letterhead
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($letterhead) {
            if ($letterhead->logo_path && Storage::exists($letterhead->logo_path)) {
                Storage::delete($letterhead->logo_path);
            }
        });
    }
}

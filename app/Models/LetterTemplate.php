<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LetterTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'subject',
        'body',
        'zone_id',
        'tournament_type_id',
        'is_active',
        'is_default',
        'variables',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'variables' => 'array',
    ];

    /**
     * Relationship with Zone
     */
    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * Relationship with TournamentType
     */
    public function tournamentType()
    {
        return $this->belongsTo(TournamentType::class);
    }

    /**
     * Scope for active templates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for default templates
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope for specific type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for specific zone
     */
    public function scopeForZone($query, $zoneId = null)
    {
        if ($zoneId) {
            return $query->where(function ($q) use ($zoneId) {
                $q->where('zone_id', $zoneId)
                  ->orWhereNull('zone_id');
            });
        }

        return $query->whereNull('zone_id');
    }

    /**
     * Scope for specific tournament type
     */
    public function scopeForTournamentType($query, $tournamentTypeId = null)
    {
        if ($tournamentTypeId) {
            return $query->where(function ($q) use ($tournamentTypeId) {
                $q->where('tournament_type_id', $tournamentTypeId)
                  ->orWhereNull('tournament_type_id');
            });
        }

        return $query->whereNull('tournament_type_id');
    }

    /**
     * Get template type badge color
     */
    public function getTypeBadgeColorAttribute()
    {
        return match ($this->type) {
            'assignment' => 'bg-blue-100 text-blue-800',
            'convocation' => 'bg-green-100 text-green-800',
            'club' => 'bg-yellow-100 text-yellow-800',
            'institutional' => 'bg-purple-100 text-purple-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Get template type display name
     */
    public function getTypeDisplayAttribute()
    {
        return match ($this->type) {
            'assignment' => 'Assegnazione',
            'convocation' => 'Convocazione',
            'club' => 'Circolo',
            'institutional' => 'Istituzionale',
            default => ucfirst($this->type),
        };
    }

    /**
     * Get template type label (alias for compatibility)
     */
    public function getTypeLabelAttribute()
    {
        return $this->type_display;
    }

    /**
     * Get template status label
     */
    public function getStatusLabelAttribute()
    {
        return $this->is_active ? 'Attivo' : 'Inattivo';
    }

    /**
     * Get template scope label
     */
    public function getScopeLabelAttribute()
    {
        if ($this->zone_id && $this->tournament_type_id) {
            return 'Specifico';
        } elseif ($this->zone_id) {
            return 'Zonale';
        } elseif ($this->tournament_type_id) {
            return 'Per Tipo Torneo';
        } else {
            return 'Globale';
        }
    }

    /**
     * Extract variables from template content
     */
    public function getUsedVariablesAttribute()
    {
        $text = $this->subject . ' ' . $this->body;
        preg_match_all('/\{\{([^}]+)\}\}/', $text, $matches);

        $variables = [];
        foreach (array_unique($matches[1]) as $variable) {
            $variables[trim($variable)] = true;
        }

        return $variables;
    }

    /**
     * Replace variables in text
     */
    public function replaceVariables(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace('{{' . $key . '}}', $value, $text);
        }
        return $text;
    }

    /**
     * Get processed subject with variables
     */
    public function getProcessedSubject(array $variables = []): string
    {
        return $this->replaceVariables($this->subject, $variables);
    }

    /**
     * Get processed body with variables
     */
    public function getProcessedBody(array $variables = []): string
    {
        return $this->replaceVariables($this->body, $variables);
    }

    /**
     * Check if template can be used for given context
     */
    public function canBeUsedFor(?int $zoneId = null, ?int $tournamentTypeId = null): bool
    {
        // Check if template is active
        if (!$this->is_active) {
            return false;
        }

        // Check zone compatibility
        if ($this->zone_id && $this->zone_id !== $zoneId) {
            return false;
        }

        // Check tournament type compatibility
        if ($this->tournament_type_id && $this->tournament_type_id !== $tournamentTypeId) {
            return false;
        }

        return true;
    }

    /**
     * Get the best template for given criteria
     */
    public static function getBestTemplate(
        string $type,
        ?int $zoneId = null,
        ?int $tournamentTypeId = null
    ): ?self {
        return static::active()
            ->ofType($type)
            ->forZone($zoneId)
            ->forTournamentType($tournamentTypeId)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }
}

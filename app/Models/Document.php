<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * 📁 Document Model - Gestione documenti
 */
class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'original_name',
        'file_path',
        'file_size',
        'mime_type',
        'category',
        'type',
        'description',
        'tournament_id',
        'zone_id',
        'uploader_id',
        'is_public',
        'download_count',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'file_size' => 'integer',
        'download_count' => 'integer',
    ];

    // Document categories
    const CATEGORY_GENERAL = 'general';
    const CATEGORY_TOURNAMENT = 'tournament';
    const CATEGORY_REGULATION = 'regulation';
    const CATEGORY_FORM = 'form';
    const CATEGORY_TEMPLATE = 'template';

    const CATEGORIES = [
        self::CATEGORY_GENERAL => 'Generale',
        self::CATEGORY_TOURNAMENT => 'Torneo',
        self::CATEGORY_REGULATION => 'Regolamento',
        self::CATEGORY_FORM => 'Modulo',
        self::CATEGORY_TEMPLATE => 'Template',
    ];

    // Document types
    const TYPE_PDF = 'pdf';
    const TYPE_DOCUMENT = 'document';
    const TYPE_SPREADSHEET = 'spreadsheet';
    const TYPE_IMAGE = 'image';
    const TYPE_TEXT = 'text';
    const TYPE_OTHER = 'other';

    const TYPES = [
        self::TYPE_PDF => 'PDF',
        self::TYPE_DOCUMENT => 'Documento',
        self::TYPE_SPREADSHEET => 'Foglio di calcolo',
        self::TYPE_IMAGE => 'Immagine',
        self::TYPE_TEXT => 'Testo',
        self::TYPE_OTHER => 'Altro',
    ];

    /**
     * Get the uploader (user) who uploaded this document
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    /**
     * Get the tournament this document belongs to
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * Get the zone this document belongs to
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * Get file size in human readable format
     */
    public function getFileSizeHumanAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get file URL
     */
    public function getFileUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }

    /**
     * Get download URL
     */
    public function getDownloadUrlAttribute(): string
    {
        return route('documents.download', $this);
    }

    /**
     * Get type icon
     */
    public function getTypeIconAttribute(): string
    {
        return match($this->type) {
            self::TYPE_PDF => 'fas fa-file-pdf text-red-500',
            self::TYPE_DOCUMENT => 'fas fa-file-word text-blue-500',
            self::TYPE_SPREADSHEET => 'fas fa-file-excel text-green-500',
            self::TYPE_IMAGE => 'fas fa-file-image text-purple-500',
            self::TYPE_TEXT => 'fas fa-file-alt text-gray-500',
            default => 'fas fa-file text-gray-400',
        };
    }

    /**
     * Scope for public documents
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope for documents in a specific category
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}

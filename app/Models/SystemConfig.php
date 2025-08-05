<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemConfig newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemConfig newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemConfig query()
 * @mixin \Eloquent
 */
class SystemConfig extends Model
{
    protected $table = 'system_configs';

    protected $fillable = [
        'category',
        'key',
        'value',
        'description',
        'type',
        'is_public',
        'is_editable',
        'validation_rules',
        'created_at',
        'updated_at',
    ];

    public $timestamps = true;
}

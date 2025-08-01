<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

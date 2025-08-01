<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
        'group',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'value' => 'string',
    ];

    /**
     * Boot method to clear cache when settings change
     */
    protected static function boot()
    {
        parent::boot();

        static::saved(function () {
            Cache::forget('system_settings');
        });

        static::deleted(function () {
            Cache::forget('system_settings');
        });
    }

    /**
     * Get a setting value by key
     */
    public static function get($key, $default = null)
    {
        $settings = Cache::remember('system_settings', 3600, function () {
            return static::pluck('value', 'key')->toArray();
        });

        return $settings[$key] ?? $default;
    }

    /**
     * Set a setting value
     */
    public static function set($key, $value, $type = 'string', $description = null, $group = 'general')
    {
        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'type' => $type,
                'description' => $description,
                'group' => $group,
            ]
        );
    }

    /**
     * Get settings by group
     */
    public static function getByGroup($group)
    {
        return static::where('group', $group)->pluck('value', 'key')->toArray();
    }

    /**
     * Get all settings grouped
     */
    public static function getAllGrouped()
    {
        return static::all()->groupBy('group')->map(function ($settings) {
            return $settings->pluck('value', 'key');
        });
    }

    /**
     * Check if setting exists
     */
    public static function has($key)
    {
        return static::where('key', $key)->exists();
    }

    /**
     * Remove a setting
     */
    public static function remove($key)
    {
        return static::where('key', $key)->delete();
    }

    /**
     * Get typed value
     */
    public function getTypedValue()
    {
        switch ($this->type) {
            case 'boolean':
                return filter_var($this->value, FILTER_VALIDATE_BOOLEAN);
            case 'integer':
                return (int) $this->value;
            case 'float':
                return (float) $this->value;
            case 'array':
                return json_decode($this->value, true) ?: [];
            case 'json':
                return json_decode($this->value) ?: new \stdClass();
            default:
                return $this->value;
        }
    }

    /**
     * Set typed value
     */
    public function setTypedValue($value)
    {
        switch ($this->type) {
            case 'boolean':
                $this->value = $value ? '1' : '0';
                break;
            case 'array':
            case 'json':
                $this->value = json_encode($value);
                break;
            default:
                $this->value = (string) $value;
        }
    }
}

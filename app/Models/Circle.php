<?php

namespace App\Models;

/**
 * Circle Model - Alias for Club model to maintain backward compatibility
 *
 * This model acts as an alias for the Club model since the terminology
 * changed from "circles" to "clubs" for golf course management.
 */
class Circle extends Club
{
    /**
     * The table associated with the model.
     * Points to clubs table since we standardized on that name.
     *
     * @var string
     */
    protected $table = 'clubs';

    /**
     * Get the tournaments hosted by the circle.
     */
    public function tournaments()
    {
        return $this->hasMany(Tournament::class, 'circle_id');
    }

    /**
     * Create a new Circle instance (which is actually a Club)
     */
    public static function create(array $attributes = [])
    {
        return parent::create($attributes);
    }

    /**
     * Get all circles (alias for clubs)
     */
    public static function all($columns = ['*'])
    {
        return parent::all($columns);
    }

    /**
     * Find a circle by ID (alias for club)
     */
    public static function find($id, $columns = ['*'])
    {
        return parent::find($id, $columns);
    }

    /**
     * Alias method for backward compatibility
     */
    public function getCircleNameAttribute()
    {
        return $this->name;
    }

    /**
     * Alias method for backward compatibility
     */
    public function getCircleEmailAttribute()
    {
        return $this->best_email;
    }

    /**
     * Alias method for backward compatibility
     */
    public function getCirclePhoneAttribute()
    {
        return $this->best_phone;
    }

    /**
     * Alias method for backward compatibility
     */
    public function getCircleAddressAttribute()
    {
        return $this->full_address;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Get a setting value by key
     */
    public static function get($key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Set a setting value by key
     */
    public static function set($key, $value)
    {
        return self::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    /**
     * Get all settings as key-value array
     */
    public static function getAll()
    {
        return self::pluck('value', 'key')->toArray();
    }

    /**
     * Get commission configuration
     */
    public static function getCommissionConfig()
    {
        return [
            'slab_commissions' => [
                'Bronze' => (float)self::get('slab_commission_bronze', 1.0),
                'Silver' => (float)self::get('slab_commission_silver', 1.5),
                'Gold' => (float)self::get('slab_commission_gold', 2.0),
                'Diamond' => (float)self::get('slab_commission_diamond', 3.0),
            ],
        ];
    }
}

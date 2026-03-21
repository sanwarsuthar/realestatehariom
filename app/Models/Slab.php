<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Slab extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'minimum_target',
        'maximum_target',
        'commission_ratio',
        'bonus_percentage',
        'description',
        'color_code',
        'measurement_unit_id',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'minimum_target' => 'decimal:2',
        'maximum_target' => 'decimal:2',
        'commission_ratio' => 'decimal:2',
        'bonus_percentage' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function measurementUnit()
    {
        return $this->belongsTo(MeasurementUnit::class);
    }

    public function propertyTypes()
    {
        return $this->belongsToMany(PropertyType::class, 'property_type_slab');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'location',
        'city',
        'state',
        'pincode',
        'type',
        'commission_per_slab',
        'commission_config',
        'minimum_booking_amount',
        'allocated_amount',
        'allocated_amount_config',
        'price_per_sqft',
        'plot_size',
        'facilities',
        'images',
        'videos',
        'floor_plan_pdf',
        'status',
        'latitude',
        'longitude',
        'is_active',
    ];

    protected $casts = [
        'facilities' => 'array',
        'images' => 'array',
        'videos' => 'array',
        'commission_per_slab' => 'array',
        'commission_config' => 'array',
        'allocated_amount_config' => 'array',
        'minimum_booking_amount' => 'decimal:2',
        'allocated_amount' => 'decimal:2',
        'price_per_sqft' => 'decimal:2',
        'plot_size' => 'decimal:2',
        'is_active' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function plots()
    {
        return $this->hasMany(Plot::class);
    }

    public function sales()
    {
        return $this->hasManyThrough(Sale::class, Plot::class);
    }
}

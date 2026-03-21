<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plot extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'grid_batch_id',
        'grid_batch_name',
        'plot_number',
        'type',
        'size',
        'price_per_unit',
        'minimum_booking_amount',
        'status',
        'amenities',
        'images',
        'description',
        'bedrooms',
        'bathrooms',
        'carpet_area',
        'is_active',
    ];

    protected $casts = [
        'amenities' => 'array',
        'images' => 'array',
        'is_active' => 'boolean',
        'size' => 'decimal:2',
        'price_per_unit' => 'decimal:2',
        'minimum_booking_amount' => 'decimal:2',
        'carpet_area' => 'decimal:2',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeasurementUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'symbol',
        'description',
    ];

    public function slabs()
    {
        return $this->hasMany(Slab::class);
    }

    public function propertyTypes()
    {
        return $this->hasMany(PropertyType::class);
    }
}


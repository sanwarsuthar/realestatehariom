<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSlab extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'property_type_id',
        'slab_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function propertyType()
    {
        return $this->belongsTo(PropertyType::class);
    }

    public function slab()
    {
        return $this->belongsTo(Slab::class);
    }
}

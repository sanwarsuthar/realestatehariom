<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SlabUpgrade extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'old_slab_id',
        'new_slab_id',
        'sale_id',
        'total_area_sold',
        'notes',
        'upgraded_at',
    ];

    protected $casts = [
        'total_area_sold' => 'decimal:2',
        'upgraded_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function oldSlab()
    {
        return $this->belongsTo(Slab::class, 'old_slab_id');
    }

    public function newSlab()
    {
        return $this->belongsTo(Slab::class, 'new_slab_id');
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}

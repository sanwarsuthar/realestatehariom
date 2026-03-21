<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralCommission extends Model
{
    protected $fillable = [
        'sale_id',
        'parent_user_id',
        'child_user_id',
        'parent_slab_name',
        'child_slab_name',
        'parent_slab_percentage',
        'child_slab_percentage',
        'referral_commission_amount',
        'allocated_amount',
        'area_sold',
        'level',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'parent_slab_percentage' => 'decimal:2',
            'child_slab_percentage' => 'decimal:2',
            'referral_commission_amount' => 'decimal:2',
            'allocated_amount' => 'decimal:2',
            'area_sold' => 'decimal:2',
            'level' => 'integer',
        ];
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function parentUser()
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function childUser()
    {
        return $this->belongsTo(User::class, 'child_user_id');
    }
}

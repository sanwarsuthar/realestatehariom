<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleCommissionRelease extends Model
{
    protected $fillable = [
        'sale_id',
        'user_id',
        'total_commission',
        'released_amount',
    ];

    protected $casts = [
        'total_commission' => 'decimal:2',
        'released_amount' => 'decimal:2',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

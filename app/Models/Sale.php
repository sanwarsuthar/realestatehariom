<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'plot_id',
        'sold_by_user_id',
        'customer_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'sale_price',
        'total_sale_value',  // Total plot value (for proportional commission release)
        'booking_amount',
        'commission_amount',
        'status',
        'notes',
        'commission_distribution',
        'sale_date',
        'deal_status',   // pending | done | failed
        'deal_done_at',
        'deal_failed_at',
    ];

    protected $casts = [
        'commission_distribution' => 'array',
        'sale_price' => 'decimal:2',
        'total_sale_value' => 'decimal:2',
        'booking_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'sale_date' => 'datetime',
        'deal_done_at' => 'datetime',
        'deal_failed_at' => 'datetime',
    ];

    public function plot()
    {
        return $this->belongsTo(Plot::class);
    }

    public function soldByUser()
    {
        return $this->belongsTo(User::class, 'sold_by_user_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function paymentRequests()
    {
        return $this->hasMany(PaymentRequest::class);
    }

    public function commissionReleases()
    {
        return $this->hasMany(SaleCommissionRelease::class);
    }
}

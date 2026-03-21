<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'transaction_id',
        'type',
        'status',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'metadata',
        'reference_id',
        'processed_by',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'metadata' => 'array',
        'processed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Get transaction direction (in/out) based on type
     */
    public function getDirectionAttribute()
    {
        // Incoming: deposit, commission, bonus
        if (in_array($this->type, ['deposit', 'commission', 'bonus'])) {
            return 'in';
        }
        // Outgoing: withdrawal, booking, refund (e.g. deal_failed_reversal)
        if (in_array($this->type, ['withdrawal', 'booking', 'refund'])) {
            return 'out';
        }
        // Default to 'in' for unknown types
        return 'in';
    }
}

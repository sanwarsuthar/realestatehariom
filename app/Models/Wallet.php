<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'balance',              // Gross wallet: released commission (from payments received)
        'main_balance',         // Main wallet: projected commission from approved deals
        'withdrawable_balance', // Withdrawable: only from deals marked as "deal done"
        'total_earned',
        'total_withdrawn',
        'total_deposited',
        'is_active',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'main_balance' => 'decimal:2',
        'withdrawable_balance' => 'decimal:2',
        'total_earned' => 'decimal:2',
        'total_withdrawn' => 'decimal:2',
        'total_deposited' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

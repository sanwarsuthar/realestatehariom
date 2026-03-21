<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'details',
        'ifsc_code',
        'account_number',
        'upi_ids',
        'account_type',
        'scanner_photo',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'details' => 'array', // Store as JSON
        'upi_ids' => 'array', // Array of UPI IDs
    ];

    public function paymentRequests()
    {
        return $this->hasMany(PaymentRequest::class);
    }
}

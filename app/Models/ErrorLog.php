<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ErrorLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'error_type',
        'message',
        'file',
        'line',
        'url',
        'method',
        'user_id',
        'ip_address',
        'user_agent',
        'request_data',
        'resolved',
        'resolution_notes',
    ];

    protected $casts = [
        'request_data' => 'array',
        'resolved' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

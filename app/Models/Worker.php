<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Worker extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'image',
        'age',

        'service_type',
        'expertise_of_service',
        'shift',

        'feedback',
        'is_active',
        'address'
    ];
    protected $casts = [
        'service_type' => 'array',
        'rating' => 'decimal:2',
        'is_active' => 'boolean',
        'expertise_of_service' => 'array'
    ];
}

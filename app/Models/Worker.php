<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Worker extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
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
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}

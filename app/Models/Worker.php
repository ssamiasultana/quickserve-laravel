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
        'expertise_of_service',
        'shift',
        'feedback',
        'is_active',
        'address'
    ];
    protected $casts = [
        'rating' => 'decimal:2',
        'is_active' => 'boolean',
        'expertise_of_service' => 'array'
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function services()
    {
        return $this->belongsToMany(Services::class, 'service_worker', 'worker_id', 'service_id')
                    ->withTimestamps();
    }
    
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}

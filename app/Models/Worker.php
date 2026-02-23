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
        'address',
        'nid',                  
        'nid_verified',         
        'nid_verified_at',      
        'nid_front_image',     
        'nid_back_image', 
    ];
    protected $casts = [
        'rating' => 'decimal:2',
        'is_active' => 'boolean',
        'expertise_of_service' => 'array',
        'nid_verified' => 'boolean',        
        'nid_verified_at' => 'datetime', 
    ];

    /**
     * The accessors to append to the model's array and JSON forms.
     *
     * This ensures customer rating statistics are always available
     * in API responses (e.g., average_rating, total_reviews).
     */
    protected $appends = [
        'average_rating',
        'total_reviews',
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

    /**
     * Get all reviews for this worker.
     */
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Get the average rating for this worker from customer reviews.
     */
    public function getAverageRatingAttribute()
    {
        $avgRating = $this->reviews()->avg('rating');
        return $avgRating ? round($avgRating, 2) : 0;
    }

    /**
     * Get the total number of reviews for this worker.
     */
    public function getTotalReviewsAttribute()
    {
        return $this->reviews()->count();
    }
    
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}

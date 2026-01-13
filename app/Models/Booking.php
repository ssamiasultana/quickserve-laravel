<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class Booking extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * Explicitly set because the migration currently creates a `booking` table.
     */
    protected $table = 'booking';

    protected $fillable = [
        'customer_id',
        'worker_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'service_address',
        'special_instructions',
        'service_id',
        'service_subcategory_id',
        'quantity',
        'unit_price',
        'subtotal_amount',
        'shift_type',
        'shift_charge_percent',
        'total_amount',
        'status',
        'scheduled_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'unit_price' => 'decimal:2',
        'subtotal_amount' => 'decimal:2',
        'shift_charge_percent' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'quantity' => 'integer',
    ];
    protected $attributes = [
        'shift_type' => 'day',
        'shift_charge_percent' => 0,
        'status' => 'pending',
        'quantity' => 1,
    ];
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Services::class, 'service_id');
    }

    public function serviceCategory(): BelongsTo
    {
        return $this->service();
    }

    public function serviceSubcategory(): BelongsTo
    {
        return $this->belongsTo(ServiceSubcategory::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class, 'worker_id');
    }
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isCancelled(): bool{
        return $this->status === 'cancelled';
    }
}

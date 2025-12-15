<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceSubcategory extends Model
{
    use HasFactory;
    protected $table = 'service_subcategories';

    protected $fillable = [
        'service_id',
        'name',
        'base_price',
        'unit_type',
        
    ];

    public function category()
    {
        return $this->belongsTo(Services::class, 'service_id');
    }
}

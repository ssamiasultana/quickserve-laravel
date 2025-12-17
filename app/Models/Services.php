<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Services extends Model
{
    use HasFactory;
    protected $table = 'services';
    protected $fillable =[
        'name',
    ];
    public function subcategories()
    {
        return $this->hasMany(ServiceSubcategory::class, 'service_id');
    }
    public function workers()
    {
        return $this->belongsToMany(Worker::class, 'service_worker', 'service_id', 'worker_id')
                    ->withTimestamps();
    }
}

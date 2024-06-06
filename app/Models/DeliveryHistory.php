<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DeliveryHistory extends Model
{
    protected $casts = [
        'order_id' => 'integer',
        'deliveryman_id' => 'integer',
        'time' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function delivery_man()
    {
        return $this->belongsTo(DeliveryMan::class, 'delivery_man_id');
    }

    public function scopeWithinDistance($query, $latitude, $longitude, $distance)
    {
        return $query->select(DB::raw("delivery_men.*, ST_Distance_Sphere(point(longitude, latitude), point($longitude, $latitude)) AS distance"))
            ->having('distance', '<=', $distance * 1000);
    }
}

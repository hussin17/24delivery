<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CityWise extends Model
{
    use HasFactory;

    protected $table = 'store_city_wise';

    /**
     * Class Category
     *
     * @property int $store_id
     * @property string $city_name
     *
     * @package App\Models
     */

    protected $fillable = [
        'store_id',
        'city'
    ];

    protected $casts = [
        'store_id' => 'integer',
        'city' => 'string'
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id', 'id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}

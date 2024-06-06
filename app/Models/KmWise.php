<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KmWise extends Model
{
    use HasFactory;

    protected $table = 'store_km_wise';

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
        'from',
        'to',
        'price'
    ];

    protected $casts = [
        'store_id' => 'integer',
        'from' => 'string',
        'to' => 'string',
        'price' => 'integer'
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

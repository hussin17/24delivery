<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Block extends Model
{
    use HasFactory;

    public function zone()
    {
        return $this->belongsTo(Zone::class, 'block_id', 'id');
    }
}

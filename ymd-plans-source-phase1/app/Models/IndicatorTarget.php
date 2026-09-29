<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IndicatorTarget extends Model
{
    protected $fillable = ['indicator_id', 'quarter', 'target'];

    protected $casts = ['target' => 'float'];
}

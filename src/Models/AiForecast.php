<?php

namespace Spiggle\VisualDashboards\Models;

use Illuminate\Database\Eloquent\Model;

class AiForecast extends Model
{
    protected $table = 'dashlet_ai_forecasts';

    protected $fillable = [
        'year',
        'month',
        'projected_spend',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'projected_spend' => 'float',
    ];
}

<?php

namespace Spiggle\VisualDashboards\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAnomaly extends Model
{
    protected $table = 'dashlet_ai_anomalies';

    protected $fillable = [
        'service_id',
        'detected_on',
        'occurred_on',
        'amount_usd',
        'amount',
        'title',
        'note',
        'form_submission_id',
    ];

    protected $casts = [
        'detected_on' => 'date',
        'occurred_on' => 'date',
        'amount_usd' => 'decimal:2',
        'amount' => 'float',
        'form_submission_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $row): void {
            if (blank($row->detected_on) && filled($row->occurred_on)) {
                $row->detected_on = $row->occurred_on;
            }
            if (blank($row->amount_usd) && filled($row->amount)) {
                $row->amount_usd = $row->amount;
            }
        });
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(AiService::class, 'service_id');
    }
}

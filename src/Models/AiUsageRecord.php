<?php

namespace Spiggle\VisualDashboards\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageRecord extends Model
{
    protected $table = 'dashlet_ai_usage_reports';

    protected $fillable = [
        'service_id',
        'reported_on',
        'usage_date',
        'tokens',
        'spend',
        'spend_usd',
        'form_submission_id',
    ];

    protected $casts = [
        'reported_on' => 'date',
        'usage_date' => 'date',
        'tokens' => 'integer',
        'spend' => 'float',
        'spend_usd' => 'decimal:2',
        'form_submission_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $record): void {
            if (blank($record->reported_on) && filled($record->usage_date)) {
                $record->reported_on = $record->usage_date;
            }
            if (blank($record->spend_usd) && filled($record->spend)) {
                $record->spend_usd = $record->spend;
            }
        });
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(AiService::class, 'service_id');
    }
}

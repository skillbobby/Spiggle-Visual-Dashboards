<?php

namespace Spiggle\VisualDashboards\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageReport extends Model
{
    protected $table = 'dashlet_ai_usage_reports';

    protected $fillable = [
        'service_id',
        'reported_on',
        'tokens',
        'spend_usd',
        'form_submission_id',
    ];

    protected $casts = [
        'reported_on' => 'date',
        'tokens' => 'integer',
        'spend_usd' => 'decimal:2',
        'form_submission_id' => 'integer',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(AiService::class, 'service_id');
    }
}

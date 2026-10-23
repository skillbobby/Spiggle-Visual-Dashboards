<?php

namespace Spiggle\VisualDashboards\Models;

use Illuminate\Database\Eloquent\Model;

class AiInsight extends Model
{
    protected $table = 'dashlet_ai_insights';

    protected $fillable = [
        'kind',
        'title',
        'body',
        'action_label',
        'savings_label',
        'sort_order',
        'is_active',
        'form_submission_id',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'form_submission_id' => 'integer',
    ];
}

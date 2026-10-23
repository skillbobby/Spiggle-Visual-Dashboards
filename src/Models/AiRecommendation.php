<?php

namespace Spiggle\VisualDashboards\Models;

use Illuminate\Database\Eloquent\Model;

class AiRecommendation extends Model
{
    protected $table = 'dashlet_ai_insights';

    protected $fillable = [
        'kind',
        'title',
        'body',
        'action_label',
        'savings_label',
        'impact_label',
        'sort_order',
        'is_active',
        'form_submission_id',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'form_submission_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $row): void {
            if (blank($row->savings_label) && filled($row->impact_label)) {
                $row->savings_label = $row->impact_label;
            }
        });
    }
}

<?php

namespace Spiggle\VisualDashboards\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class AiSpendDashboard
{
    public static function tablesReady(): bool
    {
        return Schema::hasTable('dashlet_ai_services')
            && Schema::hasTable('dashlet_ai_usage_reports');
    }

    /**
     * @return array<string, mixed>
     */
    public static function payload(?Carbon $month = null): array
    {
        return app(AiSpendAggregator::class)->snapshot($month, auth()->user());
    }
}

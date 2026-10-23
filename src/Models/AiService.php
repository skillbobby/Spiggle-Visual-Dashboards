<?php

namespace Spiggle\VisualDashboards\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiService extends Model
{
    protected $table = 'dashlet_ai_services';

    protected $fillable = [
        'name',
        'slug',
        'vendor_key',
        'color',
        'cost_per_million',
        'initials',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'cost_per_million' => 'float',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $service): void {
            if (blank($service->initials) && filled($service->name)) {
                $service->initials = strtoupper(substr((string) $service->name, 0, 2));
            }
        });
    }

    public function usageReports(): HasMany
    {
        return $this->hasMany(AiUsageReport::class, 'service_id');
    }

    public function anomalies(): HasMany
    {
        return $this->hasMany(AiAnomaly::class, 'service_id');
    }
}

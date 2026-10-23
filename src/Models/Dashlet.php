<?php

namespace Spiggle\VisualDashboards\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spiggle\VisualDashboards\Database\Factories\DashletFactory;

class Dashlet extends Model
{
    use HasFactory;

    protected $fillable = [
        'custom_dashboard_id',
        'type',
        'grid_x',
        'grid_y',
        'grid_w',
        'grid_h',
        'config',
    ];

    protected $casts = [
        'grid_x' => 'integer',
        'grid_y' => 'integer',
        'grid_w' => 'integer',
        'grid_h' => 'integer',
        'config' => 'array',
    ];

    public function getTable(): string
    {
        return config('visual-dashboards.tables.dashlets', 'dashlets');
    }

    protected static function newFactory(): DashletFactory
    {
        return DashletFactory::new();
    }

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(CustomDashboard::class, 'custom_dashboard_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizedConfig(): array
    {
        $config = array_merge([
            'title' => 'Dashlet',
            'heading' => '',
            'subheading' => '',
            'source_kind' => 'model',
            'data_source' => 'sample_metrics',
            'table_name' => '',
            'api' => [],
            'file' => [],
            'filters' => [],
            'window' => '',
            'filter_groups' => [],
            'join' => [],
            'columns' => [],
            'group_by' => [],
            'aggregations' => [],
            'calculated_fields' => [],
            'mapping' => [],
            'access_roles' => [],
            'access_permissions' => [],
            'visual' => [
                'color' => '#f59e0b',
                'color_scheme' => 'amber',
                'border' => true,
                'number_format' => 'number',
                'currency' => 'USD',
                'x_label' => '',
                'y_label' => '',
                'chart_engine' => 'apexcharts',
            ],
            'children' => [],
        ], $this->config ?? []);

        if (blank($config['heading']) && filled($config['title'])) {
            $config['heading'] = $config['title'];
        }

        if (blank($config['title']) && filled($config['heading'])) {
            $config['title'] = $config['heading'];
        }

        return $config;
    }
}

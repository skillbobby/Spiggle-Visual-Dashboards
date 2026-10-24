<?php

use Spiggle\VisualDashboards\DataSources\AiSpendDataSource;
use Spiggle\VisualDashboards\DataSources\LoginActivityDataSource;
use Spiggle\VisualDashboards\DataSources\SampleMetricsDataSource;
use Spiggle\VisualDashboards\DataSources\UserMetricsDataSource;

return [
    'tables' => [
        'dashboards' => 'custom_dashboards',
        'dashlets' => 'dashlets',
        'login_events' => 'dashlet_login_events',
        'ai_services' => 'dashlet_ai_services',
        'ai_usage' => 'dashlet_ai_usage_reports',
        'ai_anomalies' => 'dashlet_ai_anomalies',
        'ai_insights' => 'dashlet_ai_insights',
        'ai_forecasts' => 'dashlet_ai_forecasts',
    ],

    'navigation' => [
        'group' => null,
        'label' => 'Dashboards',
        'icon' => 'heroicon-o-chart-bar-square',
        'sort' => 35,
    ],

    'discovery' => [
        'model_paths' => [
            'app/Models',
        ],
        'exclude_models' => [],
        'exclude_tables' => [
            'migrations',
            'password_reset_tokens',
            'personal_access_tokens',
            'failed_jobs',
            'jobs',
            'job_batches',
            'cache',
            'cache_locks',
            'sessions',
        ],
        'tables' => null,
    ],

    'permissions' => [
        'manage_dashboards' => 'manage_dashboards',
        'view_dashboards' => 'view_dashboards',
    ],

    'grid' => [
        'columns' => 12,
        'cell_height' => 80,
        'margin' => 8,
        'save_debounce_ms' => 450,
    ],

    'preview' => [
        'limit' => 50,
        'debounce_ms' => 300,
    ],

    'query' => [
        'max_rows' => 500,
    ],

    'entities' => [
        'tables' => [
            'users',
        ],
    ],

    'access' => [
        'roles' => [],
        'permissions' => [],
    ],

    'data_sources' => [
        'users' => UserMetricsDataSource::class,
        'sample_metrics' => SampleMetricsDataSource::class,
        'login_activity' => LoginActivityDataSource::class,
        'ai_spend' => AiSpendDataSource::class,
    ],

    'user_model' => env('VISUAL_DASHBOARDS_USER_MODEL', env('DASHLET_USER_MODEL', 'App\\Models\\User')),

    'public' => [
        'path' => env('VISUAL_DASHBOARDS_PUBLIC_PATH', 'visual-dashboards'),
    ],
];

<?php

namespace Spiggle\VisualDashboards\Services;

use InvalidArgumentException;
use Spiggle\VisualDashboards\Contracts\DataSource;
use Spiggle\VisualDashboards\DataSources\FormAuditLogDataSource;
use Spiggle\VisualDashboards\DataSources\FormSubmissionDataSource;
use Spiggle\VisualDashboards\Models\Dashlet;
use Spiggle\VisualDashboards\Support\ChartVariantCatalog;
use Spiggle\VisualDashboards\Support\KpiCardPresenter;
use Spiggle\VisualDashboards\Support\QueryConfig;
use Spiggle\VisualDashboards\Support\ValueFormatter;
use Spiggle\VisualDashboards\Support\VisualTypes;

class DashletRegistry
{
    /**
     * @var array<string, DataSource>|null
     */
    protected ?array $sources = null;

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $payloadCache = [];

    /**
     * @return array<string, string>
     */
    public function dataSourceLabels(): array
    {
        return collect($this->allDataSources())
            ->mapWithKeys(fn (DataSource $source): array => [$source->key() => $source->label()])
            ->all();
    }

    public function dataSource(string $key): DataSource
    {
        $sources = $this->allDataSources();

        if (! isset($sources[$key])) {
            throw new InvalidArgumentException("Unknown dashlet data source [{$key}].");
        }

        return $sources[$key];
    }

    /**
     * @return array<string, DataSource>
     */
    public function allDataSources(): array
    {
        if ($this->sources !== null) {
            return $this->sources;
        }

        $resolved = [];

        foreach (config('visual-dashboards.data_sources', []) as $key => $class) {
            $instance = app($class);

            if (! $instance instanceof DataSource) {
                throw new InvalidArgumentException("Data source [{$key}] must implement ".DataSource::class);
            }

            $resolved[$instance->key()] = $instance;
        }

        $formSource = app(FormSubmissionDataSource::class);

        if ($formSource->available()) {
            $resolved[$formSource->key()] = $formSource;
        }

        $auditSource = app(FormAuditLogDataSource::class);

        if ($auditSource->available()) {
            $resolved[$auditSource->key()] = $auditSource;
        }

        return $this->sources = $resolved;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function renderPayload(Dashlet $dashlet, array $overrides = []): array
    {
        $cacheKey = $dashlet->id.'|'.($dashlet->updated_at?->timestamp ?? 0).'|'.$dashlet->type.'|'.md5(json_encode($overrides));

        if (isset($this->payloadCache[$cacheKey])) {
            return $this->payloadCache[$cacheKey];
        }

        $config = array_replace_recursive($dashlet->normalizedConfig(), $overrides);
        $engine = app(DataEngine::class);
        $visual = is_array($config['visual'] ?? null) ? $config['visual'] : [];
        $visual['color'] = ValueFormatter::colorForScheme(
            $visual['color_scheme'] ?? null,
            $visual['color'] ?? '#f59e0b'
        );

        if (ChartVariantCatalog::supports($dashlet->type)) {
            $visual['chart_variant'] = ChartVariantCatalog::normalize(
                $dashlet->type,
                $visual['chart_variant'] ?? null
            );
        } else {
            unset($visual['chart_variant']);
        }

        $visual['number_format'] = ValueFormatter::formatKey(
            (string) ($visual['number_format'] ?? 'number')
        );
        $visual['currency'] = ValueFormatter::normalizeCurrency(
            (string) ($visual['currency'] ?? 'USD')
        );
        $queryConfig = QueryConfig::normalize($config);

        if (blank($queryConfig['filters']['grain'] ?? null) && filled($visual['default_grain'] ?? null)) {
            $queryConfig['filters']['grain'] = (string) $visual['default_grain'];
        }

        $payload = [
            'id' => $dashlet->id,
            'type' => $dashlet->type,
            'title' => $config['heading'] ?: $config['title'],
            'heading' => $config['heading'] ?: $config['title'],
            'subheading' => $config['subheading'] ?? '',
            'visual' => $visual,
            'interactive' => [
                'period_filter' => $this->periodFilterEnabled($visual),
                'grain' => (string) ($queryConfig['filters']['grain'] ?? $visual['default_grain'] ?? 'year'),
                'grains' => $this->enabledPeriodGrains($visual),
            ],
            'grid' => [
                'x' => $dashlet->grid_x,
                'y' => $dashlet->grid_y,
                'w' => $dashlet->grid_w,
                'h' => $dashlet->grid_h,
            ],
        ];

        if (! VisualTypes::isPersistedConfigReady($dashlet->type, $config, $dashlet->config ?? [])) {
            $payload['unconfigured'] = true;
            $payload['empty_message'] = 'Select a data source and field mapping to display this visual.';

            return $this->payloadCache[$cacheKey] = $payload;
        }

        if ($dashlet->type === 'container') {
            $payload['children'] = $config['children'] ?? [];

            return $this->payloadCache[$cacheKey] = $payload;
        }

        if ($dashlet->type === 'hero') {
            $chart = $engine->chartFromConfig('hero', $queryConfig);
            $payload['greeting'] = $chart['greeting'] ?? 'Hello';
            $payload['month_label'] = $chart['month_label'] ?? now()->format('F Y');

            return $this->payloadCache[$cacheKey] = $payload;
        }

        if ($dashlet->type === 'insight_list') {
            $chart = $engine->chartFromConfig('insight_list', $queryConfig);
            $payload['items'] = $chart['items'] ?? [];

            return $this->payloadCache[$cacheKey] = $payload;
        }

        if ($dashlet->type === 'stat_card') {
            $statConfig = KpiCardPresenter::configForQuery(array_replace_recursive($config, ['visual' => $visual]));
            $statQueryConfig = QueryConfig::normalize($statConfig);
            $chart = $engine->chartFromConfig('stat_card', $statQueryConfig);

            return $this->payloadCache[$cacheKey] = array_replace($payload, KpiCardPresenter::enrich([
                'value' => $chart['value'] ?? 0,
                'stat_label' => $chart['label'] ?? 'Total',
                'formatted' => $chart['formatted'] ?? ValueFormatter::format(
                    (float) ($chart['value'] ?? 0),
                    (string) ($visual['number_format'] ?? 'number'),
                    (string) ($visual['currency'] ?? 'USD')
                ),
                'hint' => $chart['hint'] ?? null,
                'delta' => $chart['delta'] ?? null,
                'up' => (bool) ($chart['up'] ?? false),
                'sparkline' => $chart['sparkline'] ?? [],
                'badge' => $chart['badge'] ?? null,
                'logo' => $chart['logo'] ?? null,
                'logo_color' => $chart['logo_color'] ?? $visual['color'] ?? null,
            ], $statConfig));
        }

        if ($dashlet->type === 'table') {
            $limit = (int) config('visual-dashboards.preview.limit', 50);
            $tablePayload = $engine->tablePayloadFromConfig($queryConfig, $limit);
            $payload['rows'] = $tablePayload['rows'];
            $payload['columns'] = $tablePayload['columns'];

            return $this->payloadCache[$cacheKey] = $payload;
        }

        $payload['chart'] = $engine->chartFromConfig($dashlet->type, $queryConfig);
        $payload['center'] = $payload['chart']['center'] ?? null;
        $payload['summary'] = $payload['chart']['summary'] ?? null;

        return $this->payloadCache[$cacheKey] = $payload;
    }

    /**
     * @param  array<string, mixed>  $visual
     * @return array<string, string>
     */
    protected function enabledPeriodGrains(array $visual): array
    {
        $labels = [
            'day' => 'Day',
            'week' => 'Week',
            'month' => 'Month',
            'year' => 'Year',
        ];

        $configured = $visual['period_grains'] ?? null;

        if (is_array($configured) && $configured !== []) {
            return collect($labels)
                ->only($configured)
                ->all();
        }

        if ((bool) ($visual['period_filter'] ?? false)) {
            return $labels;
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $visual
     */
    protected function periodFilterEnabled(array $visual): bool
    {
        $configured = $visual['period_grains'] ?? null;

        if (is_array($configured) && $configured !== []) {
            return true;
        }

        return (bool) ($visual['period_filter'] ?? false);
    }
}

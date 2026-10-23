@php
    $resolvedType = (string) ($type ?: ($payload['type'] ?? 'bar'));
    $showTypeBadge = $showTypeBadge ?? true;
    $titleStyle = $titleStyle ?? '';
    $previewSyncKey = $previewSyncKey ?? ('viz-'.md5((string) $resolvedType));
    $previewChartPayload = [
        'id' => 'panel-preview',
        'type' => $resolvedType,
        'chart' => $payload['chart'] ?? [],
        'visual' => $visual,
        'title' => $title,
        'subheading' => $subheading ?? '',
    ];
    $dataSignature = md5(json_encode([
        $resolvedType,
        $payload['chart'] ?? [],
        $payload['rows'] ?? null,
        $payload['value'] ?? null,
        $payload['items'] ?? null,
    ]));
@endphp

<div class="dashlet-viz-preview relative" wire:key="dashlet-viz-shell-{{ $previewSyncKey }}">
    <div
        wire:loading.flex.delay.300ms
        class="dashlet-viz-preview-loading"
        wire:target="editSourceField,editGroupBy,editBreakdown,editAggregation,editTransforms,editSourceKey,editSourceGroup,editRelatedJoins,editPeriodGrains,editTimeGrain,editChartVariant,editNumberFormat,editCurrency,editColorScheme,editLegendShow,editLegendPosition,editDataLabels,editShowXAxisTitle,editShowYAxisTitle,changeVisualType,saveDataSource"
    >
        <x-filament::loading-indicator class="h-8 w-8 text-primary-600" />
        <span class="dashlet-viz-preview-loading-label">Updating preview…</span>
    </div>

    <div class="dashlet-viz-preview-header">
        <div class="dashlet-viz-preview-titles">
            <strong
                class="dashlet-viz-preview-title"
                @if (! empty($titleStyle)) style="{{ $titleStyle }}" @endif
            >{{ $title !== '' ? $title : 'Untitled chart' }}</strong>
            @if (filled($subheading ?? null))
                <p class="dashlet-viz-preview-subheading">{{ $subheading }}</p>
            @endif
        </div>
        @if ($showTypeBadge)
            <span class="dashlet-viz-preview-badge">{{ strtoupper(str_replace('_', ' ', $resolvedType)) }}</span>
        @endif
    </div>

    @if ($resolvedType === 'stat_card')
        <x-visual-dashboards::dashlet-kpi-card
            :payload="array_merge($payload, ['type' => 'stat_card'])"
        />
    @elseif ($resolvedType === 'hero')
        <div class="dashlet-hero">
            <p class="dashlet-hero-date">{{ $payload['month_label'] ?? now()->format('F Y') }}</p>
            <h2 class="dashlet-hero-hello">{{ $payload['greeting'] ?? 'Hello' }}</h2>
        </div>
    @elseif ($resolvedType === 'insight_list')
        <div class="dashlet-insights">
            @forelse (($payload['items'] ?? []) as $insight)
                <article class="dashlet-insight">
                    <strong>{{ $insight['title'] ?? '' }}</strong>
                    <p>{{ $insight['body'] ?? '' }}</p>
                </article>
            @empty
                <p class="dashlet-preview-more">No recommendations yet.</p>
            @endforelse
        </div>
    @elseif ($resolvedType === 'table')
        @php($rows = $payload['rows'] ?? [])
        @if ($rows === [])
            <p class="dashlet-preview-more">No rows match the current filters.</p>
        @else
            @php($columns = $payload['columns'] ?? array_keys($rows[0]))
            <div class="dashlet-preview-wrap">
                <table class="dashlet-preview-table">
                    <thead>
                        <tr>
                            @foreach ($columns as $column)
                                <th>{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_slice($rows, 0, 5) as $row)
                            <tr>
                                @foreach ($columns as $column)
                                    <td>{{ data_get($row, $column) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if (count($rows) > 5)
                <p class="dashlet-preview-more">Showing 5 of {{ count($rows) }} row(s).</p>
            @endif
        @endif
    @else
        <div class="dashlet-chart-wrap dashlet-viz-preview-chart">
            <div
                class="dashlet-chart-host dashlet-chart-host-preview"
                wire:ignore
                data-preview-sync="{{ $previewSyncKey }}"
                data-data-signature="{{ $dataSignature }}"
                x-data="vdPanelPreviewChart(@js($previewChartPayload))"
                x-init="boot($el)"
                @vd-panel-preview-chart.window="onSync($event)"
            ></div>
        </div>
    @endif
</div>

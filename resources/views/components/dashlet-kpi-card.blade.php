@if (($payload['type'] ?? '') === 'stat_card' || isset($payload['value']))
    @php
        $kpi = is_array($payload['kpi'] ?? null) ? $payload['kpi'] : [];
        $comparison = is_array($kpi['comparison'] ?? null) ? $kpi['comparison'] : null;
        $iconSvg = (string) ($kpi['icon_svg'] ?? '');
        $iconColor = (string) ($kpi['icon_color'] ?? '#6366f1');
        $valueStyle = is_array($kpi['value_style'] ?? null) ? $kpi['value_style'] : [];
        $valueSize = (string) ($valueStyle['font_size'] ?? '1.875rem');
        $valueWeight = (string) ($valueStyle['font_weight'] ?? '700');
        $metricLabel = filled($kpi['metric_label'] ?? null)
            ? (string) $kpi['metric_label']
            : (string) ($payload['stat_label'] ?? $payload['label'] ?? 'Total');
        $value = $payload['formatted'] ?? number_format((float) ($payload['value'] ?? 0));
    @endphp

    <div class="dashlet-kpi-card">
        <div class="dashlet-kpi-header">
            <span class="dashlet-kpi-title">{{ $metricLabel }}</span>
            @if ($iconSvg !== '')
                <span
                    class="dashlet-kpi-icon"
                    style="--kpi-icon-color: {{ $iconColor }}; width: 2.25rem; height: 2.25rem; flex: 0 0 2.25rem;"
                >{!! $iconSvg !!}</span>
            @endif
        </div>

        <div
            class="dashlet-kpi-value"
            style="--kpi-value-size: {{ $valueSize }}; --kpi-value-weight: {{ $valueWeight }};"
        >{{ $value }}</div>

        @if ($comparison)
            <p class="dashlet-kpi-comparison">
                <span class="dashlet-kpi-trend {{ ($comparison['favorable'] ?? false) ? 'is-favorable' : 'is-unfavorable' }}">
                    <span class="dashlet-kpi-arrow" aria-hidden="true">{{ $comparison['arrow'] ?? '' }}</span>
                    <span>{{ $comparison['percent'] ?? '' }}</span>
                </span>
                @if (filled($comparison['label'] ?? null))
                    <span class="dashlet-kpi-comparison-label">{{ $comparison['label'] }}</span>
                @endif
            </p>
        @endif
    </div>
@endif

{{-- Shared Alpine markup for KPI cards on the dashboard grid --}}
<div class="dashlet-kpi-card">
    <div class="dashlet-kpi-header">
        <span
            class="dashlet-kpi-title"
            x-text="item.payload?.kpi?.metric_label || item.payload?.stat_label || item.payload?.label || 'Total'"
        ></span>
        <span
            class="dashlet-kpi-icon"
            x-show="item.payload?.kpi?.icon_svg"
            :style="'--kpi-icon-color: ' + (item.payload?.kpi?.icon_color || '#6366f1') + '; width: 2.25rem; height: 2.25rem; flex: 0 0 2.25rem;'"
            x-html="item.payload?.kpi?.icon_svg"
        ></span>
    </div>

    <div class="dashlet-kpi-value-row">
        <div
            class="dashlet-kpi-value"
            :style="'--kpi-value-size: ' + (item.payload?.kpi?.value_style?.font_size || '1.875rem') + '; --kpi-value-weight: ' + (item.payload?.kpi?.value_style?.font_weight || '700') + ';'"
            x-text="item.payload?.formatted ?? (item.payload?.value != null ? item.payload.value : '0')"
        ></div>
        <span class="dashlet-kpi-suffix" x-show="item.payload?.kpi?.value_suffix" x-text="item.payload?.kpi?.value_suffix"></span>
        <svg class="dashlet-kpi-sparkline" viewBox="0 0 120 36" x-show="(item.payload?.sparkline || []).length > 1" aria-hidden="true">
            <polyline fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" :points="window.spiggleSparklinePoints(item.payload?.sparkline || [])"></polyline>
        </svg>
    </div>

    <p class="dashlet-kpi-details" x-show="(item.payload?.details || []).length">
        <template x-for="detail in item.payload?.details || []" :key="detail.label">
            <span><strong x-text="detail.formatted"></strong> <span x-text="detail.label"></span></span>
        </template>
    </p>

    <p class="dashlet-kpi-comparison" x-show="item.payload?.kpi?.comparison">
        <span
            class="dashlet-kpi-trend"
            :class="item.payload?.kpi?.comparison?.favorable ? 'is-favorable' : 'is-unfavorable'"
        >
            <span class="dashlet-kpi-arrow" aria-hidden="true" x-text="item.payload?.kpi?.comparison?.arrow"></span>
            <span x-text="item.payload?.kpi?.comparison?.percent"></span>
        </span>
        <span
            class="dashlet-kpi-comparison-label"
            x-show="item.payload?.kpi?.comparison?.label"
            x-text="item.payload?.kpi?.comparison?.label"
        ></span>
    </p>
</div>

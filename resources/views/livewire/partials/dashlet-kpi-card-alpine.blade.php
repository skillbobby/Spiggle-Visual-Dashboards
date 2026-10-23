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

    <div
        class="dashlet-kpi-value"
        :style="'--kpi-value-size: ' + (item.payload?.kpi?.value_style?.font_size || '1.875rem') + '; --kpi-value-weight: ' + (item.payload?.kpi?.value_style?.font_weight || '700') + ';'"
        x-text="item.payload?.formatted ?? (item.payload?.value != null ? item.payload.value : '0')"
    ></div>

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

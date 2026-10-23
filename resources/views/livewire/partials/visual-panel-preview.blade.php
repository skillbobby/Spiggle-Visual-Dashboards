@php
    $visualType = $selectedVisual?->type ?? 'stat_card';
    $typeLabel = $this->selectedVisualTypeLabel();
    $previewTitle = filled($editHeading) ? $editHeading : $typeLabel;
    $resolvedPayload = is_array($previewPayload) ? $previewPayload : [];
    $resolvedPayload['type'] = $visualType;
    $titleFont = $editTitleFont ?? 'inherit';
    $titleSize = $editTitleSize ?? 'md';
    $titleColor = $editTitleColor ?? '';
    $titleStyle = trim(implode(';', array_filter([
        $titleFont === 'sans' ? 'font-family:ui-sans-serif,system-ui,sans-serif' : null,
        $titleFont === 'serif' ? 'font-family:ui-serif,Georgia,serif' : null,
        $titleFont === 'mono' ? 'font-family:ui-monospace,SFMono-Regular,monospace' : null,
        $titleSize === 'sm' ? 'font-size:0.875rem' : null,
        $titleSize === 'lg' ? 'font-size:1.25rem' : null,
        $titleColor !== '' ? 'color:'.$titleColor : null,
    ])));
    $previewVisual = [
        'color' => \Spiggle\VisualDashboards\Support\ValueFormatter::colorForScheme($editColorScheme),
        'color_scheme' => $editColorScheme,
        'border' => $editBorder,
        'number_format' => $this->normalizedNumberFormat(),
        'currency' => $this->normalizedCurrency(),
        'title' => [
            'font' => $titleFont,
            'size' => $titleSize,
            'color' => $titleColor,
            'text' => $previewTitle,
            'subheading' => $editSubheading,
        ],
        'legend' => [
            'show' => $editLegendShow ?? 'auto',
            'position' => $editLegendPosition ?? 'bottom',
        ],
        'data_labels' => [
            'enabled' => (bool) ($editDataLabels ?? false),
        ],
        'axes' => [
            'show_x_title' => (bool) ($editShowXAxisTitle ?? true),
            'show_y_title' => (bool) ($editShowYAxisTitle ?? true),
        ],
        ...( \Spiggle\VisualDashboards\Support\ChartVariantCatalog::supports($visualType)
            ? ['chart_variant' => \Spiggle\VisualDashboards\Support\ChartVariantCatalog::normalize($visualType, $editChartVariant)]
            : []),
    ];
@endphp

<div class="vd-panel-preview" wire:key="vd-preview-shell-{{ $selectedVisualId }}">
    <div class="vd-panel-preview-header">
        <strong>Live preview</strong>
        <span class="vd-panel-preview-badge">{{ $typeLabel }}</span>
    </div>

    @if ($previewPayload === null)
        <div class="vd-panel-preview-empty">
            @if (! $this->hasValidDataSourceSelection())
                <p>Select fields for this chart type to preview data.</p>
            @else
                <p>No rows match the current filters. Adjust filters or choose a different source.</p>
            @endif
        </div>
    @elseif (! empty($previewPayload['error']))
        <div class="vd-panel-preview-empty">
            <p>{{ $previewPayload['error'] }}</p>
        </div>
    @else
        @include('visual-dashboards::filament.components.dashlet-viz-preview', [
            'type' => $visualType,
            'title' => $previewTitle,
            'subheading' => $editSubheading,
            'titleStyle' => $titleStyle,
            'payload' => $resolvedPayload,
            'showTypeBadge' => false,
            'visual' => $previewVisual,
            'previewSyncKey' => 'panel-'.$selectedVisualId,
        ])
    @endif
</div>

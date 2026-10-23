@php
    $visualType = $selectedVisual?->type ?? 'bar';
    $fieldBlueprint = \Spiggle\VisualDashboards\Support\VisualTypes::fieldBlueprint($visualType);
    $gallery = \Spiggle\VisualDashboards\Support\VisualTypes::gallery();
    $shortLabel = static function (string $label): string {
        return match (true) {
            $label === 'KPI Card' => 'KPI',
            $label === 'Data Table' => 'Table',
            $label === 'Stacked Area' => 'Stacked',
            $label === 'Radial Gauge' => 'Gauge',
            str_ends_with($label, ' Chart') => substr($label, 0, -6),
            default => $label,
        };
    };
@endphp

<div class="vd-visual-type-banner vd-visual-type-banner-compact">
    <div class="vd-type-picker-heading">
        <span class="vd-type-picker-kicker">Chart type</span>
        <span class="vd-type-picker-current">{{ $fieldBlueprint['label'] ?? 'Visual' }}</span>
    </div>
    <div class="vd-type-picker" role="group" aria-label="Chart type">
        @foreach ($this->visualTypeOptions() as $value => $label)
            @php
                $iconKey = $gallery[$value]['icon'] ?? 'default';
                $chip = $shortLabel($label);
            @endphp
            <button
                type="button"
                class="vd-type-picker-btn @if ($value === $visualType) is-active @endif"
                wire:key="vd-type-{{ $value }}"
                wire:click="changeVisualType('{{ $value }}')"
                title="{{ $label }}"
                aria-label="{{ $label }}"
                aria-pressed="{{ $value === $visualType ? 'true' : 'false' }}"
            >
                <span class="vd-type-picker-icon" aria-hidden="true">
                    {!! \Spiggle\VisualDashboards\Support\VisualTypes::iconSvg($iconKey) !!}
                </span>
                <span class="vd-type-picker-label">{{ $chip }}</span>
            </button>
        @endforeach
    </div>
    @if (filled($fieldBlueprint['intro'] ?? null))
        <p class="vd-muted vd-visual-type-intro">{{ $fieldBlueprint['intro'] }}</p>
    @endif
</div>

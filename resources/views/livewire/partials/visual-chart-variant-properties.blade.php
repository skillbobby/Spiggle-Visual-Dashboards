@if ($selectedVisual && $this->chartVariantOptions() !== [])
    <section class="vd-kpi-properties">
        <h4 class="vd-transform-heading">Chart style</h4>

        <label class="vd-field">
            <span>Variant</span>
            <select wire:model.live="editChartVariant" class="vd-input">
                @foreach ($this->chartVariantOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>

        @php($variantHint = \Spiggle\VisualDashboards\Support\ChartVariantCatalog::hint($selectedVisual->type, $editChartVariant))
        @if ($variantHint)
            <p class="vd-muted">{{ $variantHint }}</p>
        @else
            <p class="vd-muted">Same underlying data — only the chart presentation changes.</p>
        @endif
    </section>
@endif

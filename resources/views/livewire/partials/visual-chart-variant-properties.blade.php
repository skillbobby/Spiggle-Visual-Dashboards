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

        @if ($selectedVisual->type === 'radialbar')
            <div class="vd-kpi-comparison-row">
                <label class="vd-field">
                    <span>Scale minimum</span>
                    <input type="number" step="any" class="vd-input" wire:model.live.debounce.300ms="editGaugeMin" placeholder="0">
                </label>
                <label class="vd-field">
                    <span>Scale maximum</span>
                    <input type="number" step="any" class="vd-input" wire:model.live.debounce.300ms="editGaugeMax" placeholder="100">
                </label>
            </div>
            <p class="vd-muted">Leave maximum empty to fit the value. A 0–100 scale suits rates.</p>
        @endif
    </section>
@endif

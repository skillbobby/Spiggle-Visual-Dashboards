@if ($selectedVisual && $this->supportsValueFormat())
    <section class="vd-kpi-properties">
        <h4 class="vd-transform-heading">Value labels</h4>

        <label class="vd-field">
            <span>Format</span>
            <select wire:model.live="editNumberFormat" class="vd-input">
                @foreach ($this->numberFormatOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>

        @if ($editNumberFormat === 'currency')
            <label class="vd-field">
                <span>Currency</span>
                <select wire:model.live="editCurrency" class="vd-input">
                    @foreach ($this->currencyOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        @endif

        <p class="vd-muted">Applies to KPI values, chart data labels, tooltips, and axis ticks.</p>
    </section>
@endif

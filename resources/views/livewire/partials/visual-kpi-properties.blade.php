@if ($selectedVisual?->type === 'stat_card')
    <section class="vd-kpi-properties">
        <h4 class="vd-transform-heading">KPI appearance</h4>

        <label class="vd-field">
            <span>Value suffix</span>
            <input type="text" wire:model.live.debounce.300ms="editKpiValueSuffix" class="vd-input" placeholder="Days">
            <p class="vd-muted">Shown beside the number, for example Days.</p>
        </label>

        <div class="vd-kpi-comparison-row">
            <label class="vd-field">
                <span>Sparkline column</span>
                <input type="text" wire:model.live.debounce.300ms="editKpiSparklineField" class="vd-input" placeholder="admitted_on">
                <p class="vd-muted">Date column used to draw the trend. Leave blank to hide it.</p>
            </label>
            <label class="vd-field">
                <span>Sparkline grain</span>
                <select wire:model.live="editKpiSparklineGrain" class="vd-input">
                    <option value="day">Day</option>
                    <option value="week">Week</option>
                    <option value="month">Month</option>
                    <option value="year">Year</option>
                </select>
            </label>
        </div>

        <fieldset class="vd-field">
            <span>Supporting figures</span>
            <p class="vd-muted">Extra totals from the same rows, shown under the headline number.</p>
            @foreach ($editKpiSupporting as $index => $support)
                <div class="vd-kpi-comparison-row">
                    <input type="text" class="vd-input" wire:model="editKpiSupporting.{{ $index }}.label" placeholder="Occupied">
                    <select class="vd-input" wire:model="editKpiSupporting.{{ $index }}.fn">
                        <option value="COUNT">Count</option>
                        <option value="SUM">Sum</option>
                        <option value="AVG">Average</option>
                        <option value="MIN">Min</option>
                        <option value="MAX">Max</option>
                    </select>
                    <input type="text" class="vd-input" wire:model="editKpiSupporting.{{ $index }}.field" placeholder="*">
                    <button type="button" class="vd-btn vd-btn-muted" wire:click="removeKpiSupporting({{ $index }})">Remove</button>
                </div>
            @endforeach
            <button type="button" class="vd-btn vd-btn-muted" wire:click="addKpiSupporting">Add supporting figure</button>
        </fieldset>

        <label class="vd-field">
            <span>Metric label</span>
            <input type="text" wire:model.live.debounce.300ms="editKpiMetricLabel" class="vd-input" placeholder="Total submissions">
            <p class="vd-muted">Shown inside the card (e.g. Total Balance). The card heading above stays as the widget title.</p>
        </label>

        <label class="vd-field">
            <span>Count period</span>
            <select wire:model.live="editKpiPeriod" class="vd-input">
                @foreach ($this->kpiPeriodOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <p class="vd-muted">Controls which date range is counted for this KPI value.</p>
        </label>

        @if ($editKpiPeriod === 'custom')
            <label class="vd-field">
                <span>Custom rolling days</span>
                <input type="number" min="1" step="1" wire:model.live.debounce.300ms="editKpiCustomDays" class="vd-input" placeholder="30">
            </label>
        @endif

        <div class="vd-kpi-comparison-row">
            <label class="vd-field">
                <span>Value size</span>
                <select wire:model.live="editKpiValueSize" class="vd-input">
                    @foreach ($this->kpiValueSizeOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="vd-field">
                <span>Value weight</span>
                <select wire:model.live="editKpiValueWeight" class="vd-input">
                    @foreach ($this->kpiValueWeightOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <label class="vd-field">
            <span>Icon</span>
            <select wire:model.live="editKpiIcon" class="vd-input">
                @foreach ($this->kpiIconOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <fieldset class="vd-field">
            <label class="vd-field vd-field-toggle">
                <span>Show period comparison</span>
                <input type="checkbox" wire:model.live="editKpiComparisonEnabled">
            </label>

            @if ($editKpiComparisonEnabled)
                <label class="vd-field">
                    <span>Comparison source</span>
                    <select wire:model.live="editKpiComparisonMode" class="vd-input">
                        <option value="manual">Manual value</option>
                        <option value="previous_period" @disabled($editKpiPeriod === 'total')>Previous period (auto)</option>
                    </select>
                </label>

                @if ($editKpiPeriod === 'total')
                    <p class="vd-muted">Auto comparison is unavailable for all-time totals. Use manual comparison or choose a bounded period.</p>
                @elseif ($editKpiComparisonMode === 'previous_period')
                    <p class="vd-muted">
                        Compares {{ strtolower($this->kpiPeriodOptions()[$editKpiPeriod] ?? $editKpiPeriod) }}
                        against the matching prior period
                        (e.g. this month vs last month).
                    </p>
                @endif

                @if ($editKpiComparisonMode === 'manual')
                    <div class="vd-kpi-comparison-row">
                        <label class="vd-field">
                            <span>Change (%)</span>
                            <input type="number" step="0.1" min="0" wire:model.live.debounce.300ms="editKpiComparisonDelta" class="vd-input" placeholder="3.2">
                        </label>
                        <label class="vd-field">
                            <span>Direction</span>
                            <select wire:model.live="editKpiComparisonUp" class="vd-input">
                                <option value="1">Up ↑</option>
                                <option value="0">Down ↓</option>
                            </select>
                        </label>
                    </div>
                @endif

                <label class="vd-field vd-field-toggle">
                    <span>Higher values are better</span>
                    <input type="checkbox" wire:model.live="editKpiHigherIsBetter">
                </label>
                <p class="vd-muted">When enabled, an increase shows green; when disabled (e.g. error counts), a decrease shows green.</p>

                <label class="vd-field">
                    <span>Comparison label</span>
                    <input type="text" wire:model.live.debounce.300ms="editKpiComparisonLabel" class="vd-input" placeholder="than last month">
                    @if ($editKpiComparisonMode === 'previous_period')
                        <p class="vd-muted">Leave blank to use the default for the selected count period.</p>
                    @endif
                </label>
            @endif
        </fieldset>
    </section>
@endif

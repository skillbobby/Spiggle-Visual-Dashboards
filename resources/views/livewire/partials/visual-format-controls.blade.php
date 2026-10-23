@php
    $fieldDebounceMs = (int) config('visual-dashboards.preview.debounce_ms', 300);
    $formatTabs = $this->formatTabs();
    $initialFormatTab = array_key_exists($formatTab, $formatTabs)
        ? $formatTab
        : (array_key_first($formatTabs) ?: 'title');
@endphp

<div
    class="vd-format-shell"
    x-data="{
        id: @js($selectedVisualId),
        tab: @js($initialFormatTab),
        init() {
            window.__vdFmtTabs = window.__vdFmtTabs || {};
            if (window.__vdFmtTabs[this.id]) {
                this.tab = window.__vdFmtTabs[this.id];
            }
            this.$watch('tab', (value) => {
                window.__vdFmtTabs[this.id] = value;
            });
        }
    }"
    wire:key="vd-format-shell-{{ $selectedVisualId }}"
>
    <div class="vd-format-tabs" role="tablist" aria-label="Formatting">
        @foreach ($formatTabs as $tabKey => $tabLabel)
            <button
                type="button"
                role="tab"
                class="vd-format-tab"
                :class="{ 'is-active': tab === @js($tabKey) }"
                :aria-selected="tab === @js($tabKey) ? 'true' : 'false'"
                @click.prevent="tab = @js($tabKey)"
            >{{ $tabLabel }}</button>
        @endforeach
    </div>

    <div class="vd-format-panel" role="tabpanel">
        <div class="vd-format-section" x-show="tab === 'title'" x-cloak>
            <label class="vd-field">
                <span>Chart title</span>
                <input type="text" wire:model.live.debounce.{{ $fieldDebounceMs }}ms="editHeading" class="vd-input" placeholder="Chart title">
            </label>
            <label class="vd-field">
                <span>Subheading</span>
                <input type="text" wire:model.live.debounce.{{ $fieldDebounceMs }}ms="editSubheading" class="vd-input" placeholder="Optional subheading">
            </label>
            <label class="vd-field">
                <span>Font</span>
                <select wire:model.live="editTitleFont" class="vd-input">
                    @foreach ($this->titleFontOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <div class="vd-format-row">
                <label class="vd-field">
                    <span>Size</span>
                    <select wire:model.live="editTitleSize" class="vd-input">
                        @foreach ($this->titleSizeOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="vd-field">
                    <span>Color</span>
                    <div class="vd-color-row">
                        <input
                            type="color"
                            class="vd-input vd-input-color"
                            value="{{ $editTitleColor !== '' ? $editTitleColor : '#111827' }}"
                            wire:change="$set('editTitleColor', $event.target.value)"
                            title="Title color"
                        >
                        <button
                            type="button"
                            class="vd-btn vd-btn-muted vd-btn-sm"
                            wire:click="$set('editTitleColor', '')"
                            @disabled($editTitleColor === '')
                        >Clear</button>
                    </div>
                </label>
            </div>
        </div>

        <div class="vd-format-section" x-show="tab === 'colors'" x-cloak>
            <label class="vd-field">
                <span>Color scheme</span>
                <select wire:model.live="editColorScheme" class="vd-input">
                    @foreach ($this->colorSchemeOptions() as $scheme => $schemeLabel)
                        <option value="{{ $scheme }}">{{ $schemeLabel }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="vd-format-section" x-show="tab === 'labels'" x-cloak>
            @include('visual-dashboards::livewire.partials.visual-value-format-properties', ['selectedVisual' => $selectedVisual])
            <label class="vd-field vd-field-toggle">
                <span>Show data labels on chart</span>
                <input type="checkbox" wire:model.live="editDataLabels">
            </label>
        </div>

        <div class="vd-format-section" x-show="tab === 'series'" x-cloak>
            @include('visual-dashboards::livewire.partials.visual-chart-variant-properties', ['selectedVisual' => $selectedVisual])
        </div>

        <div class="vd-format-section" x-show="tab === 'legend'" x-cloak>
            <label class="vd-field">
                <span>Legend</span>
                <select wire:model.live="editLegendShow" class="vd-input">
                    @foreach ($this->legendShowOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="vd-field">
                <span>Position</span>
                <select wire:model.live="editLegendPosition" class="vd-input" @disabled($editLegendShow === 'never')>
                    @foreach ($this->legendPositionOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="vd-format-section" x-show="tab === 'axes'" x-cloak>
            <label class="vd-field vd-field-toggle">
                <span>Show X-axis title</span>
                <input type="checkbox" wire:model.live="editShowXAxisTitle">
            </label>
            <label class="vd-field vd-field-toggle">
                <span>Show Y-axis title</span>
                <input type="checkbox" wire:model.live="editShowYAxisTitle">
            </label>
            <p class="vd-muted">Titles come from the bound dimension and measure fields.</p>
        </div>

        <div class="vd-format-section" x-show="tab === 'area'" x-cloak>
            <label class="vd-field vd-field-toggle">
                <span>Card border</span>
                <input type="checkbox" wire:model.live="editBorder">
            </label>
            <p class="vd-muted">Controls the border around the chart card on the canvas and in preview.</p>
        </div>

        <div class="vd-format-section" x-show="tab === 'kpi'" x-cloak>
            @include('visual-dashboards::livewire.partials.visual-kpi-properties', ['selectedVisual' => $selectedVisual])
        </div>

        <div class="vd-panel-actions vd-format-actions">
            @if ($panelNotice !== '')
                <p class="vd-panel-notice" role="status">{{ $panelNotice }}</p>
            @endif
            <button type="button" class="vd-btn vd-btn-primary" wire:click="saveProperties">Save formatting</button>
        </div>
    </div>
</div>

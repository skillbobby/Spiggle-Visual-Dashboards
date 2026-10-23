@php($fieldDebounceMs = (int) config('visual-dashboards.preview.debounce_ms', 300))
@php($visualType = $selectedVisual?->type ?? 'bar')
@php($fieldBlueprint = \Spiggle\VisualDashboards\Support\VisualTypes::fieldBlueprint($visualType))
<div class="vd-panel-body">
    <section class="vd-builder-section">
        <h4 class="vd-transform-heading">1. Data source</h4>
        <p class="vd-panel-intro">Fields in the dropdowns come from this source’s schema — not sample labels.</p>

        <label class="vd-field">
            <span>Source category</span>
            <select wire:model.live="editSourceGroup" class="vd-input">
                <option value="">Select a category…</option>
                @foreach ($this->sourceGroupOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>

        @if ($editSourceGroup !== '')
            <label class="vd-field">
                <span>Primary source</span>
                <select wire:model.live="editSourceKey" class="vd-input">
                    <option value="">Select a source…</option>
                    @foreach ($this->sourceOptionsForGroup() as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        @endif

        @if ($editSourceKey !== '' && $this->relatedSourceOptions() !== [])
            <fieldset class="vd-field vd-related-fieldset">
                <span>Related data</span>
                <p class="vd-muted">Include linked models to chart or filter on related fields.</p>
                <div class="vd-related-list">
                    @foreach ($this->relatedSourceOptions() as $relation)
                        <label class="vd-related-item">
                            <input type="checkbox" wire:model.live="editRelatedJoins" value="{{ $relation['name'] }}">
                            <span>
                                <strong>{{ $relation['related_label'] }}</strong>
                                <span class="vd-muted">{{ $relation['relation_type'] ?? $relation['type'] }} · {{ $relation['name'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endif
    </section>

    @if ($editSourceKey !== '')
        <section class="vd-builder-section vd-fields-section">
            <header class="vd-section-header">
                <h4 class="vd-section-header-title">2. {{ $fieldBlueprint['label'] ?? 'Chart' }} fields</h4>
                @if ($visualType === 'table')
                    <p class="vd-section-header-desc">Choose visible columns under Optional tweaks, or leave them empty to show every column.</p>
                @else
                    <p class="vd-section-header-desc">{{ $fieldBlueprint['intro'] ?? 'Bind source columns to this chart.' }}</p>
                @endif
            </header>

            @if ($this->allFieldOptions() !== [])
                @foreach ($fieldBlueprint['slots'] ?? [] as $slot)
                    @php($wireProperty = $slot['wire'] ?? 'editSourceField')
                    @php($fieldOptions = $this->fieldOptionsForSlot($slot))

                    <label class="vd-field vd-field-slot" wire:key="field-slot-{{ $slot['id'] }}-{{ $visualType }}">
                        <span class="vd-field-slot-label">
                            <span>{{ $slot['label'] }}</span>
                            @if ($slot['optional'] ?? false)
                                <span class="vd-field-slot-optional">Optional</span>
                            @elseif ($slot['required'] ?? false)
                                <span class="vd-field-slot-optional">Required</span>
                            @endif
                        </span>
                        @if (filled($slot['description'] ?? null))
                            <span class="vd-field-slot-help">{{ $slot['description'] }}</span>
                        @endif
                        <select wire:model.live.debounce.{{ $fieldDebounceMs }}ms="{{ $wireProperty }}" class="vd-input">
                            <option value="">{{ $slot['placeholder'] ?? 'Select a field…' }}</option>
                            @foreach ($fieldOptions as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                @endforeach

                @if ($this->supportsTimeBucket())
                    <label class="vd-field vd-field-slot">
                        <span class="vd-field-slot-label">
                            <span>Time bucket</span>
                        </span>
                        <span class="vd-field-slot-help">How to group the selected date field.</span>
                        <select wire:model.live="editTimeGrain" class="vd-input">
                            @foreach ($this->timeGrainOptions() as $grain => $grainLabel)
                                <option value="{{ $grain }}">{{ $grainLabel }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif

                @if ($this->supportsPeriodGrains())
                    <fieldset class="vd-field vd-period-grains">
                        <span class="vd-field-slot-label">
                            <span>Viewer period buttons</span>
                            <span class="vd-field-slot-optional">Optional</span>
                        </span>
                        <span class="vd-field-slot-help">Day / Week / Month / Year toggles on the chart header.</span>
                        <div class="vd-period-grain-list">
                            @foreach ($this->periodGrainOptions() as $grain => $grainLabel)
                                <label class="vd-period-grain-item">
                                    <input type="checkbox" wire:model.live="editPeriodGrains" value="{{ $grain }}">
                                    <span>{{ $grainLabel }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif

                @php($aggregationConfig = $fieldBlueprint['aggregation'] ?? ['visible' => true])
                @php($measureFilled = filled($this->editSourceField))
                @php($showAggregation = ($aggregationConfig['visible'] ?? true) && $measureFilled)

                @if ($showAggregation)
                    <label class="vd-field vd-field-slot">
                        <span class="vd-field-slot-label">
                            <span>{{ $aggregationConfig['label'] ?? 'How to summarize' }}</span>
                        </span>
                        @if (filled($aggregationConfig['description'] ?? null))
                            <span class="vd-field-slot-help">{{ $aggregationConfig['description'] }}</span>
                        @endif
                        <select wire:model.live.debounce.{{ $fieldDebounceMs }}ms="editAggregation" class="vd-input">
                            @foreach ($this->aggregationOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
            @else
                <p class="vd-muted">This source has no readable fields yet.</p>
            @endif
        </section>

        <section class="vd-transform-section">
            <h4 class="vd-transform-heading">Include rows</h4>
            <p class="vd-muted">Optional. Keep all rows, or add a rule such as Status equals New.</p>

            <div class="vd-transform-list">
                @foreach ($editTransforms as $index => $transform)
                    @php($filterField = (string) ($transform['field'] ?? ''))
                    @php($valueOptions = $filterField !== '' ? $this->filterValueOptions($filterField) : [])
                    @php($valueDisabled = in_array($transform['operator'] ?? '', ['is_empty', 'is_not_empty', 'is_null', 'is_not_null'], true))
                    <div class="vd-transform-row vd-filter-row" wire:key="transform-filter-{{ $index }}">
                        <span class="vd-filter-prefix">Get rows where</span>
                        <select wire:model.live.debounce.{{ $fieldDebounceMs }}ms="editTransforms.{{ $index }}.field" class="vd-input">
                            <option value="">Column…</option>
                            @foreach ($this->labeledFieldOptions() as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <select wire:model.live.debounce.{{ $fieldDebounceMs }}ms="editTransforms.{{ $index }}.operator" class="vd-input">
                            @foreach ($this->filterOperatorOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @include('visual-dashboards::livewire.partials.visual-filter-value-combo', [
                            'index' => $index,
                            'filterField' => $filterField,
                            'valueOptions' => $valueOptions,
                            'disabled' => $valueDisabled,
                            'debounceMs' => $fieldDebounceMs,
                        ])
                        <button type="button" class="vd-icon-btn" wire:click="removeTransformRow({{ $index }})" title="Remove filter">&times;</button>
                    </div>
                @endforeach
            </div>

            <button type="button" class="vd-btn vd-btn-muted vd-btn-sm" wire:click="addTransformRow">+ Add include rule</button>

            @php($transformRowCount = $cachedTransformPreviewRowCount)
            <div
                class="vd-transform-result-wrap"
                wire:loading.class="is-loading"
                wire:target="editTransforms, editSourceKey, editSourceGroup, editRemoveDuplicates, editDedupeColumns, editRenames, editFieldFormats, editTableColumns, editRelatedJoins, editSourceField, editGroupBy, editBreakdown, editAggregation, editPeriodGrains, editTimeGrain"
            >
                <p class="vd-transform-result vd-transform-result-loading" wire:loading wire:target="editTransforms, editSourceKey, editSourceGroup, editRemoveDuplicates, editDedupeColumns, editRenames, editFieldFormats, editTableColumns, editRelatedJoins, editSourceField, editGroupBy, editBreakdown, editAggregation, editPeriodGrains, editTimeGrain">
                    <span class="vd-inline-spinner" aria-hidden="true"></span>
                    Calculating matching rows…
                </p>
                <p class="vd-transform-result" wire:loading.remove wire:target="editTransforms, editSourceKey, editSourceGroup, editRemoveDuplicates, editDedupeColumns, editRenames, editFieldFormats, editTableColumns, editRelatedJoins, editSourceField, editGroupBy, editBreakdown, editAggregation, editPeriodGrains, editTimeGrain">
                    @if ($transformRowCount !== null)
                        <strong>{{ number_format($transformRowCount) }}</strong> rows match
                    @else
                        Select a source to preview row counts.
                    @endif
                </p>
            </div>
        </section>

        <section
            class="vd-transform-section vd-collapsible-section"
            x-data="{ open: @entangle('editOverridesOpen').live }"
        >
            <button
                type="button"
                class="vd-collapsible-trigger"
                @click="open = ! open"
                :aria-expanded="open"
            >
                <span class="vd-collapsible-trigger-copy">
                    <span class="vd-transform-heading">Optional tweaks</span>
                    <span class="vd-collapsible-summary">{{ $this->overridesSummary() }}</span>
                </span>
                <svg class="vd-collapsible-chevron" :class="{ 'is-open': open }" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                </svg>
            </button>

            <div class="vd-collapsible-body" x-show="open" x-collapse>
                <p class="vd-muted">Duplicates, column names, and formats. Skip this unless you need them.</p>

                <div class="vd-override-subsection">
                    <h5 class="vd-override-heading">Remove duplicates</h5>
                    <label class="vd-field vd-field-toggle">
                        <span>Remove duplicate rows</span>
                        <input type="checkbox" wire:model.live="editRemoveDuplicates">
                    </label>

                    @if ($editRemoveDuplicates)
                        <fieldset class="vd-field">
                            <span>Match columns (optional)</span>
                            <p class="vd-muted">Leave empty to compare the entire row.</p>
                            <div class="vd-table-column-list">
                                @foreach ($this->labeledFieldOptions() as $key => $label)
                                    <label class="vd-related-item">
                                        <input type="checkbox" wire:model.live="editDedupeColumns" value="{{ $key }}">
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endif
                </div>

                @if ($visualType === 'table' && $this->allFieldOptions() !== [])
                    <div class="vd-override-subsection">
                        <h5 class="vd-override-heading">Visible columns</h5>
                        <p class="vd-muted">Leave all unchecked to show every column from the source.</p>

                        <div class="vd-table-column-actions">
                            <button type="button" class="vd-btn vd-btn-muted vd-btn-sm" wire:click="selectAllTableColumns">Select all</button>
                            <button type="button" class="vd-btn vd-btn-muted vd-btn-sm" wire:click="clearTableColumns">Clear all</button>
                        </div>

                        <fieldset class="vd-field">
                            <div class="vd-table-column-list">
                                @foreach ($this->labeledFieldOptions() as $key => $label)
                                    <label class="vd-related-item">
                                        <input type="checkbox" wire:model.live="editTableColumns" value="{{ $key }}">
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    </div>
                @endif

                <div class="vd-override-subsection">
                    <h5 class="vd-override-heading">Column renames</h5>
                    <p class="vd-muted">Rename columns for clearer labels in the visual.</p>

                    @foreach ($editRenames as $index => $rename)
                        <div class="vd-transform-row vd-rename-row" wire:key="transform-rename-{{ $index }}">
                            <select wire:model.live.debounce.{{ $fieldDebounceMs }}ms="editRenames.{{ $index }}.from" class="vd-input">
                                <option value="">From column…</option>
                                @foreach ($this->labeledFieldOptions() as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <input type="text" wire:model.live.debounce.{{ $fieldDebounceMs }}ms="editRenames.{{ $index }}.to" class="vd-input" placeholder="New name">
                            <button type="button" class="vd-icon-btn" wire:click="removeRenameRow({{ $index }})" title="Remove rename">&times;</button>
                        </div>
                    @endforeach

                    <button type="button" class="vd-btn vd-btn-muted vd-btn-sm" wire:click="addRenameRow">+ Rename column</button>
                </div>

                @if ($this->resultFieldOptions() !== [])
                    <div class="vd-override-subsection">
                        <h5 class="vd-override-heading">Field formatting</h5>
                        <p class="vd-muted">Format values in the result set — for example, turn a timestamp into a readable date.</p>

                        @foreach ($editFieldFormats as $index => $formatRow)
                            @php($selectedFunction = (string) ($formatRow['function'] ?? ''))
                            <div class="vd-format-row" wire:key="transform-format-{{ $index }}">
                                <select wire:model.live.debounce.{{ $fieldDebounceMs }}ms="editFieldFormats.{{ $index }}.field" class="vd-input">
                                    <option value="">Column…</option>
                                    @foreach ($this->resultFieldOptions() as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <select wire:model.live.debounce.{{ $fieldDebounceMs }}ms="editFieldFormats.{{ $index }}.function" class="vd-input">
                                    <option value="">Function…</option>
                                    @foreach ($this->fieldFormatFunctionOptions() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @if ($selectedFunction === 'date')
                                    <select wire:model.live.debounce.{{ $fieldDebounceMs }}ms="editFieldFormats.{{ $index }}.format" class="vd-input">
                                        <option value="">Date format…</option>
                                        @foreach ($this->fieldFormatDatePresets() as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <span class="vd-format-spacer" aria-hidden="true"></span>
                                @endif
                                <button type="button" class="vd-icon-btn" wire:click="removeFieldFormatRow({{ $index }})" title="Remove format">&times;</button>
                                @if ($selectedFunction === 'date' && ($formatRow['format'] ?? '') === 'custom')
                                    <input
                                        type="text"
                                        wire:model.live.debounce.{{ $fieldDebounceMs }}ms="editFieldFormats.{{ $index }}.custom_format"
                                        class="vd-input vd-format-custom"
                                        placeholder="Custom PHP date format (e.g. F j, Y)"
                                    >
                                @endif
                            </div>
                        @endforeach

                        <button type="button" class="vd-btn vd-btn-muted vd-btn-sm" wire:click="addFieldFormatRow">+ Format field</button>
                    </div>
                @endif
            </div>
        </section>

        <div class="vd-panel-actions">
            @if ($panelNotice !== '')
                <p class="vd-panel-notice" role="status">{{ $panelNotice }}</p>
            @endif
            <button type="button" class="vd-btn vd-btn-muted" wire:click="cancelDataTransform">Reset tweaks</button>
            <button
                type="button"
                class="vd-btn vd-btn-primary"
                wire:click="saveDataSource"
                @disabled(! $this->hasValidDataSourceSelection())
            >Save chart</button>
        </div>
    @endif
</div>

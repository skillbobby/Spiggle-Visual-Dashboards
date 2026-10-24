@php
    $dashboardFilters = $dashboardFilters ?? [];
    $dashboardFilterOptions = $dashboardFilterOptions ?? [];
    $dashboardFilterFields = $dashboardFilterFields ?? [];
    $canEditFilters = $canEditFilters ?? false;
@endphp

@if ($dashboardFilters !== [] || $canEditFilters)
    <div class="vd-filter-bar">
        @foreach ($dashboardFilters as $filter)
            <label class="vd-filter-control">
                <span>{{ $filter['label'] }}</span>
                @if ($filter['control'] === 'date')
                    <input
                        type="date"
                        class="vd-input"
                        wire:model.live="filterValues.{{ $filter['id'] }}"
                    >
                @else
                    <select class="vd-input" wire:model.live="filterValues.{{ $filter['id'] }}">
                        <option value="">All</option>
                        @foreach ($dashboardFilterOptions[$filter['id']] ?? [] as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                @endif
                @if ($canEditFilters)
                    <button
                        type="button"
                        class="vd-filter-remove"
                        wire:click="removeDashboardFilter('{{ $filter['id'] }}')"
                    >Remove</button>
                @endif
            </label>
        @endforeach

        @if ($canEditFilters)
            <div class="vd-filter-editor">
                @if ($filterEditorOpen)
                    <label class="vd-field">
                        <span>Label</span>
                        <input type="text" class="vd-input" wire:model="newFilterLabel" placeholder="Hospitals">
                    </label>
                    <label class="vd-field">
                        <span>Control</span>
                        <select class="vd-input" wire:model="newFilterControl">
                            <option value="select">Dropdown</option>
                            <option value="date">Date</option>
                        </select>
                    </label>
                    <label class="vd-field">
                        <span>Column</span>
                        <select class="vd-input" wire:model="newFilterField">
                            <option value="">Choose a column</option>
                            @foreach ($dashboardFilterFields as $field => $label)
                                <option value="{{ $field }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="button" class="vd-btn vd-btn-primary" wire:click="addDashboardFilter">Save filter</button>
                    <button type="button" class="vd-btn vd-btn-muted" wire:click="$set('filterEditorOpen', false)">Cancel</button>
                    @if ($dashboardFilterFields === [])
                        <p class="vd-muted">Add a visual with a data source first. Filter columns come from those visuals.</p>
                    @endif
                @else
                    <button type="button" class="vd-btn vd-btn-muted" wire:click="$set('filterEditorOpen', true)">Add filter</button>
                @endif
            </div>
        @endif
    </div>
@endif

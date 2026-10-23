@props([
    'index',
    'filterField' => '',
    'valueOptions' => [],
    'disabled' => false,
])

<div
    class="vd-filter-value-combo"
    wire:key="filter-value-{{ $index }}-{{ $filterField }}"
    x-data="vdFilterValueCombo({
        value: @entangle('editTransforms.' . $index . '.value').live,
        options: @js($valueOptions),
        disabled: @js($disabled),
    })"
    @click.outside="close()"
>
    <div
        class="vd-combobox-trigger vd-input"
        :class="{ 'is-open': open, 'is-disabled': disabled }"
        role="button"
        tabindex="0"
        @click="toggle()"
        @keydown.enter.prevent="toggle()"
        @keydown.space.prevent="toggle()"
        :aria-expanded="open"
        aria-haspopup="listbox"
    >
        <span class="vd-combobox-value">
            <template x-if="hasValue">
                <span class="vd-combobox-chip">
                    <span class="vd-combobox-chip-label" x-text="displayLabel"></span>
                    <button
                        type="button"
                        class="vd-combobox-chip-remove"
                        @click.stop="clear($event)"
                        aria-label="Remove value"
                    >&times;</button>
                </span>
            </template>
            <span x-show="! hasValue" class="vd-combobox-placeholder">Value</span>
        </span>
        <svg class="vd-combobox-chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
        </svg>
    </div>

    <div class="vd-combobox-dropdown" x-show="open" x-cloak>
        <input
            type="text"
            class="vd-combobox-search"
            x-ref="searchInput"
            x-model="search"
            placeholder="Start typing to search…"
            @keydown="onSearchKeydown($event)"
            autocomplete="off"
        >
        <div class="vd-combobox-separator" aria-hidden="true"></div>
        <ul class="vd-combobox-options" role="listbox">
            <template x-for="option in filteredOptionList" :key="option.value">
                <li>
                    <button
                        type="button"
                        class="vd-combobox-option"
                        :class="{ 'is-selected': String(value) === String(option.value) }"
                        @click="select(option.value)"
                        x-text="option.label"
                    ></button>
                </li>
            </template>
            <li x-show="showCustomOption">
                <button type="button" class="vd-combobox-option vd-combobox-option-custom" @click="commitCustom()">
                    Use custom value: <strong x-text="customSearchValue"></strong>
                </button>
            </li>
            <li x-show="open && filteredOptionList.length === 0 && ! showCustomOption && search.trim() === ''" class="vd-combobox-empty">
                Type to search or enter a custom value
            </li>
        </ul>
    </div>
</div>

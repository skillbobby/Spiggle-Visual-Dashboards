@script
<script>
    Alpine.data('vdFilterValueCombo', ({ value, options, disabled }) => ({
        value,
        options: options ?? {},
        disabled: disabled ?? false,
        open: false,
        search: '',

        get hasValue() {
            return this.value !== null && this.value !== undefined && String(this.value).length > 0;
        },

        get displayLabel() {
            if (! this.hasValue) {
                return '';
            }

            const key = String(this.value);

            return this.options[key] ?? key;
        },

        get filteredOptionList() {
            const query = this.search.trim().toLowerCase();

            return Object.entries(this.options)
                .filter(([optionValue, optionLabel]) => {
                    if (! query) {
                        return true;
                    }

                    return optionValue.toLowerCase().includes(query)
                        || String(optionLabel).toLowerCase().includes(query);
                })
                .map(([optionValue, optionLabel]) => ({
                    value: optionValue,
                    label: optionLabel,
                }));
        },

        get customSearchValue() {
            return this.search.trim();
        },

        get showCustomOption() {
            const query = this.customSearchValue;

            if (! query) {
                return false;
            }

            return ! Object.keys(this.options).some(
                (key) => key.toLowerCase() === query.toLowerCase(),
            );
        },

        toggle() {
            if (this.disabled) {
                return;
            }

            this.open = ! this.open;

            if (this.open) {
                this.$nextTick(() => this.$refs.searchInput?.focus());
            } else {
                this.search = '';
            }
        },

        close() {
            this.open = false;
            this.search = '';
        },

        select(nextValue) {
            this.value = nextValue;
            this.close();
        },

        clear(event) {
            event?.stopPropagation();
            this.value = '';
            this.search = '';
        },

        commitCustom() {
            const query = this.customSearchValue;

            if (! query) {
                return;
            }

            const exact = Object.keys(this.options).find(
                (key) => key.toLowerCase() === query.toLowerCase(),
            );

            this.select(exact ?? query);
        },

        onSearchKeydown(event) {
            if (event.key === 'Escape') {
                this.close();

                return;
            }

            if (event.key !== 'Enter') {
                return;
            }

            event.preventDefault();

            const query = this.customSearchValue;

            if (! query) {
                return;
            }

            if (this.showCustomOption) {
                this.commitCustom();

                return;
            }

            if (this.filteredOptionList.length === 1) {
                this.select(this.filteredOptionList[0].value);

                return;
            }

            const exact = Object.keys(this.options).find(
                (key) => key.toLowerCase() === query.toLowerCase(),
            );

            this.select(exact ?? query);
        },
    }));
</script>
@endscript

<div
    x-data="dashletGrid({
        columns: @js($columns),
        cellHeight: @js($cellHeight),
        saveDebounceMs: @js($saveDebounceMs),
        items: @js($items),
        presentationMode: @js($presentationMode ?? false),
    })"
    x-on:dashlet-grid-refresh.window="refreshFromServer()"
    x-on:dashlet-grid-refresh="refreshFromServer()"
    @class(['dashlet-grid-shell', 'dashlet-grid-presentation' => ($presentationMode ?? false)])
>
    <div class="dashlet-grid-toolbar" x-show="! presentationMode">
        <p class="dashlet-grid-help">Drag card headers to reorder. Resize from the bottom-right handle. Cards wrap to the next row and never cover each other. Layout saves automatically.</p>
    </div>

    @if ($items === [])
        <div class="dashlet-grid-empty">
            <div class="dashlet-grid-empty-icon" aria-hidden="true">📊</div>
            <h3 class="dashlet-grid-empty-title">No dashlets yet</h3>
            <p class="dashlet-grid-empty-copy">Add your first dashlet with the <strong>Add dashlet</strong> button above. Start with a data model such as sample metrics and a KPI card.</p>
        </div>
    @else
        <div class="dashlet-grid" :style="gridStyle()" :class="{ 'is-dragging': drag || resize }">
            <template x-for="item in items" :key="item.id">
                <div
                    class="dashlet-grid-item"
                    :class="{
                        'has-border': item.payload?.visual?.border !== false,
                        'is-active': drag?.item?.id === item.id || resize?.item?.id === item.id,
                        'is-hero': item.payload?.type === 'hero',
                        'is-stat-card': item.payload?.type === 'stat_card',
                    }"
                    :style="itemStyle(item)"
                >
                    <div
                        class="dashlet-grid-item-header"
                        x-show="item.payload?.type !== 'hero'"
                        :class="{ 'is-readonly': presentationMode }"
                        @mousedown="! presentationMode && startDrag($event, item)"
                    >
                        <div class="dashlet-grid-item-titles">
                            <strong x-text="item.payload?.heading || item.payload?.title || 'Dashlet'"></strong>
                            <span class="dashlet-grid-item-subheading" x-show="item.payload?.subheading" x-text="item.payload?.subheading"></span>
                        </div>
                        <div class="dashlet-grid-item-actions" @mousedown.stop.prevent>
                            <div
                                class="dashlet-grain"
                                x-show="item.payload?.interactive?.period_filter"
                            >
                                <template x-for="(label, grain) in item.payload?.interactive?.grains || {}" :key="grain">
                                    <button
                                        type="button"
                                        class="dashlet-grain-btn"
                                        :class="{ 'is-active': (item.payload?.interactive?.grain || 'year') === grain }"
                                        @click.stop="applyGrain(item, grain)"
                                        x-text="label"
                                    ></button>
                                </template>
                            </div>
                            <template x-if="! presentationMode">
                                <div class="dashlet-grid-item-edit-actions">
                                    <button
                                        type="button"
                                        class="dashlet-grid-btn dashlet-grid-btn-muted"
                                        @click.stop="$wire.openEditDashlet(Number(item.id))"
                                    >Edit</button>
                                    <button
                                        type="button"
                                        class="dashlet-grid-btn"
                                        @click.stop="$wire.openDeleteDashlet(Number(item.id))"
                                    >Remove</button>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="dashlet-grid-item-body">
                        <template x-if="item.payload?.type === 'hero'">
                            <div class="dashlet-hero">
                                <div class="dashlet-hero-row">
                                    <div class="dashlet-hero-copy">
                                        <p class="dashlet-hero-date" x-text="item.payload.month_label"></p>
                                        <h2 class="dashlet-hero-hello" x-text="item.payload.greeting"></h2>
                                    </div>
                                    @if ($presentationMode ?? false)
                                        <button
                                            type="button"
                                            class="dashlet-hero-connect"
                                            @mousedown.stop.prevent
                                            @click="window.dispatchEvent(new CustomEvent('open-connect-service'))"
                                        >+ Connect Service</button>
                                    @endif
                                </div>
                            </div>
                        </template>

                        <template x-if="item.payload?.type === 'stat_card'">
                            <div class="dashlet-stat-card">
                                @include('visual-dashboards::livewire.partials.dashlet-kpi-card-alpine')
                                <div
                                    class="dashlet-stat-spark"
                                    x-show="(item.payload.sparkline || []).length"
                                    x-effect="(item.payload.sparkline || []).length && renderSpark($el, item.payload)"
                                ></div>
                            </div>
                        </template>

                        <template x-if="item.payload?.type === 'insight_list'">
                            <div class="dashlet-insights">
                                <template x-for="(insight, index) in item.payload.items || []" :key="index">
                                    <article class="dashlet-insight">
                                        <strong x-text="insight.title"></strong>
                                        <p x-text="insight.body"></p>
                                        <div class="dashlet-insight-row">
                                            <span class="dashlet-insight-action" x-text="insight.action_label || 'View details'"></span>
                                            <span class="dashlet-insight-save" x-text="insight.savings_label"></span>
                                        </div>
                                    </article>
                                </template>
                            </div>
                        </template>

                        <template x-if="item.payload?.type === 'table'">
                            <div class="dashlet-table-wrap">
                                <table class="dashlet-table">
                                    <thead>
                                        <tr>
                                            <template x-for="column in tableColumns(item.payload)" :key="column">
                                                <th x-text="column"></th>
                                            </template>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="(row, rowIndex) in item.payload.rows || []" :key="rowIndex">
                                            <tr>
                                                <template x-for="column in tableColumns(item.payload)" :key="column">
                                                    <td>
                                                        <span
                                                            x-show="column === 'Change' && row[column]"
                                                            class="dashlet-chip"
                                                            :class="Number(row.up) ? 'is-up' : 'is-down'"
                                                            x-text="row[column]"
                                                        ></span>
                                                        <span x-show="column !== 'Change'" x-text="row[column]"></span>
                                                    </td>
                                                </template>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </template>

                        <template x-if="isChartType(item.payload?.type)">
                            <div class="dashlet-chart-wrap" :class="{ 'has-center': item.payload?.center }" wire:ignore>
                                <div class="dashlet-chart-host" x-effect="renderChart($el, item.payload)"></div>
                                <div class="dashlet-donut-center" x-show="item.payload?.center">
                                    <span
                                        class="dashlet-logo"
                                        :style="'background:' + (item.payload.center?.color || '#22c55e')"
                                        x-text="item.payload.center?.initials || 'AI'"
                                    ></span>
                                    <strong x-text="item.payload.center?.name"></strong>
                                    <span x-text="item.payload.center?.detail"></span>
                                </div>
                                <div class="dashlet-summary" x-show="item.payload?.summary">
                                    <template x-for="(value, label) in item.payload.summary || {}" :key="label">
                                        <div :class="{ 'is-down': label === 'Best', 'is-up': label === 'Worst' }">
                                            <strong x-text="label + ':'"></strong>
                                            <span x-text="value"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <template x-if="item.payload?.type === 'container'">
                            <div class="dashlet-container-placeholder">Container dashlets render nested children (Pro).</div>
                        </template>
                    </div>

                    <button type="button" class="dashlet-resize-handle" x-show="! presentationMode" @mousedown.stop.prevent="startResize($event, item)"></button>
                </div>
            </template>
        </div>
    @endif

    @unless ($presentationMode ?? false)
        <x-filament-actions::modals />
    @endunless
</div>

@assets
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.1"></script>
@if (class_exists(\Filament\Support\Facades\FilamentAsset::class))
    <link rel="stylesheet" href="{{ \Filament\Support\Facades\FilamentAsset::getStyleHref('dashlet-grid') }}" data-navigate-track />
@endif
@endassets

@script
<script>
    Alpine.data('dashletGrid', ({ columns, cellHeight, saveDebounceMs, items, presentationMode = false }) => ({
        columns,
        cellHeight,
        saveDebounceMs,
        presentationMode,
        items: Array.isArray(items) ? items : [],
        drag: null,
        resize: null,
        charts: {},
        saveTimer: null,
        _onDrag: null,
        _endDrag: null,
        _onResize: null,
        _endResize: null,

        init() {
            this.items = this.resolveItems(this.items);
            this._onDrag = (event) => this.onDrag(event);
            this._endDrag = () => this.endDrag();
            this._onResize = (event) => this.onResize(event);
            this._endResize = () => this.endResize();
        },

        gridStyle() {
            const maxRow = this.items.reduce((max, item) => Math.max(max, (item.y || 0) + (item.h || 2)), 8);

            return `grid-template-columns: repeat(${this.columns}, minmax(0, 1fr)); grid-auto-rows: ${this.cellHeight}px; grid-template-rows: repeat(${maxRow}, ${this.cellHeight}px);`;
        },

        itemStyle(item) {
            const x = Math.max(0, Number(item.x) || 0);
            const y = Math.max(0, Number(item.y) || 0);
            const w = Math.max(2, Math.min(this.columns, Number(item.w) || 2));
            const h = Math.max(2, Number(item.h) || 2);

            return `grid-column: ${x + 1} / span ${w}; grid-row: ${y + 1} / span ${h};`;
        },

        tableColumns(payload) {
            if (Array.isArray(payload?.columns) && payload.columns.length) {
                return payload.columns;
            }

            const rows = payload?.rows || [];

            if (! rows.length) {
                return [];
            }

            return Object.keys(rows[0]).filter((column) => column !== 'up');
        },

        isChartType(type) {
            return ! ['hero', 'stat_card', 'insight_list', 'table', 'container'].includes(type);
        },

        collides(a, b) {
            if (! a || ! b || Number(a.id) === Number(b.id)) {
                return false;
            }

            return a.x < b.x + b.w && a.x + a.w > b.x && a.y < b.y + b.h && a.y + a.h > b.y;
        },

        overlapsAny(candidate, others) {
            return others.some((item) => this.collides(candidate, item));
        },

        firstFreeSlot(item, others) {
            const width = Math.max(2, Math.min(this.columns, Number(item.w) || 2));
            const height = Math.max(2, Number(item.h) || 2);

            for (let y = 0; y < 250; y++) {
                for (let x = 0; x <= this.columns - width; x++) {
                    const candidate = { ...item, x, y, w: width, h: height };

                    if (! this.overlapsAny(candidate, others)) {
                        return { x, y, w: width, h: height };
                    }
                }
            }

            return { x: 0, y: 0, w: width, h: height };
        },

        resolveItems(items) {
            const placed = [];

            items.forEach((item) => {
                const width = Math.max(2, Math.min(this.columns, Number(item.w) || 2));
                const height = Math.max(2, Number(item.h) || 2);
                const candidate = {
                    ...item,
                    x: Math.max(0, Number(item.x) || 0),
                    y: Math.max(0, Number(item.y) || 0),
                    w: width,
                    h: height,
                };

                if (candidate.x + width > this.columns || this.overlapsAny(candidate, placed)) {
                    const slot = this.firstFreeSlot(candidate, placed);
                    candidate.x = slot.x;
                    candidate.y = slot.y;
                }

                placed.push(candidate);
            });

            return placed;
        },

        canPlace(item, x, y, w, h) {
            const others = this.items.filter((candidate) => Number(candidate.id) !== Number(item.id));
            const next = { ...item, x, y, w, h };

            return x >= 0 && y >= 0 && x + w <= this.columns && ! this.overlapsAny(next, others);
        },

        async refreshFromServer() {
            this.destroyCharts();

            const nextItems = await $wire.refreshLayout();

            if (Array.isArray(nextItems)) {
                this.items = this.resolveItems(nextItems);
            }
        },

        startDrag(event, item) {
            if (event.button !== 0 || event.target.closest('button, .dashlet-grid-item-actions')) {
                return;
            }

            event.preventDefault();

            this.drag = {
                item,
                startX: event.clientX,
                startY: event.clientY,
                originX: Number(item.x) || 0,
                originY: Number(item.y) || 0,
            };

            window.addEventListener('mousemove', this._onDrag);
            window.addEventListener('mouseup', this._endDrag);
        },

        onDrag(event) {
            if (! this.drag) {
                return;
            }

            const colWidth = Math.max(40, (this.$el.querySelector('.dashlet-grid')?.clientWidth || 960) / this.columns);
            const deltaX = Math.round((event.clientX - this.drag.startX) / colWidth);
            const deltaY = Math.round((event.clientY - this.drag.startY) / this.cellHeight);
            const nextX = Math.max(0, Math.min(this.columns - (this.drag.item.w || 2), this.drag.originX + deltaX));
            const nextY = Math.max(0, this.drag.originY + deltaY);

            if (this.canPlace(this.drag.item, nextX, nextY, this.drag.item.w || 2, this.drag.item.h || 2)) {
                this.drag.item.x = nextX;
                this.drag.item.y = nextY;
            }
        },

        endDrag() {
            if (! this.drag) {
                return;
            }

            this.items = this.resolveItems(this.items);
            this.queuePersistLayout();
            this.drag = null;
            window.removeEventListener('mousemove', this._onDrag);
            window.removeEventListener('mouseup', this._endDrag);
        },

        startResize(event, item) {
            if (event.button !== 0) {
                return;
            }

            this.resize = {
                item,
                startX: event.clientX,
                startY: event.clientY,
                originW: Number(item.w) || 2,
                originH: Number(item.h) || 2,
            };

            window.addEventListener('mousemove', this._onResize);
            window.addEventListener('mouseup', this._endResize);
        },

        onResize(event) {
            if (! this.resize) {
                return;
            }

            const colWidth = Math.max(40, (this.$el.querySelector('.dashlet-grid')?.clientWidth || 960) / this.columns);
            const deltaW = Math.round((event.clientX - this.resize.startX) / colWidth);
            const deltaH = Math.round((event.clientY - this.resize.startY) / this.cellHeight);
            const maxW = this.columns - (Number(this.resize.item.x) || 0);
            const nextW = Math.max(2, Math.min(maxW, this.resize.originW + deltaW));
            const nextH = Math.max(2, this.resize.originH + deltaH);

            if (this.canPlace(this.resize.item, this.resize.item.x || 0, this.resize.item.y || 0, nextW, nextH)) {
                this.resize.item.w = nextW;
                this.resize.item.h = nextH;
            }
        },

        endResize() {
            if (! this.resize) {
                return;
            }

            this.items = this.resolveItems(this.items);
            this.queuePersistLayout();
            this.resize = null;
            window.removeEventListener('mousemove', this._onResize);
            window.removeEventListener('mouseup', this._endResize);
        },

        queuePersistLayout() {
            clearTimeout(this.saveTimer);

            this.saveTimer = setTimeout(async () => {
                const payload = this.items.map((item) => ({
                    id: Number(item.id),
                    x: Number(item.x) || 0,
                    y: Number(item.y) || 0,
                    w: Number(item.w) || 2,
                    h: Number(item.h) || 2,
                }));

                const nextItems = await $wire.updateLayout(payload);

                if (Array.isArray(nextItems)) {
                    this.items = this.resolveItems(nextItems);
                }
            }, this.saveDebounceMs);
        },

        async applyGrain(item, grain) {
            this.destroyCharts();

            const nextItems = await $wire.setGrain(Number(item.id), grain);

            if (Array.isArray(nextItems)) {
                this.items = this.resolveItems(nextItems);
            }
        },

        destroyCharts() {
            Object.values(this.charts).forEach((chart) => {
                try {
                    if (chart && typeof chart.destroy === 'function') {
                        chart.destroy();
                    }
                } catch (error) {
                    // chart library already tore the instance down
                }
            });
            this.charts = {};
        },

        renderChart(host, payload, attempt = 0) {
            if (! host || ! payload) {
                return;
            }

            if (! window.ApexCharts) {
                if (attempt < 40) {
                    window.setTimeout(() => this.renderChart(host, payload, attempt + 1), 50);
                }

                return;
            }

            const chartId = payload.id || host;

            if (this.charts[chartId] && typeof this.charts[chartId].destroy === 'function') {
                this.charts[chartId].destroy();
            }

            host.innerHTML = '';
            this.charts[chartId] = this.renderApex(host, payload);
        },

        renderApex(host, payload) {
            const radial = ['pie', 'donut'].includes(payload.type);
            const scatter = payload.type === 'scatter' || Boolean(payload.chart?.scatter);
            const stacked = Boolean(payload.chart?.stacked) || payload.type === 'stacked_column';
            const datasets = payload.chart?.datasets || [];
            const labels = payload.chart?.labels || [];
            const color = payload.visual?.color || '#f59e0b';
            const palette = payload.chart?.colors || [color, '#3b82f6', '#10b981', '#f43f5e', '#8b5cf6', '#64748b'];
            const typeMap = {
                column: 'bar',
                stacked_column: 'bar',
                bar: 'bar',
                line: 'line',
                pie: 'pie',
                donut: 'donut',
                scatter: 'scatter',
            };

            const options = {
                chart: {
                    type: scatter ? 'scatter' : (typeMap[payload.type] || 'bar'),
                    height: host.clientHeight || 260,
                    stacked,
                    toolbar: { show: false },
                    fontFamily: 'inherit',
                    animations: { enabled: false },
                },
                colors: palette,
                labels: radial ? labels : undefined,
                series: radial
                    ? (datasets[0]?.data || []).map((value) => Number(value))
                    : datasets.map((dataset) => ({ name: dataset.label, data: dataset.data })),
                xaxis: radial ? undefined : {
                    categories: scatter ? undefined : labels,
                    type: scatter ? 'numeric' : undefined,
                    title: { text: payload.visual?.x_label || '' },
                },
                yaxis: radial ? undefined : { title: { text: payload.visual?.y_label || '' } },
                legend: { show: radial || datasets.length > 1, position: 'bottom' },
                dataLabels: { enabled: false },
                stroke: { width: payload.type === 'line' ? 3 : scatter ? 0 : 0 },
                plotOptions: {
                    bar: { horizontal: payload.type === 'bar', borderRadius: 6, columnWidth: '55%' },
                    pie: { donut: { size: payload.type === 'donut' ? '68%' : '62%' } },
                },
            };

            const chart = new ApexCharts(host, options);
            chart.render();

            return chart;
        },

        renderSpark(host, payload) {
            if (! host || ! window.ApexCharts || ! payload?.sparkline?.length) {
                return;
            }

            const chartId = 'spark-' + (payload.id || host);

            if (this.charts[chartId] && typeof this.charts[chartId].destroy === 'function') {
                this.charts[chartId].destroy();
            }

            host.innerHTML = '';
            const chart = new ApexCharts(host, {
                chart: { type: 'area', height: 42, sparkline: { enabled: true }, toolbar: { show: false }, animations: { enabled: false } },
                series: [{ data: payload.sparkline.map((value) => Number(value)) }],
                colors: [payload.up ? '#e11d48' : '#16a34a'],
                stroke: { width: 2, curve: 'smooth' },
                fill: { opacity: 0.18 },
                tooltip: { enabled: false },
                dataLabels: { enabled: false },
            });
            chart.render();
            this.charts[chartId] = chart;
        },
    }));
</script>
@endscript

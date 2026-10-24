<div
    x-data="dashletGrid({
        columns: @js($columns),
        cellHeight: @js($cellHeight),
        saveDebounceMs: @js($saveDebounceMs),
        items: @js($items),
        presentationMode: @js($presentationMode ?? false),
        canArrange: @js($canArrange ?? false),
    })"
    x-on:dashlet-grid-refresh.window="refreshFromServer()"
    x-on:dashlet-grid-refresh="refreshFromServer()"
    @class(['dashlet-grid-shell', 'dashlet-grid-presentation' => ($presentationMode ?? false)])
>
    <div class="dashlet-grid-toolbar" x-show="! presentationMode || canArrange">
        <p class="dashlet-grid-help">Drag a card and it snaps into the nearest gap. Drop it on another card to place it in front or behind.</p>
    </div>

    @include('visual-dashboards::livewire.partials.dashboard-filters')

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
                        'is-stack-target': Number(drag?.stack?.id) === Number(item.id),
                        'is-behind': drag?.item?.id === item.id && drag?.stack && ! drag.stack.inFront,
                        'is-hero': item.payload?.type === 'hero',
                        'is-stat-card': item.payload?.type === 'stat_card',
                    }"
                    :data-dashlet-id="item.id"
                    :style="itemStyle(item)"
                >
                    <div class="dashlet-stack-badge" x-show="(drag?.item?.id === item.id && drag?.stack?.inFront) || (Number(drag?.stack?.id) === Number(item.id) && ! drag?.stack?.inFront)" x-text="drag?.stack?.inFront ? 'In front' : 'Behind'"></div>
                    <div
                        class="dashlet-grid-item-header"
                        x-show="item.payload?.type !== 'hero'"
                        :class="{ 'is-readonly': presentationMode && ! canArrange }"
                        @mousedown="(! presentationMode || canArrange) && startDrag($event, item)"
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
                        <template x-if="item.payload?.restricted">
                            <div class="vd-widget-empty">
                                <div class="vd-widget-empty-icon" aria-hidden="true">🔒</div>
                                <p x-text="item.payload?.empty_message"></p>
                            </div>
                        </template>

                        <template x-if="! item.payload?.restricted && item.payload?.type === 'hero'">
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

                        <template x-if="! item.payload?.restricted && item.payload?.type === 'stat_card'">
                            <div class="dashlet-stat-card">
                                @include('visual-dashboards::livewire.partials.dashlet-kpi-card-alpine')
                                <div
                                    class="dashlet-stat-spark"
                                    x-show="(item.payload.sparkline || []).length"
                                    x-effect="(item.payload.sparkline || []).length && renderSpark($el, item.payload)"
                                ></div>
                            </div>
                        </template>

                        <template x-if="! item.payload?.restricted && item.payload?.type === 'insight_list'">
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

                        <template x-if="! item.payload?.restricted && item.payload?.type === 'table'">
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

                        <template x-if="! item.payload?.restricted && isChartType(item.payload?.type)">
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

                        <template x-if="! item.payload?.restricted && item.payload?.type === 'container'">
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

@include('visual-dashboards::livewire.partials.layout-snap-script')
@include('visual-dashboards::livewire.partials.visual-chart-renderer-script')

@script
<script>
    Alpine.data('dashletGrid', ({ columns, cellHeight, saveDebounceMs, items, presentationMode = false, canArrange = false }) => ({
        columns,
        cellHeight,
        saveDebounceMs,
        presentationMode,
        canArrange,
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

            return `grid-column: ${x + 1} / span ${w}; grid-row: ${y + 1} / span ${h}; z-index: ${this.itemZIndex(item)};`;
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

        itemZIndex(item) {
            if (this.drag?.item && Number(this.drag.item.id) === Number(item.id)) {
                if (this.drag.stack) {
                    return this.drag.stack.inFront ? 80 : 1;
                }

                return 40;
            }

            return (Number(item.z) || 0) + 2;
        },

        restoreDragSnapshot() {
            if (! this.drag?.snapshot) {
                return;
            }

            const byId = new Map(this.drag.snapshot.map((entry) => [Number(entry.id), entry]));

            this.items.forEach((entry) => {
                if (Number(entry.id) === Number(this.drag.item.id)) {
                    return;
                }

                const snap = byId.get(Number(entry.id));

                if (! snap) {
                    return;
                }

                entry.x = snap.x;
                entry.y = snap.y;
                entry.z = snap.z;
            });
        },

        stackTarget(item) {
            let best = null;
            let bestArea = 0;

            this.items.forEach((other) => {
                if (Number(other.id) === Number(item.id) || ! this.collides(item, other)) {
                    return;
                }

                const width = Math.max(0, Math.min(item.x + item.w, other.x + other.w) - Math.max(item.x, other.x));
                const height = Math.max(0, Math.min(item.y + item.h, other.y + other.h) - Math.max(item.y, other.y));
                const area = width * height;

                if (area > bestArea) {
                    best = other;
                    bestArea = area;
                }
            });

            if (! best || bestArea < Math.min(item.w * item.h, best.w * best.h) * 0.5) {
                return null;
            }

            const element = this.$el.querySelector(`[data-dashlet-id="${Number(best.id)}"]`);

            return {
                item: best,
                rect: element?.getBoundingClientRect?.() || null,
            };
        },

        resolveItems(items) {
            const placed = items.map((item) => ({
                ...item,
                x: Math.max(0, Number(item.x) || 0),
                y: Math.max(0, Number(item.y) || 0),
                w: Math.max(2, Math.min(this.columns, Number(item.w) || 2)),
                h: Math.max(2, Number(item.h) || 2),
                z: Number(item.z) || 0,
            }));

            return window.spigglePackLayout(placed, this.columns, null);
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
                stack: null,
                snapshot: this.items.map((entry) => ({
                    id: Number(entry.id),
                    x: Number(entry.x) || 0,
                    y: Number(entry.y) || 0,
                    z: Number(entry.z) || 0,
                })),
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
            const item = this.drag.item;

            this.restoreDragSnapshot();
            item.x = nextX;
            item.y = nextY;

            const target = this.stackTarget(item);

            if (target) {
                const inFront = ! target.rect || event.clientY < target.rect.top + (target.rect.height / 2);
                this.drag.stack = { id: Number(target.item.id), inFront };

                if (inFront) {
                    item.z = (Number(target.item.z) || 0) + 1;
                } else {
                    item.z = Number(target.item.z) || 0;
                    target.item.z = item.z + 1;
                }

                return;
            }

            this.drag.stack = null;
            item.z = 0;
            window.spigglePackLayout(this.items, this.columns, Number(item.id));
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
                    z: Number(item.z) || 0,
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
            if (! host || ! payload || payload.restricted || ! payload.chart) {
                return;
            }

            if (! window.ApexCharts || typeof window.spiggleRenderDashletChart !== 'function') {
                if (attempt < 40) {
                    window.setTimeout(() => this.renderChart(host, payload, attempt + 1), 50);
                }

                return;
            }

            window.spiggleRenderDashletChart(host, payload, (chart) => {
                this.charts[payload.id || host] = chart;
            });
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

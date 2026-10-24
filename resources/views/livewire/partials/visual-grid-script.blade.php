@include('visual-dashboards::livewire.partials.layout-snap-script')
@script
<script>
    Alpine.data('visualDashboardCanvas', ({ columns, cellHeight, saveDebounceMs, items, selectedVisualId = null }) => ({
        columns,
        cellHeight,
        saveDebounceMs,
        selectedVisualId,
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

            this.$watch('$wire.selectedVisualId', (value) => {
                this.selectedVisualId = value;
            });
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

        chartVariantSignature(type, payload) {
            if (! window.spiggleChartVariantTypes?.includes(type)) {
                return '';
            }

            return payload.visual?.chart_variant || 'basic';
        },

        chartDataSignature(payload) {
            if (! payload || payload.unconfigured) {
                return '';
            }

            const type = payload.type || '';
            const grain = payload.interactive?.grain || '';
            const variant = this.chartVariantSignature(type, payload);
            const numberFormat = payload.visual?.number_format || 'number';
            const currency = payload.visual?.currency || 'USD';

            return String(payload.id || '')
                + '|'
                + type
                + '|'
                + grain
                + '|'
                + variant
                + '|'
                + numberFormat
                + '|'
                + currency
                + '|'
                + JSON.stringify(payload.chart || {});
        },

        mountChart(host, item) {
            if (! host || ! item) {
                return;
            }

            const visualId = Number(item.id);

            if (host._spiggleMountId === visualId) {
                return;
            }

            host._spiggleMountId = visualId;

            const render = () => {
                const current = this.items.find((entry) => Number(entry.id) === visualId);

                if (current) {
                    this.renderChart(host, current.payload);
                }
            };

            render();

            this.$watch(() => {
                const current = this.items.find((entry) => Number(entry.id) === visualId);

                return this.chartDataSignature(current?.payload);
            }, (next, prev) => {
                if (next !== prev) {
                    render();
                }
            });
        },

        resizeChartHost(host) {
            const chart = host?._spiggleChart;

            if (chart && typeof chart.resize === 'function') {
                try {
                    chart.resize();
                } catch (error) {
                    //
                }
            }
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

        isUnconfigured(item) {
            return item?.payload?.unconfigured === true;
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

        itemStateSignature(item) {
            return [
                Number(item.id),
                Number(item.x) || 0,
                Number(item.y) || 0,
                Number(item.w) || 2,
                Number(item.h) || 2,
                Number(item.z) || 0,
                this.chartDataSignature(item.payload),
            ].join('|');
        },

        resolveItems(items) {
            const existingById = new Map(this.items.map((item) => [Number(item.id), item]));
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
                    z: Number(item.z) || 0,
                };

                placed.push(candidate);
            });

            window.spigglePackLayout(placed, this.columns, null);

            return placed.map((candidate) => {
                const existing = existingById.get(Number(candidate.id));

                if (existing && this.itemStateSignature(existing) === this.itemStateSignature(candidate)) {
                    return existing;
                }

                return candidate;
            });
        },

        mergeItemsFromServer(nextItems) {
            if (! Array.isArray(nextItems)) {
                return;
            }

            const normalized = this.resolveItems(nextItems);
            const existingById = new Map(this.items.map((item) => [Number(item.id), item]));

            this.items = normalized.map((incoming) => {
                const existing = existingById.get(Number(incoming.id));

                if (existing && this.itemStateSignature(existing) === this.itemStateSignature(incoming)) {
                    return existing;
                }

                return incoming;
            });
        },

        canPlace(item, x, y, w, h) {
            const others = this.items.filter((candidate) => Number(candidate.id) !== Number(item.id));
            const next = { ...item, x, y, w, h };

            return x >= 0 && y >= 0 && x + w <= this.columns && ! this.overlapsAny(next, others);
        },

        async refreshFromServer() {
            const nextItems = await $wire.refreshLayout();

            this.mergeItemsFromServer(nextItems);
        },

        startDrag(event, item) {
            if (event.button !== 0) {
                return;
            }

            event.preventDefault();

            this.drag = {
                item,
                startX: event.clientX,
                startY: event.clientY,
                originX: Number(item.x) || 0,
                originY: Number(item.y) || 0,
                originW: Number(item.w) || 2,
                originH: Number(item.h) || 2,
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

            const item = this.drag.item;
            const moved = this.drag.snapshot.some((entry) => {
                const current = this.items.find((candidate) => Number(candidate.id) === Number(entry.id));

                return ! current
                    || Number(current.x) !== entry.x
                    || Number(current.y) !== entry.y
                    || Number(current.z) !== entry.z
                    || Number(current.w) !== (Number(item.id) === Number(entry.id) ? this.drag.originW : Number(current.w));
            });

            if (moved || this.drag.stack) {
                this.items = this.resolveItems(this.items);
                this.queuePersistLayout();
            }

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
                originX: Number(item.x) || 0,
                originY: Number(item.y) || 0,
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

            const item = this.resize.item;
            const moved = Number(item.w) !== this.resize.originW
                || Number(item.h) !== this.resize.originH;

            if (moved) {
                this.items = this.resolveItems(this.items);
                this.queuePersistLayout();

                const host = this.$el?.querySelector(`.dashlet-chart-host[data-dashlet-id="${Number(item.id)}"]`);
                this.resizeChartHost(host);
            }

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

                const nextItems = await $wire.updateLayout(payload, true);

                this.mergeItemsFromServer(nextItems);
            }, this.saveDebounceMs);
        },

        async applyGrain(item, grain) {
            const chartId = Number(item.id);
            const nextItems = await $wire.setGrain(chartId, grain);

            this.mergeItemsFromServer(nextItems);
        },

        renderChart(host, payload) {
            if (! host || ! payload || payload?.unconfigured || payload?.restricted) {
                return;
            }

            const signature = this.chartDataSignature(payload);
            const chartId = payload.id || host;

            if (host._spiggleChartSignature === signature && this.charts[chartId]) {
                return;
            }

            host._spiggleChartSignature = signature;

            if (this.charts[chartId] && typeof this.charts[chartId].destroy === 'function') {
                this.charts[chartId].destroy();
            }

            const run = () => window.spiggleRenderDashletChart?.(host, payload, (chart) => {
                this.charts[chartId] = chart;
            });

            if ((payload.type === 'bar' || payload.type === 'funnel') && host.clientHeight < 120) {
                requestAnimationFrame(() => requestAnimationFrame(run));
            } else {
                run();
            }
        },
    }));
</script>
@endscript

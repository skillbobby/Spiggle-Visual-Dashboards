<script>

    window.spiggleChartVariantTypes = @json(\Spiggle\VisualDashboards\Support\ChartVariantCatalog::supportedTypes());



    window.spiggleEffectiveChartVariant = function (type, variant) {

        if (! window.spiggleChartVariantTypes.includes(type)) {

            return 'basic';

        }



        return variant || 'basic';

    };

    window.spigglePrepareRadialBarOptions = function (options, payload, chart, chartHeight, variant) {
        const value = Number(chart.value) || 0;
        const min = Number(chart.min) || 0;
        const max = Number(chart.max) || Math.max(value, 1);
        const percent = Number.isFinite(Number(chart.percent))
            ? Number(chart.percent)
            : Math.min(100, Math.max(0, ((value - min) / Math.max(max - min, 1)) * 100));
        const color = payload.visual?.color || '#3b82f6';
        const seriesLabel = chart.series_label || payload.title || chart.label || 'Value';
        const formatted = chart.formatted || String(value);
        const isSemi = variant === 'semi_circle' || variant === 'live_needle';
        const showName = variant === 'custom_label' || variant === 'live_needle';

        options.chart.type = 'radialBar';
        options.chart.height = chartHeight;
        const isLivelineEngine = payload.visual?.chart_engine === 'liveline';
        const animateGauge = variant === 'live_needle' || isLivelineEngine;
        options.chart.animations = {
            enabled: animateGauge,
            easing: 'easeinout',
            speed: animateGauge ? 750 : 250,
            animateGradually: {
                enabled: animateGauge,
                delay: 120,
            },
            dynamicAnimation: {
                enabled: animateGauge,
                speed: 500,
            },
        };
        options.series = [percent];
        options.labels = [seriesLabel];
        options.colors = [color];
        options.legend = { show: false };
        options.stroke = { lineCap: 'round' };
        options.plotOptions = {
            ...(options.plotOptions || {}),
            radialBar: {
                startAngle: isSemi ? -90 : -135,
                endAngle: isSemi ? 90 : 135,
                hollow: {
                    size: variant === 'live_needle' ? '58%' : '62%',
                },
                track: {
                    background: '#e2e8f0',
                    strokeWidth: '100%',
                    margin: variant === 'bands' ? 8 : 5,
                },
                dataLabels: {
                    show: true,
                    name: {
                        show: showName,
                        offsetY: isSemi ? -10 : -6,
                        fontSize: '13px',
                        fontWeight: 600,
                        color: '#64748b',
                    },
                    value: {
                        show: true,
                        fontSize: variant === 'live_needle' ? '28px' : '22px',
                        fontWeight: 700,
                        offsetY: isSemi ? 10 : 6,
                        color: '#0f172a',
                        formatter: () => formatted,
                    },
                },
            },
        };

        if (variant === 'bands') {
            options.fill = {
                type: 'gradient',
                gradient: {
                    shade: 'light',
                    type: 'horizontal',
                    shadeIntensity: 0.45,
                    inverseColors: false,
                    opacityFrom: 1,
                    opacityTo: 0.95,
                    colorStops: [
                        { offset: 0, color: '#10b981', opacity: 1 },
                        { offset: 55, color: '#f59e0b', opacity: 1 },
                        { offset: 100, color: '#ef4444', opacity: 1 },
                    ],
                },
            };
        }

        if (variant === 'live_needle') {
            options.plotOptions.radialBar.dataLabels.value.color = color;
        }

        options.tooltip = {
            ...(options.tooltip || {}),
            y: {
                formatter: () => formatted,
            },
        };

        return options;
    };

    window.spiggleFormatValue = function (value, format, currency) {
        const number = Number(value);

        if (! Number.isFinite(number)) {
            return String(value ?? '');
        }

        if (format === 'decimal') {
            return number.toLocaleString(undefined, {
                minimumFractionDigits: 1,
                maximumFractionDigits: 1,
            });
        }

        if (format === 'percent') {
            return number.toLocaleString(undefined, {
                minimumFractionDigits: 0,
                maximumFractionDigits: 1,
            }) + '%';
        }

        if (format === 'currency') {
            const symbols = { USD: '$', EUR: '€', GBP: '£', JPY: '¥' };
            const symbol = symbols[String(currency || 'USD').toUpperCase()] || '$';

            return symbol + number.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        }

        return number.toLocaleString(undefined, {
            maximumFractionDigits: 0,
        });
    };

    window.spiggleSparklinePoints = function (values) {
        const series = (values || []).map((value) => Number(value)).filter((value) => Number.isFinite(value));

        if (series.length < 2) {
            return '';
        }

        const min = Math.min(...series);
        const max = Math.max(...series);
        const span = max - min || 1;

        return series.map((value, index) => {
            const x = (index / (series.length - 1)) * 120;
            const y = 32 - ((value - min) / span) * 28;

            return x.toFixed(1) + ',' + y.toFixed(1);
        }).join(' ');
    };

    window.spiggleApplyValueFormatters = function (options, payload, context = {}) {
        const format = payload.visual?.number_format || 'number';
        const currency = payload.visual?.currency || 'USD';
        const horizontalBar = Boolean(context.horizontalBar);
        const fmt = (value) => window.spiggleFormatValue(value, format, currency);

        options.tooltip = {
            ...(options.tooltip || {}),
            y: {
                ...(options.tooltip?.y || {}),
                formatter: fmt,
            },
        };

        if (horizontalBar) {
            options.xaxis = options.xaxis || {};
            options.xaxis.labels = {
                ...(options.xaxis.labels || {}),
                formatter: fmt,
            };
        } else if (options.chart?.type !== 'radialBar' && options.chart?.type !== 'pie' && options.chart?.type !== 'donut') {
            if (Array.isArray(options.yaxis)) {
                options.yaxis = options.yaxis.map((axis) => ({
                    ...axis,
                    labels: {
                        ...(axis.labels || {}),
                        formatter: fmt,
                    },
                }));
            } else {
                options.yaxis = {
                    ...(options.yaxis || {}),
                    labels: {
                        ...(options.yaxis?.labels || {}),
                        formatter: fmt,
                    },
                };
            }
        }

        if (options.dataLabels?.enabled) {
            options.dataLabels = {
                ...options.dataLabels,
                formatter: fmt,
            };
        }

        if (options.chart?.type === 'radialBar') {
            return options;
        }

        if (options.chart?.type === 'pie' || options.chart?.type === 'donut') {
            options.dataLabels = {
                ...(options.dataLabels || {}),
                enabled: options.dataLabels?.enabled !== false,
                formatter: (value, chartContext) => {
                    const total = (chartContext?.w?.globals?.seriesTotals || []).reduce((sum, entry) => sum + Number(entry || 0), 0);
                    const percent = total > 0 ? (Number(value) / total) * 100 : Number(value);

                    return format === 'percent'
                        ? window.spiggleFormatValue(percent, 'percent', currency)
                        : fmt(value);
                },
            };
        }

        return options;
    };

    window.spiggleApplyChartVariant = function (type, variant, options, context) {

        const {

            datasets = [],

            labels = [],

            funnelLabels,

            funnelDatasets,

        } = context;



        variant = window.spiggleEffectiveChartVariant(type, variant);



        if (type === 'donut') {

            options.plotOptions = options.plotOptions || {};

            options.plotOptions.pie = options.plotOptions.pie || {};

            options.plotOptions.pie.donut = {

                ...(options.plotOptions.pie.donut || {}),

                size: '68%',

            };



            if (variant === 'rounded') {

                options.stroke = { show: true, width: 6, lineCap: 'round', colors: ['transparent'] };

                options.plotOptions.pie.expandOnClick = false;

            }



            if (variant === 'rounded_spaced') {

                options.stroke = {

                    show: true,

                    width: 4,

                    lineCap: 'round',

                    colors: Array(labels.length).fill('#ffffff'),

                };

                options.plotOptions.pie.expandOnClick = false;

            }



            return options;

        }



        if (type === 'pie') {

            options.plotOptions = options.plotOptions || {};

            options.plotOptions.pie = options.plotOptions.pie || {};



            if (variant === 'rounded') {

                options.stroke = { show: true, width: 6, lineCap: 'round', colors: ['transparent'] };

                options.plotOptions.pie.expandOnClick = false;

            }



            if (variant === 'rounded_spaced') {

                options.stroke = {

                    show: true,

                    width: 4,

                    lineCap: 'round',

                    colors: Array(labels.length).fill('#ffffff'),

                };

                options.plotOptions.pie.expandOnClick = false;

            }



            return options;

        }



        if (type === 'line') {

            options.stroke = { ...(options.stroke || {}), width: 3, curve: 'smooth' };

            options.markers = options.markers || { size: 4, strokeWidth: 0 };



            if (variant === 'straight') {

                options.stroke.curve = 'straight';

            }



            if (variant === 'area') {

                options.stroke.curve = 'smooth';

                options.fill = {

                    type: 'gradient',

                    gradient: {

                        shadeIntensity: 1,

                        opacityFrom: 0.45,

                        opacityTo: 0.05,

                        stops: [0, 90, 100],

                    },

                };

            }



            if (variant === 'data_labels') {

                options.dataLabels = {

                    enabled: true,

                    style: { fontSize: '10px', fontWeight: 600 },

                };

            }



            return options;

        }



        if (type === 'column') {

            options.plotOptions = options.plotOptions || {};

            options.plotOptions.bar = {

                horizontal: false,

                borderRadius: 6,

                columnWidth: '55%',

                ...(options.plotOptions.bar || {}),

            };

            options.xaxis = options.xaxis || {};

            options.xaxis.labels = options.xaxis.labels || {};



            if (variant === 'data_labels') {

                options.dataLabels = {

                    enabled: true,

                    style: { fontSize: '11px', fontWeight: 600 },

                    offsetY: -6,

                };

            }



            if (variant === 'stacked') {

                options.chart.stacked = true;

            }



            if (variant === 'rotated_labels') {

                options.xaxis.labels.rotate = -45;

                options.xaxis.labels.trim = true;

                options.xaxis.labels.maxHeight = 96;

            }



            return options;

        }



        if (type === 'stacked_column') {

            options.chart.stacked = true;

            options.plotOptions = options.plotOptions || {};

            options.plotOptions.bar = {

                horizontal: false,

                borderRadius: 6,

                columnWidth: '55%',

                ...(options.plotOptions.bar || {}),

            };

            options.xaxis = options.xaxis || {};

            options.xaxis.labels = options.xaxis.labels || {};



            if (variant === 'data_labels') {

                options.dataLabels = {

                    enabled: true,

                    style: { fontSize: '11px', fontWeight: 600 },

                    offsetY: -6,

                };

            }



            if (variant === 'rotated_labels') {

                options.xaxis.labels.rotate = -45;

                options.xaxis.labels.trim = true;

                options.xaxis.labels.maxHeight = 96;

            }



            return options;

        }



        if (type === 'bar') {

            options.plotOptions = options.plotOptions || {};

            options.plotOptions.bar = {

                horizontal: true,

                borderRadius: 6,

                barHeight: '70%',

                ...(options.plotOptions.bar || {}),

            };



            if (variant === 'group') {

                options.chart.stacked = false;

                options.plotOptions.bar.columnWidth = datasets.length > 1 ? '80%' : '55%';

            }



            if (variant === 'stacked') {

                options.chart.stacked = true;

            }



            if (variant === 'race' && funnelLabels && funnelDatasets?.[0]) {

                const pairs = funnelLabels.map((label, index) => ({

                    label,

                    value: Number(funnelDatasets[0].data[index] ?? 0),

                })).sort((a, b) => b.value - a.value);



                context.funnelLabels = pairs.map((pair) => pair.label);

                context.funnelDatasets = [{

                    ...funnelDatasets[0],

                    data: pairs.map((pair) => pair.value),

                }];



                options.plotOptions.bar.distributed = true;

                options.chart.animations = {

                    enabled: true,

                    speed: 700,

                    animateGradually: { enabled: true, delay: 120 },

                };

                options.legend = { ...(options.legend || {}), show: false };

            }



            return options;

        }



        if (type === 'funnel') {

            options.plotOptions = options.plotOptions || {};

            options.plotOptions.bar = {

                horizontal: true,

                borderRadius: 6,

                barHeight: variant === 'compact' ? '55%' : '70%',

                isFunnel: true,

                distributed: true,

                ...(options.plotOptions.bar || {}),

            };



            if (variant === 'data_labels') {

                options.dataLabels = {

                    enabled: true,

                    style: { fontSize: '11px', fontWeight: 600 },

                };

            }



            return options;

        }



        if (type === 'radialbar') {
            options.plotOptions = options.plotOptions || {};
            options.plotOptions.radialBar = {
                ...(options.plotOptions.radialBar || {}),
            };

            if (variant === 'bands') {
                options.fill = {
                    type: 'gradient',
                    gradient: {
                        shade: 'light',
                        type: 'horizontal',
                        shadeIntensity: 0.45,
                        inverseColors: false,
                        opacityFrom: 1,
                        opacityTo: 0.95,
                        colorStops: [
                            { offset: 0, color: '#10b981', opacity: 1 },
                            { offset: 55, color: '#f59e0b', opacity: 1 },
                            { offset: 100, color: '#ef4444', opacity: 1 },
                        ],
                    },
                };
            }

            return options;
        }

        return options;

    };




    window.spiggleMonotoneSplinePath = function (pts, straight = false) {
        if (!pts || pts.length === 0) return '';
        if (pts.length === 1) {
            return `M ${pts[0][0].toFixed(2)} ${pts[0][1].toFixed(2)}`;
        }
        let d = `M ${pts[0][0].toFixed(2)} ${pts[0][1].toFixed(2)}`;
        if (straight || pts.length === 2) {
            for (let i = 1; i < pts.length; i++) {
                d += ` L ${pts[i][0].toFixed(2)} ${pts[i][1].toFixed(2)}`;
            }
            return d;
        }
        const n = pts.length;
        const delta = new Array(n - 1);
        const h = new Array(n - 1);
        for (let i = 0; i < n - 1; i++) {
            h[i] = pts[i + 1][0] - pts[i][0];
            delta[i] = h[i] === 0 ? 0 : (pts[i + 1][1] - pts[i][1]) / h[i];
        }
        const m = new Array(n);
        m[0] = delta[0];
        m[n - 1] = delta[n - 2];
        for (let i = 1; i < n - 1; i++) {
            if (delta[i - 1] * delta[i] <= 0) {
                m[i] = 0;
            } else {
                m[i] = (delta[i - 1] + delta[i]) / 2;
            }
        }
        for (let i = 0; i < n - 1; i++) {
            if (delta[i] === 0) {
                m[i] = 0;
                m[i + 1] = 0;
            } else {
                const alpha = m[i] / delta[i];
                const beta = m[i + 1] / delta[i];
                const s2 = alpha * alpha + beta * beta;
                if (s2 > 9) {
                    const sc = 3 / Math.sqrt(s2);
                    m[i] = sc * alpha * delta[i];
                    m[i + 1] = sc * beta * delta[i];
                }
            }
        }
        for (let i = 0; i < n - 1; i++) {
            const hi = h[i];
            const cp1x = (pts[i][0] + hi / 3).toFixed(2);
            const cp1y = (pts[i][1] + m[i] * hi / 3).toFixed(2);
            const cp2x = (pts[i + 1][0] - hi / 3).toFixed(2);
            const cp2y = (pts[i + 1][1] - m[i + 1] * hi / 3).toFixed(2);
            const x2 = pts[i + 1][0].toFixed(2);
            const y2 = pts[i + 1][1].toFixed(2);
            d += ` C ${cp1x} ${cp1y}, ${cp2x} ${cp2y}, ${x2} ${y2}`;
        }
        return d;
    };

    window.spiggleRenderLivelineCanvas = function (host, payload, chartHeight, onRendered) {
        const SVG_NS = 'http://www.w3.org/2000/svg';
        const svgEl = (tag, attrs = {}) => {
            const el = document.createElementNS(SVG_NS, tag);
            for (const [k, v] of Object.entries(attrs)) {
                el.setAttribute(k, String(v));
            }
            return el;
        };

        const hexToRgb = (hex) => {
            const m = String(hex || '').trim().match(/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i);
            if (!m) return [59, 130, 246];
            let h = m[1];
            if (h.length === 3) h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
            return [parseInt(h.slice(0, 2), 16), parseInt(h.slice(2, 4), 16), parseInt(h.slice(4, 6), 16)];
        };

        const lerp = (curr, target, speed, dt = 16.67) => {
            const factor = 1 - Math.pow(1 - speed, dt / 16.67);
            return curr + (target - curr) * factor;
        };

        const loadingY = (t, centerY, amplitude, scroll) => {
            return centerY + amplitude * (
                Math.sin(t * 9.4 + scroll) * 0.55 +
                Math.sin(t * 15.7 + scroll * 1.3) * 0.3 +
                Math.sin(t * 4.2 + scroll * 0.7) * 0.15
            );
        };

        // Reuse existing Liveline instance on the same host for smooth data morphing
        if (host._spiggleLiveline && !host._spiggleLiveline.destroyed) {
            host._spiggleLiveline.updatePayload(payload, chartHeight);
            if (typeof onRendered === 'function') {
                onRendered(host._spiggleLiveline);
            }
            return host._spiggleLiveline;
        }

        const uid = 'vd-ll-' + Math.random().toString(36).slice(2, 9);

        host.innerHTML = '';
        host.style.minHeight = `${chartHeight}px`;
        host.style.height = `${chartHeight}px`;
        host.style.position = 'relative';
        host.style.background = 'transparent';

        const container = document.createElement('div');
        container.className = 'vd-liveline-container';
        container.style.cssText = 'position:relative;width:100%;height:100%;display:flex;flex-direction:column;user-select:none;overflow:hidden;background:transparent !important;';

        const canvasWrap = document.createElement('div');
        canvasWrap.style.cssText = 'position:relative;flex:1;width:100%;min-height:180px;background:transparent !important;';

        const svg = svgEl('svg', {
            class: 'spiggle-liveline-svg',
            width: '100%',
            height: '100%',
            style: 'display:block;width:100%;height:100%;background:transparent !important;cursor:crosshair;overflow:visible;',
        });

        const defs = svgEl('defs');
        const clipLeftRect = svgEl('rect', { x: 0, y: 0, width: 2000, height: 2000 });
        const clipRightRect = svgEl('rect', { x: 2000, y: 0, width: 0, height: 2000 });
        const clipLeft = svgEl('clipPath', { id: `${uid}-clip-left` });
        const clipRight = svgEl('clipPath', { id: `${uid}-clip-right` });
        clipLeft.appendChild(clipLeftRect);
        clipRight.appendChild(clipRightRect);
        defs.appendChild(clipLeft);
        defs.appendChild(clipRight);
        svg.appendChild(defs);

        const gGrid = svgEl('g', { class: 'vd-ll-grid' });
        const gXAxis = svgEl('g', { class: 'vd-ll-xaxis' });
        const gSeries = svgEl('g', { class: 'vd-ll-series' });
        const gEndpoints = svgEl('g', { class: 'vd-ll-endpoints' });
        const gScrub = svgEl('g', { class: 'vd-ll-scrub', style: 'pointer-events:none;' });

        svg.appendChild(gGrid);
        svg.appendChild(gXAxis);
        svg.appendChild(gSeries);
        svg.appendChild(gEndpoints);
        svg.appendChild(gScrub);

        canvasWrap.appendChild(svg);
        container.appendChild(canvasWrap);

        const legendBar = document.createElement('div');
        legendBar.className = 'vd-liveline-legend';
        legendBar.style.cssText = 'display:none;flex-wrap:wrap;gap:6px;padding:6px 10px 2px;align-items:center;justify-content:center;';
        container.appendChild(legendBar);

        host.appendChild(container);

        // Pre-build reusable grid lines (5 steps: 0..4)
        const gridSteps = 4;
        const gridRows = [];
        for (let i = 0; i <= gridSteps; i++) {
            const line = svgEl('line', {
                'stroke-dasharray': '2 3',
                'stroke-width': '1',
            });
            const label = svgEl('text', {
                'font-family': 'ui-monospace, SFMono-Regular, Menlo, monospace',
                'font-size': '10.5',
                'font-weight': '500',
                'dominant-baseline': 'central',
            });
            gGrid.appendChild(line);
            gGrid.appendChild(label);
            gridRows.push({ line, label });
        }

        const xTickNodes = [];
        const seriesDom = [];

        // Pre-build scrub overlay nodes
        const scrubLine = svgEl('line', {
            'stroke-width': '1',
            style: 'display:none;',
        });
        const scrubDotsGroup = svgEl('g');
        const scrubReadoutGroup = svgEl('g', { style: 'display:none;' });
        const scrubReadoutBg = svgEl('rect', {
            rx: '5',
            ry: '5',
            height: '20',
        });
        const scrubReadoutPrimary = svgEl('text', {
            'font-family': 'ui-monospace, SFMono-Regular, Menlo, monospace',
            'font-size': '11.5',
            'font-weight': '600',
            'dominant-baseline': 'central',
        });
        const scrubReadoutSep = svgEl('text', {
            'font-family': 'ui-monospace, SFMono-Regular, Menlo, monospace',
            'font-size': '11',
            'font-weight': '500',
            'dominant-baseline': 'central',
        });
        scrubReadoutGroup.appendChild(scrubReadoutBg);
        scrubReadoutGroup.appendChild(scrubReadoutPrimary);
        scrubReadoutGroup.appendChild(scrubReadoutSep);
        gScrub.appendChild(scrubLine);
        gScrub.appendChild(scrubDotsGroup);
        gScrub.appendChild(scrubReadoutGroup);

        const state = {
            destroyed: false,
            rafId: null,
            lastMs: performance.now(),
            reveal: 0,
            scrubX: null,
            scrubY: null,
            scrubAmount: 0,
            hiddenSeries: new Set(),
            seriesAlpha: new Map(),
            displayValues: [],
            minVal: 0,
            maxVal: 1,
            targetMin: 0,
            targetMax: 1,
            rangeInited: false,
            payload,
            chartHeight,
            labels: [],
            seriesList: [],
            resizeObserver: null,
        };

        const ensureSeriesDom = (count) => {
            while (seriesDom.length < count) {
                const idx = seriesDom.length;
                const gradId = `${uid}-grad-${idx}`;
                const grad = svgEl('linearGradient', { id: gradId, x1: '0', y1: '0', x2: '0', y2: '1' });
                const stop0 = svgEl('stop', { offset: '0%' });
                const stop1 = svgEl('stop', { offset: '100%', 'stop-opacity': '0' });
                grad.appendChild(stop0);
                grad.appendChild(stop1);
                defs.appendChild(grad);

                const group = svgEl('g');
                const leftWrap = svgEl('g', { 'clip-path': `url(#${uid}-clip-left)` });
                const fillLeft = svgEl('path', { fill: `url(#${gradId})` });
                const lineLeft = svgEl('path', {
                    fill: 'none',
                    'stroke-width': '2.5',
                    'stroke-linecap': 'round',
                    'stroke-linejoin': 'round',
                });
                leftWrap.appendChild(fillLeft);
                leftWrap.appendChild(lineLeft);

                const rightWrap = svgEl('g', { 'clip-path': `url(#${uid}-clip-right)`, style: 'display:none;' });
                const fillRight = svgEl('path', { fill: `url(#${gradId})` });
                const lineRight = svgEl('path', {
                    fill: 'none',
                    'stroke-width': '2.5',
                    'stroke-linecap': 'round',
                    'stroke-linejoin': 'round',
                });
                rightWrap.appendChild(fillRight);
                rightWrap.appendChild(lineRight);

                const labelsGroup = svgEl('g');
                group.appendChild(leftWrap);
                group.appendChild(rightWrap);
                group.appendChild(labelsGroup);
                gSeries.appendChild(group);

                // Endpoint nodes
                const epGroup = svgEl('g');
                const refLine = svgEl('line', {
                    'stroke-dasharray': '4 4',
                    'stroke-width': '1',
                });
                const pulseRing = svgEl('circle', {
                    fill: 'none',
                    'stroke-width': '1.5',
                });
                const haloDot = svgEl('circle', { r: '6' });
                const innerDot = svgEl('circle', { r: '3.5' });
                const pillGroup = svgEl('g');
                const pillPath = svgEl('path');
                const pillText = svgEl('text', {
                    'font-family': 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace',
                    'font-size': '11',
                    'font-weight': '600',
                    fill: '#ffffff',
                    'dominant-baseline': 'central',
                });
                pillGroup.appendChild(pillPath);
                pillGroup.appendChild(pillText);

                epGroup.appendChild(refLine);
                epGroup.appendChild(pulseRing);
                epGroup.appendChild(haloDot);
                epGroup.appendChild(innerDot);
                epGroup.appendChild(pillGroup);
                gEndpoints.appendChild(epGroup);

                const scrubDot = svgEl('circle', {
                    r: '4.5',
                    'stroke-width': '1.5',
                    style: 'display:none;',
                });
                scrubDotsGroup.appendChild(scrubDot);

                seriesDom.push({
                    grad,
                    stop0,
                    stop1,
                    group,
                    leftWrap,
                    fillLeft,
                    lineLeft,
                    rightWrap,
                    fillRight,
                    lineRight,
                    labelsGroup,
                    pointLabelNodes: [],
                    epGroup,
                    refLine,
                    pulseRing,
                    haloDot,
                    innerDot,
                    pillGroup,
                    pillPath,
                    pillText,
                    scrubDot,
                });
            }
            for (let i = 0; i < seriesDom.length; i++) {
                const show = i < count;
                seriesDom[i].group.style.display = show ? '' : 'none';
                seriesDom[i].epGroup.style.display = show ? '' : 'none';
                if (!show) seriesDom[i].scrubDot.style.display = 'none';
            }
        };

        const syncSeriesFromPayload = (nextPayload, nextHeight) => {
            state.payload = nextPayload;
            if (nextHeight) {
                state.chartHeight = nextHeight;
                host.style.minHeight = `${nextHeight}px`;
                host.style.height = `${nextHeight}px`;
            }
            const c = nextPayload.chart || {};
            state.labels = c.labels || [];
            const rawDatasets = c.datasets || [];
            const pColor = nextPayload.visual?.color || '#3b82f6';
            const pColors = c.colors || [pColor, '#3b82f6', '#10b981', '#f43f5e', '#8b5cf6', '#06b6d4'];

            state.seriesList = rawDatasets.map((ds, idx) => {
                const col = ds.color || pColors[idx % pColors.length] || pColor;
                const data = (ds.data || []).map((v) => {
                    const n = Number(v);
                    return Number.isFinite(n) ? n : 0;
                });
                const id = String(ds.label || `Series ${idx + 1}`);
                if (!state.seriesAlpha.has(id)) {
                    state.seriesAlpha.set(id, 1);
                }
                if (!state.displayValues[idx] || state.displayValues[idx].length !== data.length) {
                    state.displayValues[idx] = data.map((v) => v);
                }
                return {
                    id,
                    label: id,
                    color: col,
                    rgb: hexToRgb(col),
                    targetData: data,
                };
            });

            ensureSeriesDom(state.seriesList.length);

            // Compute target min/max across visible series
            let allVals = [];
            state.seriesList.forEach((s) => {
                if (!state.hiddenSeries.has(s.id)) {
                    allVals = allVals.concat(s.targetData);
                }
            });
            if (allVals.length === 0) {
                allVals = [0, 1];
            }
            let minV = Math.min(...allVals);
            let maxV = Math.max(...allVals);
            if (minV === maxV) {
                const pad = Math.abs(minV) * 0.1 || 1;
                minV -= pad;
                maxV += pad;
            } else {
                const span = maxV - minV;
                minV -= span * 0.08;
                maxV += span * 0.12;
            }
            state.targetMin = minV;
            state.targetMax = maxV;
            if (!state.rangeInited) {
                state.minVal = minV;
                state.maxVal = maxV;
                state.rangeInited = true;
            }

            // Legend chips for multi-series
            const legendMode = nextPayload.visual?.legend?.show || 'auto';
            const showLegend = legendMode === 'always' || (legendMode === 'auto' && state.seriesList.length > 1);
            if (showLegend && state.seriesList.length > 0) {
                legendBar.style.display = 'flex';
                legendBar.innerHTML = '';
                state.seriesList.forEach((s) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    const isHidden = state.hiddenSeries.has(s.id);
                    btn.style.cssText = `display:inline-flex;align-items:center;gap:5px;padding:2px 8px;border-radius:9999px;font-size:11px;font-weight:500;border:1px solid rgba(148,163,184,0.3);background:transparent;color:inherit;cursor:pointer;opacity:${isHidden ? '0.4' : '1'};transition:opacity 0.15s;`;
                    const dot = document.createElement('span');
                    dot.style.cssText = `width:7px;height:7px;border-radius:50%;background:${s.color};display:inline-block;`;
                    const txt = document.createElement('span');
                    txt.textContent = s.label;
                    btn.appendChild(dot);
                    btn.appendChild(txt);
                    btn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        if (state.hiddenSeries.has(s.id)) {
                            state.hiddenSeries.delete(s.id);
                        } else if (state.hiddenSeries.size < state.seriesList.length - 1) {
                            state.hiddenSeries.add(s.id);
                        }
                        syncSeriesFromPayload(state.payload);
                    });
                    legendBar.appendChild(btn);
                });
            } else {
                legendBar.style.display = 'none';
            }
        };

        syncSeriesFromPayload(payload, chartHeight);

        const onMove = (e) => {
            const rect = svg.getBoundingClientRect();
            state.scrubX = e.clientX - rect.left;
            state.scrubY = e.clientY - rect.top;
        };
        const onLeave = () => {
            state.scrubX = null;
            state.scrubY = null;
        };
        svg.addEventListener('mousemove', onMove);
        svg.addEventListener('mouseleave', onLeave);

        const buildPillPath = (x, y, text, canvasH) => {
            const approxCharW = 6.6;
            const textW = String(text).length * approxCharW;
            const padX = 8;
            const pillH = 20;
            const tailLen = 5;
            const pillW = Math.max(36, textW + padX * 2);
            const r = pillH / 2;

            const boxX = x + 10;
            const boxY = Math.max(4, Math.min(canvasH - pillH - 4, y - pillH / 2));
            const tl = boxX + tailLen + r;
            const cx = boxX + tailLen + pillW - r;
            const topY = boxY;
            const botY = boxY + pillH;
            const midY = boxY + r;

            const d = [
                `M ${tl.toFixed(1)} ${topY.toFixed(1)}`,
                `L ${cx.toFixed(1)} ${topY.toFixed(1)}`,
                `A ${r} ${r} 0 0 1 ${cx.toFixed(1)} ${botY.toFixed(1)}`,
                `L ${tl.toFixed(1)} ${botY.toFixed(1)}`,
                `C ${(boxX + tailLen + 2).toFixed(1)} ${botY.toFixed(1)}, ${(boxX + 2).toFixed(1)} ${(midY + 2.5).toFixed(1)}, ${boxX.toFixed(1)} ${midY.toFixed(1)}`,
                `C ${(boxX + 2).toFixed(1)} ${(midY - 2.5).toFixed(1)}, ${(boxX + tailLen + 2).toFixed(1)} ${topY.toFixed(1)}, ${tl.toFixed(1)} ${topY.toFixed(1)}`,
                'Z',
            ].join(' ');

            return {
                d,
                textX: boxX + tailLen + padX,
                textY: midY + 0.5,
            };
        };

        const drawFrame = (nowMs) => {
            if (state.destroyed) return;
            const dt = Math.min(50, Math.max(1, nowMs - state.lastMs));
            state.lastMs = nowMs;

            const rect = canvasWrap.getBoundingClientRect();
            const w = Math.max(120, rect.width || host.clientWidth || 300);
            const h = Math.max(140, rect.height || (state.chartHeight - (legendBar.style.display !== 'none' ? 28 : 0)) || 220);

            svg.setAttribute('viewBox', `0 0 ${w} ${h}`);

            const isDark = document.documentElement.classList.contains('dark') || document.body.classList.contains('dark');
            const curVariant = window.spiggleEffectiveChartVariant(state.payload.type || 'line', state.payload.visual?.chart_variant);
            const isStraight = curVariant === 'straight';
            const hasDataLabels = Boolean(state.payload.visual?.data_labels?.enabled || curVariant === 'data_labels');
            const curFormat = state.payload.visual?.number_format || 'number';
            const curCurrency = state.payload.visual?.currency || 'USD';
            const formatFn = (v) => window.spiggleFormatValue(v, curFormat, curCurrency);

            // Smoothly advance entrance reveal & scrub opacity
            state.reveal = lerp(state.reveal, 1, 0.085, dt);
            if (state.reveal > 0.998) state.reveal = 1;

            const isHovering = state.scrubX !== null;
            state.scrubAmount = lerp(state.scrubAmount, isHovering ? 1 : 0, 0.16, dt);
            if (state.scrubAmount < 0.01) state.scrubAmount = 0;

            // Smoothly lerp Y range
            state.minVal = lerp(state.minVal, state.targetMin, 0.14, dt);
            state.maxVal = lerp(state.maxVal, state.targetMax, 0.14, dt);
            const valSpan = Math.max(1e-6, state.maxVal - state.minVal);

            // Estimate right padding for floating live badge + Y axis labels
            const sampleFormatted = formatFn(state.targetMax);
            const badgeRoom = Math.max(66, Math.min(110, String(sampleFormatted).length * 7 + 36));

            const pad = {
                top: 24,
                right: badgeRoom,
                bottom: 30,
                left: 14,
            };
            const chartW = Math.max(40, w - pad.left - pad.right);
            const chartH = Math.max(40, h - pad.top - pad.bottom);

            const toY = (v) => pad.top + chartH - ((v - state.minVal) / valSpan) * chartH;
            const nPoints = Math.max(
                state.labels.length,
                state.seriesList[0]?.targetData?.length || 0
            );
            const toX = (idx) => {
                if (nPoints <= 1) return pad.left + chartW / 2;
                return pad.left + (idx / (nPoints - 1)) * chartW;
            };

            // 1. Horizontal dashed grid lines + right-aligned Y-axis labels
            const gridStroke = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(15,23,42,0.07)';
            const axisTextFill = isDark ? 'rgba(255,255,255,0.45)' : 'rgba(100,116,139,0.78)';
            for (let i = 0; i <= gridSteps; i++) {
                const ratio = i / gridSteps;
                const val = state.minVal + ratio * valSpan;
                const y = pad.top + chartH - ratio * chartH;
                const row = gridRows[i];
                row.line.setAttribute('x1', String(pad.left));
                row.line.setAttribute('y1', y.toFixed(1));
                row.line.setAttribute('x2', String(w - pad.right));
                row.line.setAttribute('y2', y.toFixed(1));
                row.line.setAttribute('stroke', gridStroke);

                row.label.setAttribute('x', String(w - pad.right + 8));
                row.label.setAttribute('y', y.toFixed(1));
                row.label.setAttribute('fill', axisTextFill);
                row.label.textContent = formatFn(val);
            }

            // 2. X-axis category / time labels along the bottom
            const maxTicks = Math.max(2, Math.min(state.labels.length, Math.floor(chartW / 72)));
            const step = state.labels.length <= maxTicks ? 1 : Math.ceil((state.labels.length - 1) / (maxTicks - 1));
            const tickIndices = [];
            if (state.labels.length > 0) {
                for (let i = 0; i < state.labels.length; i += step) {
                    tickIndices.push(i);
                }
            }
            while (xTickNodes.length < tickIndices.length) {
                const tNode = svgEl('text', {
                    'font-family': 'ui-monospace, SFMono-Regular, Menlo, monospace',
                    'font-size': '10.5',
                    'font-weight': '500',
                });
                gXAxis.appendChild(tNode);
                xTickNodes.push(tNode);
            }
            for (let tIdx = 0; tIdx < xTickNodes.length; tIdx++) {
                const node = xTickNodes[tIdx];
                if (tIdx >= tickIndices.length) {
                    node.style.display = 'none';
                    continue;
                }
                node.style.display = '';
                const i = tickIndices[tIdx];
                const x = toX(i);
                const lbl = String(state.labels[i] ?? '');
                const anchor = i === 0 ? 'start' : (i >= state.labels.length - 1 ? 'end' : 'middle');
                node.setAttribute('x', x.toFixed(1));
                node.setAttribute('y', String(h - pad.bottom + 18));
                node.setAttribute('text-anchor', anchor);
                node.setAttribute('fill', axisTextFill);
                node.textContent = lbl.length > 14 ? lbl.slice(0, 12) + '…' : lbl;
            }

            // Determine active hover index if scrubbing
            let hoverIndex = null;
            let clampScrubX = null;
            if (state.scrubX !== null && nPoints > 0) {
                clampScrubX = Math.max(pad.left, Math.min(pad.left + chartW, state.scrubX));
                if (nPoints === 1) {
                    hoverIndex = 0;
                } else {
                    const rawIdx = ((clampScrubX - pad.left) / chartW) * (nPoints - 1);
                    hoverIndex = Math.max(0, Math.min(nPoints - 1, Math.round(rawIdx)));
                    clampScrubX = toX(hoverIndex);
                }
            }

            const splitting = clampScrubX !== null && state.scrubAmount > 0.02;
            if (splitting) {
                clipLeftRect.setAttribute('x', '0');
                clipLeftRect.setAttribute('y', '0');
                clipLeftRect.setAttribute('width', String(clampScrubX));
                clipLeftRect.setAttribute('height', String(h));

                clipRightRect.setAttribute('x', String(clampScrubX));
                clipRightRect.setAttribute('y', '0');
                clipRightRect.setAttribute('width', String(Math.max(0, w - clampScrubX)));
                clipRightRect.setAttribute('height', String(h));
            } else {
                clipLeftRect.setAttribute('x', '0');
                clipLeftRect.setAttribute('y', '0');
                clipLeftRect.setAttribute('width', String(w));
                clipLeftRect.setAttribute('height', String(h));
            }

            const centerY = pad.top + chartH / 2;
            const amplitude = chartH * 0.07;
            const scroll = nowMs * 0.0015;

            // 3. Lerp series values & update SVG spline paths + live endpoints
            const liveEndpoints = [];

            state.seriesList.forEach((series, sIdx) => {
                const dom = seriesDom[sIdx];
                if (!dom) return;

                const isHidden = state.hiddenSeries.has(series.id);
                const targetAlpha = isHidden ? 0 : 1;
                const prevAlpha = state.seriesAlpha.get(series.id) ?? 1;
                const curAlpha = lerp(prevAlpha, targetAlpha, 0.14, dt);
                state.seriesAlpha.set(series.id, curAlpha);

                if (curAlpha < 0.01 || series.targetData.length === 0) {
                    dom.group.style.display = 'none';
                    dom.epGroup.style.display = 'none';
                    dom.scrubDot.style.display = 'none';
                    return;
                }
                dom.group.style.display = '';
                dom.epGroup.style.display = '';

                const disp = state.displayValues[sIdx];
                for (let i = 0; i < series.targetData.length; i++) {
                    disp[i] = lerp(disp[i], series.targetData[i], 0.16, dt);
                }

                const pts = disp.map((val, i) => {
                    const x = toX(i);
                    let rawY = Math.max(pad.top, Math.min(pad.top + chartH, toY(val)));
                    if (state.reveal > 0.85 && i === disp.length - 1 && disp.length > 1) {
                        const liveWave = Math.sin(nowMs * 0.0035 + sIdx * 1.7) * 3.5 * (1 - state.scrubAmount);
                        rawY = Math.max(pad.top + 2, Math.min(pad.top + chartH - 2, rawY + liveWave));
                    }
                    if (state.reveal < 1) {
                        const t = Math.max(0, Math.min(1, (x - pad.left) / chartW));
                        const centerDist = Math.abs(t - 0.5) * 2;
                        const localReveal = Math.max(0, Math.min(1, (state.reveal - centerDist * 0.35) / 0.65));
                        const baseY = loadingY(t, centerY, amplitude, scroll);
                        return [x, baseY + (rawY - baseY) * localReveal];
                    }
                    return [x, rawY];
                });

                const showFill = state.seriesList.length === 1 || curVariant === 'area';
                const fillTopAlpha = curVariant === 'area' ? (isDark ? 0.32 : 0.24) : (isDark ? 0.16 : 0.11);
                dom.stop0.setAttribute('stop-color', series.color);
                dom.stop0.setAttribute('stop-opacity', String(fillTopAlpha * state.reveal));
                dom.stop1.setAttribute('stop-color', series.color);

                const lineD = window.spiggleMonotoneSplinePath(pts, isStraight);
                const areaD = (showFill && pts.length >= 2)
                    ? `${lineD} L ${pts[pts.length - 1][0].toFixed(2)} ${(pad.top + chartH).toFixed(2)} L ${pts[0][0].toFixed(2)} ${(pad.top + chartH).toFixed(2)} Z`
                    : '';

                dom.lineLeft.setAttribute('d', lineD);
                dom.lineLeft.setAttribute('stroke', series.color);
                dom.lineLeft.setAttribute('opacity', String(curAlpha));

                if (areaD) {
                    dom.fillLeft.style.display = '';
                    dom.fillLeft.setAttribute('d', areaD);
                    dom.fillLeft.setAttribute('opacity', String(curAlpha));
                } else {
                    dom.fillLeft.style.display = 'none';
                }

                if (splitting) {
                    const rightAlpha = curAlpha * (1 - state.scrubAmount * 0.55);
                    dom.rightWrap.style.display = '';
                    dom.lineRight.setAttribute('d', lineD);
                    dom.lineRight.setAttribute('stroke', series.color);
                    dom.lineRight.setAttribute('opacity', String(rightAlpha));
                    if (areaD) {
                        dom.fillRight.style.display = '';
                        dom.fillRight.setAttribute('d', areaD);
                        dom.fillRight.setAttribute('opacity', String(rightAlpha));
                    } else {
                        dom.fillRight.style.display = 'none';
                    }
                } else {
                    dom.rightWrap.style.display = 'none';
                }

                // Point data labels if enabled
                if (hasDataLabels && state.reveal > 0.7) {
                    dom.labelsGroup.style.display = '';
                    const lblAlpha = curAlpha * ((state.reveal - 0.7) / 0.3);
                    dom.labelsGroup.setAttribute('opacity', String(lblAlpha));
                    while (dom.pointLabelNodes.length < pts.length) {
                        const pTxt = svgEl('text', {
                            'font-family': 'ui-monospace, SFMono-Regular, Menlo, monospace',
                            'font-size': '10',
                            'font-weight': '600',
                            'text-anchor': 'middle',
                        });
                        dom.labelsGroup.appendChild(pTxt);
                        dom.pointLabelNodes.push(pTxt);
                    }
                    for (let pIdx = 0; pIdx < dom.pointLabelNodes.length; pIdx++) {
                        const pNode = dom.pointLabelNodes[pIdx];
                        if (pIdx >= pts.length) {
                            pNode.style.display = 'none';
                            continue;
                        }
                        pNode.style.display = '';
                        pNode.setAttribute('x', pts[pIdx][0].toFixed(1));
                        pNode.setAttribute('y', Math.max(pad.top + 8, pts[pIdx][1] - 8).toFixed(1));
                        pNode.setAttribute('fill', series.color);
                        pNode.textContent = formatFn(series.targetData[pIdx]);
                    }
                } else {
                    dom.labelsGroup.style.display = 'none';
                }

                const lastPt = pts[pts.length - 1];
                const lastVal = disp[disp.length - 1];
                liveEndpoints.push({
                    series,
                    dom,
                    x: lastPt[0],
                    y: lastPt[1],
                    value: lastVal,
                    alpha: curAlpha,
                    pts,
                });
            });

            // 4. Dashed current-value horizontal reference line & live pulsing endpoint dots
            liveEndpoints.forEach((ep, idx) => {
                const { dom } = ep;
                const dim = state.scrubAmount * 0.65;
                const [r, g, b] = ep.series.rgb;

                if (idx === 0) {
                    dom.refLine.style.display = '';
                    dom.refLine.setAttribute('x1', String(pad.left));
                    dom.refLine.setAttribute('y1', ep.y.toFixed(1));
                    dom.refLine.setAttribute('x2', String(w - pad.right));
                    dom.refLine.setAttribute('y2', ep.y.toFixed(1));
                    dom.refLine.setAttribute('stroke', `rgba(${r},${g},${b},${(0.38 * (1 - dim * 0.5) * ep.alpha * state.reveal).toFixed(3)})`);
                } else {
                    dom.refLine.style.display = 'none';
                }

                // Expanding pulse ring every 1.5s
                const pulseT = (nowMs % 1500) / 900;
                if (pulseT < 1 && dim < 0.4) {
                    const radius = 8 + pulseT * 12;
                    const pulseAlpha = Math.max(0, 0.38 * (1 - pulseT) * (1 - dim * 2) * ep.alpha * state.reveal);
                    dom.pulseRing.style.display = '';
                    dom.pulseRing.setAttribute('cx', ep.x.toFixed(1));
                    dom.pulseRing.setAttribute('cy', ep.y.toFixed(1));
                    dom.pulseRing.setAttribute('r', radius.toFixed(1));
                    dom.pulseRing.setAttribute('stroke', ep.series.color);
                    dom.pulseRing.setAttribute('stroke-opacity', pulseAlpha.toFixed(3));
                } else {
                    dom.pulseRing.style.display = 'none';
                }

                // Outer halo circle
                dom.haloDot.setAttribute('cx', ep.x.toFixed(1));
                dom.haloDot.setAttribute('cy', ep.y.toFixed(1));
                dom.haloDot.setAttribute('fill', isDark ? '#1e293b' : '#ffffff');
                dom.haloDot.setAttribute('stroke', isDark ? 'rgba(0,0,0,0.4)' : 'rgba(15,23,42,0.14)');
                dom.haloDot.setAttribute('stroke-width', '1');
                dom.haloDot.setAttribute('opacity', String(ep.alpha));

                // Inner colored dot
                dom.innerDot.setAttribute('cx', ep.x.toFixed(1));
                dom.innerDot.setAttribute('cy', ep.y.toFixed(1));
                dom.innerDot.setAttribute('fill', ep.series.color);
                dom.innerDot.setAttribute('opacity', String(ep.alpha));

                // Floating value pill with tail on primary series
                if (idx === 0) {
                    dom.pillGroup.style.display = '';
                    dom.pillGroup.setAttribute('opacity', String(ep.alpha));
                    const valText = formatFn(ep.value);
                    const pill = buildPillPath(w - pad.right - 6, ep.y, valText, h);
                    dom.pillPath.setAttribute('d', pill.d);
                    dom.pillPath.setAttribute('fill', ep.series.color);
                    dom.pillText.setAttribute('x', pill.textX.toFixed(1));
                    dom.pillText.setAttribute('y', pill.textY.toFixed(1));
                    dom.pillText.textContent = valText;
                } else {
                    dom.pillGroup.style.display = 'none';
                }
            });

            // 5. Crosshair scrubbing overlay + top readout
            if (hoverIndex !== null && clampScrubX !== null && state.scrubAmount > 0.02) {
                scrubLine.style.display = '';
                scrubLine.setAttribute('x1', clampScrubX.toFixed(1));
                scrubLine.setAttribute('y1', String(pad.top));
                scrubLine.setAttribute('x2', clampScrubX.toFixed(1));
                scrubLine.setAttribute('y2', String(pad.top + chartH));
                scrubLine.setAttribute('stroke', isDark ? 'rgba(255,255,255,0.28)' : 'rgba(15,23,42,0.22)');
                scrubLine.setAttribute('opacity', String(state.scrubAmount * 0.65));

                const hoverEntries = [];
                liveEndpoints.forEach((ep) => {
                    const pt = ep.pts[hoverIndex];
                    const val = ep.series.targetData[hoverIndex];
                    if (!pt || val === undefined) {
                        ep.dom.scrubDot.style.display = 'none';
                        return;
                    }
                    ep.dom.scrubDot.style.display = '';
                    ep.dom.scrubDot.setAttribute('cx', pt[0].toFixed(1));
                    ep.dom.scrubDot.setAttribute('cy', pt[1].toFixed(1));
                    ep.dom.scrubDot.setAttribute('fill', ep.series.color);
                    ep.dom.scrubDot.setAttribute('stroke', isDark ? '#0f172a' : '#ffffff');
                    ep.dom.scrubDot.setAttribute('opacity', String(state.scrubAmount * ep.alpha));
                    hoverEntries.push({
                        label: ep.series.label,
                        color: ep.series.color,
                        valueText: formatFn(val),
                    });
                });

                if (hoverEntries.length > 0) {
                    scrubReadoutGroup.style.display = '';
                    scrubReadoutGroup.setAttribute('opacity', String(state.scrubAmount));
                    const catLabel = String(state.labels[hoverIndex] ?? `#${hoverIndex + 1}`);
                    const primaryText = hoverEntries.length === 1
                        ? `${hoverEntries[0].valueText}`
                        : hoverEntries.map((e) => `${e.label}: ${e.valueText}`).join('  ');
                    const sepText = `  ·  ${catLabel}`;
                    const pW = primaryText.length * 7.0;
                    const sW = sepText.length * 6.6;
                    const totalW = pW + sW;

                    let tx = clampScrubX - totalW / 2;
                    tx = Math.max(pad.left + 4, Math.min(w - pad.right - totalW - 4, tx));
                    const ty = Math.max(13, pad.top - 7);

                    scrubReadoutBg.setAttribute('x', (tx - 6).toFixed(1));
                    scrubReadoutBg.setAttribute('y', (ty - 10).toFixed(1));
                    scrubReadoutBg.setAttribute('width', (totalW + 12).toFixed(1));
                    scrubReadoutBg.setAttribute('fill', isDark ? 'rgba(15,23,42,0.88)' : 'rgba(255,255,255,0.92)');

                    scrubReadoutPrimary.setAttribute('x', tx.toFixed(1));
                    scrubReadoutPrimary.setAttribute('y', ty.toFixed(1));
                    scrubReadoutPrimary.setAttribute('fill', isDark ? '#f8fafc' : '#0f172a');
                    scrubReadoutPrimary.textContent = primaryText;

                    scrubReadoutSep.setAttribute('x', (tx + pW).toFixed(1));
                    scrubReadoutSep.setAttribute('y', ty.toFixed(1));
                    scrubReadoutSep.setAttribute('fill', isDark ? 'rgba(255,255,255,0.6)' : 'rgba(100,116,139,0.85)');
                    scrubReadoutSep.textContent = sepText;
                } else {
                    scrubReadoutGroup.style.display = 'none';
                }
            } else {
                scrubLine.style.display = 'none';
                scrubReadoutGroup.style.display = 'none';
                seriesDom.forEach((d) => {
                    d.scrubDot.style.display = 'none';
                });
            }

            if (!document.hidden && !state.destroyed) {
                state.rafId = requestAnimationFrame(drawFrame);
            }
        };

        const onVisibilityChange = () => {
            if (!document.hidden && !state.destroyed && !state.rafId) {
                state.lastMs = performance.now();
                state.rafId = requestAnimationFrame(drawFrame);
            } else if (document.hidden && state.rafId) {
                cancelAnimationFrame(state.rafId);
                state.rafId = null;
            }
        };
        document.addEventListener('visibilitychange', onVisibilityChange);

        if (typeof ResizeObserver !== 'undefined') {
            state.resizeObserver = new ResizeObserver(() => {
                if (!state.destroyed) {
                    state.lastMs = performance.now();
                }
            });
            state.resizeObserver.observe(canvasWrap);
        }

        state.rafId = requestAnimationFrame(drawFrame);

        const instance = {
            _isLiveline: true,
            get destroyed() {
                return state.destroyed;
            },
            updatePayload(nextPayload, nextHeight) {
                syncSeriesFromPayload(nextPayload, nextHeight);
            },
            resize() {
                state.lastMs = performance.now();
            },
            destroy() {
                state.destroyed = true;
                if (state.rafId) {
                    cancelAnimationFrame(state.rafId);
                    state.rafId = null;
                }
                svg.removeEventListener('mousemove', onMove);
                svg.removeEventListener('mouseleave', onLeave);
                document.removeEventListener('visibilitychange', onVisibilityChange);
                state.resizeObserver?.disconnect();
                if (host._spiggleLiveline === instance) {
                    host._spiggleLiveline = null;
                }
            },
        };

        host._spiggleLiveline = instance;
        host._spiggleChart = instance;

        if (typeof onRendered === 'function') {
            onRendered(instance);
        }

        return instance;
    };

    window.spiggleRenderDashletChart = function (host, payload, onRendered) {

        if (! host || ! payload?.chart) {

            return null;

        }



        const requestedEngine = payload.visual?.chart_engine || 'apexcharts';
        const requestedVariant = window.spiggleEffectiveChartVariant(payload.type || 'bar', payload.visual?.chart_variant);
        const useLivelineCanvas = (payload.type === 'line') && (requestedEngine === 'liveline' || requestedVariant === 'liveline');

        if (!useLivelineCanvas && host._spiggleLiveline) {
            host._spiggleLiveline.destroy();
            host._spiggleLiveline = null;
        }

        if (!useLivelineCanvas && host._spiggleChart && typeof host._spiggleChart.destroy === 'function') {
            host._spiggleChart.destroy();
            host._spiggleChart = null;
        }



        const chart = payload.chart || {};

        const type = payload.type || 'bar';

        const variant = window.spiggleEffectiveChartVariant(type, payload.visual?.chart_variant);

        const labels = chart.labels || [];

        const datasets = chart.datasets || [];

        const isRadialBar = type === 'radialbar' || chart.radialbar === true;

        const horizontalBar = type === 'bar' || type === 'funnel';

        const chartWrap = host.closest('.dashlet-chart-wrap');



        host.classList.toggle('is-horizontal-bar', horizontalBar);

        chartWrap?.classList.toggle('is-horizontal-bar', horizontalBar);

        host.classList.toggle('is-radialbar', isRadialBar);

        chartWrap?.classList.toggle('is-radialbar', isRadialBar);



        if (! isRadialBar && labels.length === 0 && datasets.length === 0) {

            host.innerHTML = '<p class="dashlet-preview-more">No chart data for the current field mapping.</p>';



            return null;

        }



        if (isRadialBar && ! ('value' in chart)) {

            host.innerHTML = '<p class="dashlet-preview-more">No gauge data for the current field mapping.</p>';



            return null;

        }



        let attempts = 0;



        const resolveHeight = () => {

            const wrapHeight = chartWrap?.clientHeight || host.parentElement?.clientHeight || 0;

            const hostHeight = host.clientHeight || 0;

            const containerHeight = Math.max(wrapHeight, hostHeight, 180);
            const isPreview = host.classList.contains('dashlet-chart-host-preview');

            if (isRadialBar) {
                return isPreview ? 300 : Math.max(280, containerHeight);
            }

            if (horizontalBar) {
                const needed = Math.max(220, labels.length * 34 + 80);

                return isPreview ? Math.min(480, needed) : Math.max(needed, containerHeight);
            }

            return isPreview ? 280 : Math.max(containerHeight, 220);

        };



        const render = () => {
            if (useLivelineCanvas) {
                const chartHeight = resolveHeight();
                return window.spiggleRenderLivelineCanvas(host, payload, chartHeight, onRendered);
            }

            if (! window.ApexCharts) {

                if (++attempts < 40) {

                    setTimeout(render, 75);

                }



                return null;

            }



            host.innerHTML = '';



            const color = payload.visual?.color || '#f59e0b';

            const palette = chart.colors || [color, '#3b82f6', '#10b981', '#f43f5e', '#8b5cf6', '#64748b'];

            const radial = type === 'pie' || type === 'donut';

            const stacked = type === 'stacked_column' || chart.stacked === true;

            const chartHeight = resolveHeight();



            host.style.minHeight = `${chartHeight}px`;

            host.style.height = `${chartHeight}px`;



            let options = {

                chart: {

                    type: 'bar',

                    height: chartHeight,

                    stacked,

                    toolbar: { show: false },

                    fontFamily: 'inherit',

                    animations: requestedEngine === 'liveline'
                        ? {
                            enabled: true,
                            easing: 'easeinout',
                            speed: 650,
                            animateGradually: { enabled: true, delay: 90 },
                            dynamicAnimation: { enabled: true, speed: 420 },
                        }
                        : { enabled: false },

                    parentHeightOffset: host.classList.contains('dashlet-chart-host-preview') ? 0 : 15,

                    redrawOnParentResize: ! host.classList.contains('dashlet-chart-host-preview'),
                    redrawOnWindowResize: ! host.classList.contains('dashlet-chart-host-preview'),

                },

                colors: palette,

                dataLabels: { enabled: false },

                legend: {

                    show: radial || datasets.length > 1,

                    position: 'bottom',

                },

                tooltip: {

                    enabled: true,

                    shared: false,

                    intersect: true,

                    followCursor: true,

                    style: { fontSize: '12px' },

                },

            };



            const legendMode = payload.visual?.legend?.show || 'auto';

            if (legendMode === 'always') {

                options.legend.show = true;

            } else if (legendMode === 'never') {

                options.legend.show = false;

            }

            if (payload.visual?.legend?.position) {

                options.legend.position = payload.visual.legend.position;

            }

            if (payload.visual?.data_labels?.enabled) {

                options.dataLabels = {

                    ...(options.dataLabels || {}),

                    enabled: true,

                };

            }



            const variantContext = {

                datasets,

                labels,

                funnelLabels: [...labels],

                funnelDatasets: datasets.map((dataset) => ({

                    label: dataset.label || 'Series',

                    data: (dataset.data || []).map((value) => Number(value)),

                })),

            };



            if (isRadialBar) {

                options = window.spigglePrepareRadialBarOptions(options, payload, chart, chartHeight, variant);

                options = window.spiggleApplyChartVariant('radialbar', variant, options, variantContext);

            } else if (radial) {

                options.chart.type = type === 'donut' ? 'donut' : 'pie';

                options.series = (datasets[0]?.data || []).map((value) => Number(value));

                options.labels = labels;

                options.plotOptions = {

                    pie: { donut: { size: type === 'donut' ? '68%' : '62%' } },

                };



                if (type === 'donut') {

                    options = window.spiggleApplyChartVariant('donut', variant, options, variantContext);

                } else {

                    options = window.spiggleApplyChartVariant('pie', variant, options, variantContext);

                }

            } else if (type === 'line') {

                options.chart.type = 'line';

                options.series = datasets.map((dataset) => ({

                    name: dataset.label || 'Series',

                    data: (dataset.data || []).map((value) => Number(value)),

                }));

                options.stroke = { width: 3, curve: 'smooth' };

                options.markers = { size: 4, strokeWidth: 0 };

                options.xaxis = {

                    categories: labels,

                    title: { text: chart.x_title || '' },

                };

                options.yaxis = {

                    title: { text: chart.y_title || '' },

                };

                options = window.spiggleApplyChartVariant('line', variant, options, variantContext);

            } else if (type === 'treemap') {

                options.chart.type = 'treemap';

                options.series = datasets.map((dataset) => ({

                    name: dataset.label || 'Value',

                    data: labels.map((label, index) => ({

                        x: String(label),

                        y: Number((dataset.data || [])[index] ?? 0),

                    })),

                }));

                options.legend = { show: datasets.length > 1 };

                options.dataLabels = {

                    enabled: true,

                    formatter: (text, op) => {

                        const value = window.spiggleFormatValue(

                            op?.value,

                            payload.visual?.number_format,

                            payload.visual?.currency,

                        );

                        return [text, value];

                    },

                    style: { fontSize: '12px' },

                };

                options.plotOptions = {

                    treemap: {

                        distributed: datasets.length < 2,

                        enableShades: true,

                    },

                };

            } else {

                let funnelLabels = variantContext.funnelLabels;

                let funnelDatasets = variantContext.funnelDatasets;



                if (type === 'funnel' && funnelLabels.length > 0 && funnelDatasets[0]) {

                    const pairs = funnelLabels.map((label, index) => ({

                        label,

                        value: Number(funnelDatasets[0].data[index] ?? 0),

                    })).sort((a, b) => b.value - a.value);



                    funnelLabels = pairs.map((pair) => pair.label);

                    funnelDatasets = [{

                        label: funnelDatasets[0].label || 'Count',

                        data: pairs.map((pair) => pair.value),

                    }];

                }



                if (type === 'bar') {

                    const barContext = {

                        datasets,

                        labels,

                        funnelLabels,

                        funnelDatasets,

                    };



                    options = window.spiggleApplyChartVariant('bar', variant, options, barContext);

                    funnelLabels = barContext.funnelLabels;

                    funnelDatasets = barContext.funnelDatasets;

                }



                options.series = funnelDatasets.map((dataset) => ({

                    name: dataset.label || 'Series',

                    data: dataset.data || [],

                }));



                if (horizontalBar) {

                    options.plotOptions = {

                        ...(options.plotOptions || {}),

                        bar: {

                            ...(options.plotOptions?.bar || {}),

                            horizontal: true,

                            borderRadius: 6,

                            barHeight: '70%',

                            isFunnel: type === 'funnel',

                            distributed: type === 'funnel' || (variant === 'race' && type === 'bar'),

                        },

                    };



                    if (type === 'funnel') {

                        options = window.spiggleApplyChartVariant('funnel', variant, options, variantContext);

                    }



                    options.xaxis = {

                        categories: funnelLabels,

                        title: { text: chart.y_title || '' },

                        tickAmount: 4,

                    };

                    options.yaxis = {

                        labels: {

                            maxWidth: 220,

                            style: { fontSize: '11px' },

                        },

                        title: { text: chart.x_title || '' },

                    };

                    options.grid = { padding: { left: 8, right: 12 } };

                } else {

                    options.plotOptions = {

                        bar: {

                            horizontal: false,

                            borderRadius: 6,

                            columnWidth: type === 'funnel' ? '85%' : '55%',

                            isFunnel: type === 'funnel',

                            distributed: type === 'funnel',

                        },

                    };

                    options.xaxis = {

                        categories: funnelLabels,

                        title: { text: chart.x_title || '' },

                        labels: {

                            rotate: -35,

                            trim: true,

                            maxHeight: 72,

                        },

                    };

                    options.yaxis = {

                        title: { text: chart.y_title || '' },

                    };



                    if (type === 'column') {

                        options = window.spiggleApplyChartVariant('column', variant, options, variantContext);

                        options.series = funnelDatasets.map((dataset) => ({

                            name: dataset.label || 'Series',

                            data: dataset.data || [],

                        }));

                        options.xaxis = {

                            ...(options.xaxis || {}),

                            categories: funnelLabels,

                            title: { text: chart.x_title || '' },

                        };

                    }



                    if (type === 'stacked_column') {

                        options = window.spiggleApplyChartVariant('stacked_column', variant, options, variantContext);

                        options.series = funnelDatasets.map((dataset) => ({

                            name: dataset.label || 'Series',

                            data: dataset.data || [],

                        }));

                        options.xaxis = {

                            ...(options.xaxis || {}),

                            categories: funnelLabels,

                            title: { text: chart.x_title || '' },

                        };

                    }

                }

            }

            options = window.spiggleApplyValueFormatters(options, payload, { horizontalBar });

            if (payload.visual?.axes?.show_x_title === false && options.xaxis) {
                options.xaxis = {
                    ...options.xaxis,
                    title: { ...(options.xaxis.title || {}), text: '' },
                };
            }

            if (payload.visual?.axes?.show_y_title === false) {
                if (Array.isArray(options.yaxis)) {
                    options.yaxis = options.yaxis.map((axis) => ({
                        ...axis,
                        title: { ...(axis.title || {}), text: '' },
                    }));
                } else if (options.yaxis) {
                    options.yaxis = {
                        ...options.yaxis,
                        title: { ...(options.yaxis.title || {}), text: '' },
                    };
                }
            }

            try {

                const instance = new ApexCharts(host, options);

                instance.render();

                host._spiggleChart = instance;



                if (typeof onRendered === 'function') {

                    onRendered(instance);

                }



                return instance;

            } catch (error) {

                host.innerHTML = '<p class="dashlet-preview-more">Unable to render this chart type.</p>';



                return null;

            }

        };



        requestAnimationFrame(() => requestAnimationFrame(render));



        return null;

    };

</script>


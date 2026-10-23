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
        options.chart.animations = {
            enabled: variant === 'live_needle',
            speed: variant === 'live_needle' ? 700 : 250,
            animateGradually: {
                enabled: variant === 'live_needle',
                delay: 120,
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



    window.spiggleRenderDashletChart = function (host, payload, onRendered) {

        if (! host || ! payload?.chart) {

            return null;

        }



        if (host._spiggleChart && typeof host._spiggleChart.destroy === 'function') {

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



            if (isRadialBar) {
                return Math.max(280, containerHeight);
            }

            if (horizontalBar) {

                const barHeight = 34;

                const needed = Math.max(200, labels.length * barHeight + 72);



                return Math.max(needed, containerHeight);

            }



            return Math.max(containerHeight, 220);

        };



        const render = () => {

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

                    animations: { enabled: false },

                    redrawOnParentResize: true,

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


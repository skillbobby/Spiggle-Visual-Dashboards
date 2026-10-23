<div
    class="vd-canvas-shell"
    x-data="visualDashboardCanvas({
        columns: @js($columns),
        cellHeight: @js($cellHeight),
        saveDebounceMs: @js($saveDebounceMs),
        items: @js($items),
        selectedVisualId: @js($selectedVisualId),
    })"
    x-on:visual-dashboard-refresh.window="refreshFromServer()"
>
    <div class="vd-canvas-toolbar">
        <div class="vd-canvas-toolbar-copy">
            <h2 class="vd-canvas-title">{{ $dashboard->displayHeading() }}</h2>
            @if (filled($dashboard->description))
                <p class="vd-canvas-description">{{ $dashboard->description }}</p>
            @else
                <p class="vd-canvas-description">Design your dashboard on the canvas. Drag, resize, and configure each visual.</p>
            @endif
        </div>
        <div class="vd-canvas-toolbar-actions">
            @if (! $dashboard->isPubliclyViewable())
                <span class="vd-badge vd-badge-draft">Private</span>
            @else
                <span class="vd-badge vd-badge-published">Public link</span>
            @endif
            <button type="button" class="vd-btn vd-btn-primary" wire:click="openGallery">
                Insert visual
            </button>
        </div>
    </div>

    @if ($items === [])
        <div class="vd-canvas-empty">
            <div class="vd-canvas-empty-icon" aria-hidden="true">📊</div>
            <h3 class="vd-canvas-empty-title">Canvas is empty</h3>
            <p class="vd-canvas-empty-copy">Insert a KPI card or chart from the gallery to get started.</p>
            <button type="button" class="vd-btn vd-btn-primary" wire:click="openGallery">Open chart gallery</button>
        </div>
    @else
        @include('visual-dashboards::livewire.partials.visual-grid', [
            'items' => $items,
            'columns' => $columns,
            'cellHeight' => $cellHeight,
            'saveDebounceMs' => $saveDebounceMs,
            'selectedVisualId' => $selectedVisualId,
        ])
    @endif

    {{-- Chart gallery slide-over --}}
    <div
        class="vd-slideover vd-slideover-gallery"
        x-show="$wire.galleryOpen"
        x-cloak
        @keydown.escape.window="$wire.closeGallery()"
    >
        <div class="vd-slideover-backdrop" wire:click="closeGallery"></div>
        <aside class="vd-slideover-panel vd-slideover-panel-wide" role="dialog" aria-label="Insert visual">
            <header class="vd-slideover-header">
                <div>
                    <h3>Insert visual</h3>
                    <p>Visuals are cards on your dashboard that display metrics, charts, or tables.</p>
                </div>
                <button type="button" class="vd-icon-btn" wire:click="closeGallery" aria-label="Close">&times;</button>
            </header>
            <div class="vd-gallery-grid">
                @foreach ($gallery as $entry)
                    <button
                        type="button"
                        class="vd-gallery-card @if (! ($entry['enabled'] ?? false)) is-disabled @endif"
                        wire:click="addVisual('{{ $entry['type'] }}')"
                        @disabled(! ($entry['enabled'] ?? false))
                    >
                        <div class="vd-gallery-card-icon" aria-hidden="true">{!! \Spiggle\VisualDashboards\Support\VisualTypes::iconSvg($entry['icon'] ?? 'kpi') !!}</div>
                        <strong>{{ $entry['label'] }}</strong>
                        <span class="vd-gallery-ideal">Ideal usage</span>
                        <span class="vd-gallery-desc">{{ $entry['ideal_usage'] ?? $entry['description'] }}</span>
                    </button>
                @endforeach
            </div>
        </aside>
    </div>

    {{-- Properties / Data source panel --}}
    <div
        class="vd-slideover vd-slideover-panel-right"
        x-show="$wire.panelOpen"
        x-cloak
        @keydown.escape.window="$wire.closePanel()"
    >
        <div class="vd-slideover-backdrop" wire:click="closePanel"></div>
        <aside class="vd-slideover-panel vd-slideover-panel-builder" role="dialog" aria-label="Visual settings">
            <header class="vd-slideover-header">
                <div>
                    <h3>Configure chart</h3>
                    <p>Pick a type, bind fields from your data, and watch the preview update.</p>
                </div>
                <button type="button" class="vd-icon-btn" wire:click="closePanel" aria-label="Close">&times;</button>
            </header>

            <div
                class="vd-builder-scroll"
                x-data="vdBuilderSplit()"
                x-on:pointerup.window="stopResize()"
                x-on:pointercancel.window="stopResize()"
                x-on:pointermove.window="onResize($event)"
            >
                <div class="vd-builder-controls" :style="`flex-basis: ${leftPct}%;`">
                    @if ($selectedVisual)
                        @include('visual-dashboards::livewire.partials.visual-type-selector', ['selectedVisual' => $selectedVisual])
                    @endif

                    @include('visual-dashboards::livewire.partials.visual-datasource-wizard')
                </div>

                <div
                    class="vd-builder-resizer"
                    role="separator"
                    aria-orientation="vertical"
                    aria-label="Resize panels"
                    title="Drag to resize"
                    @pointerdown.prevent="startResize($event)"
                ></div>

                <div class="vd-builder-preview-pane" :style="`flex-basis: ${100 - leftPct}%;`">
                    <div class="vd-builder-format">
                        @include('visual-dashboards::livewire.partials.visual-format-controls', ['selectedVisual' => $selectedVisual])
                    </div>
                    <div class="vd-builder-preview">
                        @include('visual-dashboards::livewire.partials.visual-panel-preview')
                    </div>
                </div>
            </div>
        </aside>
    </div>

    <x-filament-actions::modals />
</div>

@assets
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.1"></script>
@if (class_exists(\Filament\Support\Facades\FilamentAsset::class))
    <link rel="stylesheet" href="{{ \Filament\Support\Facades\FilamentAsset::getStyleHref('dashlet-grid') }}" data-navigate-track />
    <link rel="stylesheet" href="{{ \Filament\Support\Facades\FilamentAsset::getStyleHref('visual-dashboard') }}" data-navigate-track />
@endif
@include('visual-dashboards::livewire.partials.visual-chart-renderer-script')
<script>
    window.vdBuilderSplit = function () {
        return {
            leftPct: 38,
            resizing: false,
            startX: 0,
            startPct: 38,
            startResize(event) {
                this.resizing = true;
                this.startX = event.clientX;
                this.startPct = this.leftPct;
                document.body.classList.add('vd-resizing');
                event.target.setPointerCapture?.(event.pointerId);
            },
            onResize(event) {
                if (! this.resizing) {
                    return;
                }

                const root = this.$el;
                const width = root?.getBoundingClientRect?.().width || 1;
                const deltaPct = ((event.clientX - this.startX) / width) * 100;
                this.leftPct = Math.min(62, Math.max(28, this.startPct + deltaPct));
            },
            stopResize() {
                if (! this.resizing) {
                    return;
                }

                this.resizing = false;
                document.body.classList.remove('vd-resizing');
            },
        };
    };

    window.vdPanelPreviewChart = function (initialPayload) {
        return {
            payload: initialPayload || null,
            dataSignature: '',
            el: null,
            boot(el) {
                this.el = el;
                this.dataSignature = el?.dataset?.dataSignature || '';
                this.render();
            },
            onSync(event) {
                const detail = event?.detail || {};
                const key = this.el?.dataset?.previewSync || '';

                if (detail.key && key && detail.key !== key) {
                    return;
                }

                if (! detail.payload) {
                    return;
                }

                this.payload = detail.payload;
                this.dataSignature = detail.dataSignature || this.dataSignature;
                this.render();
            },
            render() {
                if (! this.el || ! this.payload) {
                    return;
                }

                window.spiggleRenderDashletChart?.(this.el, this.payload);
            },
        };
    };
</script>
@endassets

@include('visual-dashboards::livewire.partials.visual-grid-script')
@include('visual-dashboards::livewire.partials.visual-filter-value-combo-script')

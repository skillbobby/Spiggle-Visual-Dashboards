<div

    class="dashlet-grid-shell vd-grid-shell"

    wire:ignore

    x-on:dashlet-grid-refresh.window="refreshFromServer()"

>

    <div class="dashlet-grid-toolbar">

        <p class="dashlet-grid-help">Drag headers to move visuals. Resize from the bottom-right handle. Use Edit on a card to configure it.</p>

    </div>



    <div class="dashlet-grid vd-grid" :style="gridStyle()" :class="{ 'is-dragging': drag || resize }">

        <template x-for="item in items" :key="item.id">

            <div

                class="dashlet-grid-item vd-grid-item"

                :class="{

                    'has-border': item.payload?.visual?.border !== false,

                    'is-active': drag?.item?.id === item.id || resize?.item?.id === item.id,

                    'is-selected': Number(selectedVisualId) === Number(item.id),

                    'is-stat-card': item.payload?.type === 'stat_card',

                    'is-unconfigured': isUnconfigured(item),

                }"

                :style="itemStyle(item)"

            >

                <div

                    class="dashlet-grid-item-header"

                    @mousedown.stop="startDrag($event, item)"

                >

                    <div class="dashlet-grid-item-titles">

                        <strong x-text="item.payload?.heading || item.payload?.title || 'Visual'"></strong>

                        <span class="dashlet-grid-item-subheading" x-show="item.payload?.subheading" x-text="item.payload?.subheading"></span>

                    </div>

                    <div class="vd-widget-actions" @mousedown.stop.prevent @click.stop>

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

                        <button

                            type="button"

                            class="vd-widget-link vd-widget-link-edit"

                            title="Edit visual"

                            @click="$wire.selectVisual(Number(item.id))"

                        >Edit</button>

                        <span class="vd-widget-actions-sep" aria-hidden="true">·</span>

                        <button

                            type="button"

                            class="vd-widget-link vd-widget-link-delete"

                            title="Delete visual"

                            @click.stop="$wire.openDeleteVisual(Number(item.id))"

                        >Delete</button>

                    </div>

                </div>



                <div class="dashlet-grid-item-body">

                    <div

                        class="vd-widget-empty"

                        x-show="isUnconfigured(item)"

                        x-cloak

                        role="button"

                        tabindex="0"

                        @keydown.enter.prevent="$wire.selectVisual(Number(item.id), 'datasource')"

                        @keydown.space.prevent="$wire.selectVisual(Number(item.id), 'datasource')"

                        @click.stop="$wire.selectVisual(Number(item.id), 'datasource')"

                    >

                        <div class="vd-widget-empty-icon" aria-hidden="true">📊</div>

                        <p x-text="item.payload?.empty_message || 'Select a data source and field mapping to display this visual.'"></p>

                        <button

                            type="button"

                            class="vd-btn vd-btn-muted vd-widget-empty-action"

                            @click.stop="$wire.selectVisual(Number(item.id), 'datasource')"

                        >Select data source</button>

                    </div>



                    <div

                        class="dashlet-stat-card"

                        x-show="! isUnconfigured(item) && item.payload?.type === 'stat_card'"

                        x-cloak

                    >

                        @include('visual-dashboards::livewire.partials.dashlet-kpi-card-alpine')

                    </div>



                    <div

                        class="dashlet-table-wrap"

                        x-show="! isUnconfigured(item) && item.payload?.type === 'table'"

                        x-cloak

                    >

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

                                            <td x-text="row[column]"></td>

                                        </template>

                                    </tr>

                                </template>

                            </tbody>

                        </table>

                    </div>



                    <div

                        class="dashlet-chart-wrap"

                        x-show="! isUnconfigured(item) && isChartType(item.payload?.type)"

                        x-cloak

                    >

                        <div

                            class="dashlet-chart-host"

                            wire:ignore.self

                            :data-dashlet-id="item.id"

                            x-init="mountChart($el, item)"

                        ></div>

                    </div>

                </div>



                <button type="button" class="dashlet-resize-handle" @mousedown.stop.prevent="startResize($event, item)"></button>

            </div>

        </template>

    </div>

</div>


<?php

namespace Spiggle\VisualDashboards\Filament\Support;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\HtmlString;
use Spiggle\VisualDashboards\DataSources\DatabaseTableDataSource;
use Spiggle\VisualDashboards\Models\Dashlet;
use Spiggle\VisualDashboards\Services\DashletRegistry;
use Spiggle\VisualDashboards\Services\DataEngine;
use Spiggle\VisualDashboards\Support\DashletAccess;
use Spiggle\VisualDashboards\Support\DashletTypes;
use Spiggle\VisualDashboards\Support\FeatureCatalog;
use Spiggle\VisualDashboards\Support\QueryConfig;
use Spiggle\VisualDashboards\Support\ValueFormatter;

class DashletFormSchema
{
    /**
     * @return array<int, Step>
     */
    public static function steps(): array
    {
        $debounce = (int) config('visual-dashboards.preview.debounce_ms', 300);

        return [
            Step::make('Data source')
                ->description('Where the numbers come from.')
                ->icon('heroicon-o-circle-stack')
                ->schema(self::dataSourceStep($debounce)),
            Step::make('Filter data')
                ->description('Optional. Narrow the query.')
                ->icon('heroicon-o-funnel')
                ->schema(self::filterStep($debounce)),
            Step::make('Transform')
                ->description('Optional. Group and aggregate.')
                ->icon('heroicon-o-calculator')
                ->schema(self::transformStep($debounce)),
            Step::make('Visualization')
                ->description('Chart type and field mapping.')
                ->icon('heroicon-o-chart-bar')
                ->schema(self::visualizationStep($debounce)),
            Step::make('Finalize')
                ->description('Name, style, then live preview.')
                ->icon('heroicon-o-check-circle')
                ->schema(self::finalizeStep($debounce)),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public static function components(): array
    {
        return self::steps();
    }

    /**
     * @return array<int, mixed>
     */
    protected static function dataSourceStep(int $debounce): array
    {
        return [
            Section::make('Dataset')
                ->description('Pick a foundational source. You can filter and chart it in the next steps.')
                ->icon('heroicon-o-circle-stack')
                ->compact()
                ->schema([
                    Radio::make('source_kind')
                        ->label('Source type')
                        ->options([
                            'model' => 'Data model',
                            'table' => 'Database table',
                            'api' => 'Live API',
                            'file' => 'CSV file',
                        ])
                        ->descriptions([
                            'model' => 'Users, form submissions, sample metrics, and other registered models.',
                            'table' => 'A SQL table from the allow-list in config.',
                            'api' => 'GET a JSON list from an HTTPS endpoint.',
                            'file' => 'Upload a CSV; columns become fields.',
                        ])
                        ->default('model')
                        ->required()
                        ->columns(2)
                        ->live(debounce: $debounce)
                        ->afterStateUpdated(function (Set $set): void {
                            $set('columns', []);
                            $set('group_by', []);
                            $set('aggregations', []);
                            $set('filter_groups.conditions', []);
                            $set('filter_groups.or_conditions', []);
                        }),
                ]),
            Section::make('Data model')
                ->description('Registered Eloquent datasets for this app.')
                ->compact()
                ->visible(fn (Get $get): bool => (string) $get('source_kind') === 'model')
                ->schema([
                    Select::make('data_source')
                        ->label('Model')
                        ->options(fn (): array => app(DashletRegistry::class)->dataSourceLabels())
                        ->default('sample_metrics')
                        ->required()
                        ->native(false)
                        ->searchable()
                        ->live(debounce: $debounce)
                        ->afterStateUpdated(function (Set $set): void {
                            $set('columns', []);
                            $set('filters', []);
                            $set('filter_groups.conditions', []);
                            $set('filter_groups.or_conditions', []);
                            $set('join.target', null);
                        }),
                ]),
            Section::make('Database table')
                ->description('Only tables listed in config/dashlets.php appear here.')
                ->compact()
                ->visible(fn (Get $get): bool => (string) $get('source_kind') === 'table')
                ->schema([
                    Select::make('table_name')
                        ->label('Table')
                        ->options(fn (): array => DatabaseTableDataSource::tableOptions())
                        ->required()
                        ->native(false)
                        ->searchable()
                        ->live(debounce: $debounce),
                ]),
            Section::make('Live API')
                ->description('The response should be a JSON array of objects, or an object with a list at the JSON path.')
                ->compact()
                ->visible(fn (Get $get): bool => (string) $get('source_kind') === 'api')
                ->schema([
                    TextInput::make('api.url')
                        ->label('URL')
                        ->url()
                        ->required()
                        ->live(debounce: $debounce)
                        ->placeholder('https://example.com/metrics.json')
                        ->columnSpanFull(),
                    TextInput::make('api.json_path')
                        ->label('JSON path')
                        ->live(debounce: $debounce)
                        ->placeholder('data.items')
                        ->helperText('Leave blank if the root is already a list.'),
                    KeyValue::make('api.headers')
                        ->label('Headers')
                        ->addActionLabel('Add header')
                        ->keyLabel('Header')
                        ->valueLabel('Value')
                        ->reorderable(false)
                        ->columnSpanFull(),
                ])
                ->columns(1),
            Section::make('CSV file')
                ->description('First row should be column headers.')
                ->compact()
                ->visible(fn (Get $get): bool => (string) $get('source_kind') === 'file')
                ->schema([
                    FileUpload::make('file_path')
                        ->label('File')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->disk('local')
                        ->directory('dashlet-files')
                        ->visibility('private')
                        ->required()
                        ->live(),
                ]),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected static function filterStep(int $debounce): array
    {
        return [
            Section::make('Time window')
                ->description('Skip this step to include every matching row. Windows cut the dataset before charts run.')
                ->compact()
                ->columns(2)
                ->visible(fn (Get $get): bool => filled(self::sourceKey($get)))
                ->schema([
                    Select::make('window')
                        ->label('Rolling window')
                        ->placeholder('No window')
                        ->native(false)
                        ->options([
                            '' => 'None',
                            'last_7_days' => 'Last 7 days',
                            'last_30_days' => 'Last 30 days',
                            'last_90_days' => 'Last 90 days',
                            'custom' => 'Custom days',
                        ])
                        ->live(debounce: $debounce),
                    TextInput::make('filters.days')
                        ->label('Custom days')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(365)
                        ->visible(fn (Get $get): bool => (string) $get('window') === 'custom'),
                ]),
            Section::make('Source filters')
                ->description('Fields that belong to this dataset.')
                ->compact()
                ->columns(2)
                ->visible(fn (Get $get): bool => filled(self::sourceKey($get)))
                ->schema(self::dynamicFilterFields($debounce)),
            Section::make('Conditions')
                ->description('AND conditions must all match. OR conditions match any one.')
                ->compact()
                ->visible(fn (Get $get): bool => filled(self::sourceKey($get)))
                ->schema([
                    Repeater::make('filter_groups.conditions')
                        ->label('Match all (AND)')
                        ->schema(self::filterConditionSchema())
                        ->defaultItems(0)
                        ->compact()
                        ->cloneable()
                        ->itemLabel(fn (array $state): ?string => self::conditionLabel($state))
                        ->addActionLabel('Add AND condition')
                        ->live(debounce: $debounce)
                        ->columns(3),
                    Repeater::make('filter_groups.or_conditions')
                        ->label('Match any (OR)')
                        ->schema(self::filterConditionSchema())
                        ->defaultItems(0)
                        ->compact()
                        ->cloneable()
                        ->itemLabel(fn (array $state): ?string => self::conditionLabel($state))
                        ->addActionLabel('Add OR condition')
                        ->live(debounce: $debounce)
                        ->columns(3),
                ]),
            Section::make('Related records')
                ->description('Optional join, for example form submissions → users.')
                ->compact()
                ->collapsed()
                ->columns(3)
                ->visible(fn (Get $get): bool => (string) $get('source_kind') === 'model' && self::joinOptions((string) $get('data_source')) !== [])
                ->schema(self::joinFields($debounce)),
            Html::make(new HtmlString(
                '<p class="text-sm text-gray-500 dark:text-gray-400">Select a data source on the previous step to filter it.</p>'
            ))->visible(fn (Get $get): bool => blank(self::sourceKey($get))),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected static function transformStep(int $debounce): array
    {
        return [
            Section::make('Group and aggregate')
                ->description('Leave blank to chart raw rows. Grouped dimensions become categories; aggregations become values.')
                ->compact()
                ->schema([
                    Select::make('group_by')
                        ->label('Group by')
                        ->multiple()
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->options(fn (Get $get): array => self::columnOptionsForGet($get))
                        ->live(debounce: $debounce)
                        ->placeholder('No grouping')
                        ->helperText('Example: region, status, or month.'),
                    Repeater::make('aggregations')
                        ->label('Aggregations')
                        ->schema([
                            Select::make('fn')
                                ->label('Function')
                                ->options([
                                    'COUNT' => 'COUNT',
                                    'SUM' => 'SUM',
                                    'AVG' => 'AVG',
                                    'MIN' => 'MIN',
                                    'MAX' => 'MAX',
                                ])
                                ->native(false)
                                ->required()
                                ->live(),
                            Select::make('field')
                                ->label('Field')
                                ->options(fn (Get $get): array => ['*' => 'All rows', ...self::columnOptionsForGet($get)])
                                ->required()
                                ->native(false)
                                ->searchable(),
                            TextInput::make('alias')
                                ->label('Alias')
                                ->required()
                                ->maxLength(40)
                                ->placeholder('total'),
                        ])
                        ->defaultItems(0)
                        ->compact()
                        ->cloneable()
                        ->itemLabel(fn (array $state): ?string => filled($state['fn'] ?? null)
                            ? trim(($state['fn'] ?? '').'('.($state['field'] ?? '*').') '.($state['alias'] ?? ''))
                            : null)
                        ->addActionLabel('Add aggregation')
                        ->live(debounce: $debounce)
                        ->columns(3),
                ]),
            Section::make('Calculated fields')
                ->description('Optional. Simple a + b, a − b, a × b, or a ÷ b using row fields.')
                ->compact()
                ->collapsed()
                ->schema([
                    Repeater::make('calculated_fields')
                        ->hiddenLabel()
                        ->schema([
                            TextInput::make('name')->label('Name')->required()->maxLength(40),
                            TextInput::make('expression')
                                ->label('Expression')
                                ->placeholder('total / count')
                                ->required(),
                        ])
                        ->defaultItems(0)
                        ->compact()
                        ->cloneable()
                        ->addActionLabel('Add calculated field')
                        ->live(debounce: $debounce)
                        ->columns(2),
                ]),
            Section::make('Table columns')
                ->description('Used when the output is a table. Leave empty to show every field.')
                ->compact()
                ->collapsed()
                ->schema([
                    Select::make('columns')
                        ->label('Visible columns')
                        ->multiple()
                        ->native(false)
                        ->searchable()
                        ->options(fn (Get $get): array => self::columnOptionsForGet($get))
                        ->visible(fn (Get $get): bool => self::columnOptionsForGet($get) !== [])
                        ->live(debounce: $debounce),
                ]),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected static function visualizationStep(int $debounce): array
    {
        return [
            Section::make('Output')
                ->description('KPI cards show a single number. Charts need categories and values.')
                ->compact()
                ->schema([
                    ToggleButtons::make('type')
                        ->label('Visual')
                        ->options(fn (): array => FeatureCatalog::typeLabels())
                        ->icons(self::typeIcons())
                        ->colors(self::typeColors())
                        ->default('stat_card')
                        ->required()
                        ->columns(['default' => 2, 'md' => 4])
                        ->live(debounce: $debounce)
                        ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                            self::suggestHeading($get, $set, $state);
                        })
                        ->helperText(fn (Get $get): ?string => FeatureCatalog::typeHelperText((string) $get('type'))),
                    Toggle::make('visual.period_filter')
                        ->label('Show Day / Week / Month / Year on the card')
                        ->default(fn (Get $get): bool => (string) $get('data_source') === 'login_activity')
                        ->inline(false)
                        ->live(debounce: $debounce),
                    Select::make('visual.default_grain')
                        ->label('Default period')
                        ->options([
                            'day' => 'Day',
                            'week' => 'Week',
                            'month' => 'Month',
                            'year' => 'Year',
                        ])
                        ->default('year')
                        ->native(false)
                        ->visible(fn (Get $get): bool => (bool) $get('visual.period_filter')),
                ]),
            Section::make('Map data')
                ->description(fn (Get $get): string => \Spiggle\VisualDashboards\Support\VisualTypes::fieldBlueprint((string) $get('type'))['intro'] ?? 'Bind fields from the selected source.')
                ->compact()
                ->columns(2)
                ->visible(fn (Get $get): bool => ! in_array((string) $get('type'), ['stat_card', 'hero', 'insight_list', 'table'], true))
                ->schema([
                    Select::make('mapping.x')
                        ->label(fn (Get $get): string => \Spiggle\VisualDashboards\Support\VisualTypes::mappingLabels((string) $get('type'))['x'])
                        ->native(false)
                        ->searchable()
                        ->options(fn (Get $get): array => self::columnOptionsForGet($get))
                        ->live(debounce: $debounce)
                        ->placeholder('Select a field…')
                        ->helperText(fn (Get $get): ?string => \Spiggle\VisualDashboards\Support\VisualTypes::mappingLabels((string) $get('type'))['x_help']),
                    Select::make('mapping.y')
                        ->label(fn (Get $get): string => \Spiggle\VisualDashboards\Support\VisualTypes::mappingLabels((string) $get('type'))['y'])
                        ->native(false)
                        ->searchable()
                        ->options(fn (Get $get): array => self::columnOptionsForGet($get))
                        ->live(debounce: $debounce)
                        ->placeholder('Select a field…')
                        ->helperText(fn (Get $get): ?string => \Spiggle\VisualDashboards\Support\VisualTypes::mappingLabels((string) $get('type'))['y_help']),
                    Select::make('mapping.series')
                        ->label(fn (Get $get): string => \Spiggle\VisualDashboards\Support\VisualTypes::mappingLabels((string) $get('type'))['series'])
                        ->native(false)
                        ->searchable()
                        ->options(fn (Get $get): array => self::columnOptionsForGet($get))
                        ->live(debounce: $debounce)
                        ->placeholder('None (single series)')
                        ->helperText(fn (Get $get): ?string => \Spiggle\VisualDashboards\Support\VisualTypes::mappingLabels((string) $get('type'))['series_help'])
                        ->visible(fn (Get $get): bool => \Spiggle\VisualDashboards\Support\VisualTypes::mappingLabels((string) $get('type'))['show_series']),
                ]),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected static function finalizeStep(int $debounce): array
    {
        return [
            Section::make('Labels')
                ->description('Shown on the card header.')
                ->compact()
                ->columns(2)
                ->schema([
                    TextInput::make('heading')
                        ->label('Heading')
                        ->required()
                        ->maxLength(120)
                        ->live(debounce: $debounce)
                        ->default(fn (Get $get): string => self::defaultTitle((string) $get('type')))
                        ->placeholder('Users this month'),
                    TextInput::make('subheading')
                        ->label('Subheading')
                        ->required()
                        ->maxLength(180)
                        ->live(debounce: $debounce)
                        ->placeholder('What this dashlet shows'),
                ]),
            Section::make('Appearance')
                ->description('Number format, colors, and axis titles.')
                ->compact()
                ->collapsed()
                ->columns(2)
                ->schema([
                    Select::make('visual.number_format')
                        ->label('Number format')
                        ->native(false)
                        ->options(ValueFormatter::numberFormats())
                        ->default('number')
                        ->live(debounce: $debounce),
                    TextInput::make('visual.currency')
                        ->label('Currency')
                        ->default('USD')
                        ->maxLength(8)
                        ->visible(fn (Get $get): bool => (string) $get('visual.number_format') === 'currency'),
                    Select::make('visual.color_scheme')
                        ->label('Color scheme')
                        ->native(false)
                        ->options(ValueFormatter::colorSchemes())
                        ->default('amber')
                        ->live(debounce: $debounce)
                        ->afterStateUpdated(function (?string $state, Set $set): void {
                            $set('visual.color', ValueFormatter::colorForScheme($state));
                        }),
                    ColorPicker::make('visual.color')
                        ->label('Accent color')
                        ->default('#f59e0b')
                        ->live(debounce: $debounce),
                    TextInput::make('visual.x_label')
                        ->label('X-axis label')
                        ->maxLength(80)
                        ->live(debounce: $debounce)
                        ->visible(fn (Get $get): bool => ! in_array((string) $get('type'), ['stat_card', 'hero', 'insight_list', 'table', 'pie', 'donut'], true)),
                    TextInput::make('visual.y_label')
                        ->label('Y-axis label')
                        ->maxLength(80)
                        ->live(debounce: $debounce)
                        ->visible(fn (Get $get): bool => ! in_array((string) $get('type'), ['stat_card', 'hero', 'insight_list', 'table', 'pie', 'donut'], true)),
                    Toggle::make('visual.border')
                        ->label('Show card border')
                        ->default(true)
                        ->inline(false)
                        ->live(debounce: $debounce),
                ]),
            Section::make('Who can see this')
                ->description('Leave empty for anyone who can open the dashboard.')
                ->compact()
                ->collapsed()
                ->visible(fn (): bool => DashletAccess::roleOptions() !== [] || DashletAccess::permissionOptions() !== [])
                ->schema([
                    CheckboxList::make('access_roles')
                        ->label('Roles')
                        ->options(fn (): array => DashletAccess::roleOptions())
                        ->columns(2)
                        ->visible(fn (): bool => DashletAccess::roleOptions() !== []),
                    CheckboxList::make('access_permissions')
                        ->label('Permissions')
                        ->options(fn (): array => DashletAccess::permissionOptions())
                        ->columns(2)
                        ->visible(fn (): bool => DashletAccess::permissionOptions() !== []),
                ]),
            Section::make('Live preview')
                ->description('Uses the query from previous steps. A loader appears while it refreshes.')
                ->icon('heroicon-o-eye')
                ->compact()
                ->schema([
                    Html::make(fn (Get $get): HtmlString => self::previewHtml($get))
                        ->columnSpanFull()
                        ->visible(fn (Get $get): bool => filled(self::sourceKey($get))),
                    Html::make(new HtmlString(
                        '<p class="text-sm text-gray-500 dark:text-gray-400">Go back to Data source if the preview is empty.</p>'
                    ))->visible(fn (Get $get): bool => blank(self::sourceKey($get))),
                ]),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected static function dynamicFilterFields(int $debounce = 300): array
    {
        $registry = app(DashletRegistry::class);
        $fields = [];
        $addedFormFilter = false;

        foreach ($registry->allDataSources() as $source) {
            foreach ($source->filterSchema() as $key => $label) {
                if ($key === 'days') {
                    continue;
                }

                if (in_array($source->key(), ['form_submissions', 'form_audit_logs'], true) && $key === 'form_id') {
                    if ($addedFormFilter) {
                        continue;
                    }

                    $addedFormFilter = true;
                    $fields[] = Select::make('filters.form_id')
                        ->label($label)
                        ->options(fn (): array => self::formOptions())
                        ->searchable()
                        ->native(false)
                        ->live(debounce: $debounce)
                        ->visible(fn (Get $get): bool => (string) $get('source_kind') === 'model'
                            && in_array((string) $get('data_source'), ['form_submissions', 'form_audit_logs'], true));

                    continue;
                }

                if ($source->key() === 'form_submissions' && $key === 'status') {
                    $fields[] = Select::make('filters.status')
                        ->label($label)
                        ->options(self::submissionStatusOptions())
                        ->placeholder('Any status')
                        ->native(false)
                        ->live(debounce: $debounce)
                        ->visible(fn (Get $get): bool => (string) $get('source_kind') === 'model' && (string) $get('data_source') === $source->key());

                    continue;
                }

                if ($source->key() === 'ai_spend' && $key === 'metric') {
                    $fields[] = Select::make('filters.metric')
                        ->label($label)
                        ->options([
                            'spend' => 'Total spend',
                            'projected' => 'Projected spend',
                            'anomalies' => 'Anomalies',
                            'growing' => 'Top growing service',
                            'tokens' => 'Tokens',
                            'rate' => 'Rate',
                            'insights' => 'Recommendations',
                        ])
                        ->default('spend')
                        ->native(false)
                        ->live(debounce: $debounce)
                        ->visible(fn (Get $get): bool => (string) $get('source_kind') === 'model' && (string) $get('data_source') === $source->key());

                    continue;
                }

                if ($source->key() === 'login_activity' && $key === 'grain') {
                    $fields[] = Select::make('filters.grain')
                        ->label($label)
                        ->options([
                            'day' => 'Day',
                            'week' => 'Week',
                            'month' => 'Month',
                            'year' => 'Year',
                        ])
                        ->default('year')
                        ->native(false)
                        ->live(debounce: $debounce)
                        ->helperText('Also available as in-dashlet Day / Week / Month / Year buttons.')
                        ->visible(fn (Get $get): bool => (string) $get('source_kind') === 'model' && (string) $get('data_source') === $source->key());

                    continue;
                }

                if ($source->key() === 'form_audit_logs' && $key === 'action') {
                    $fields[] = Select::make('filters.action')
                        ->label($label)
                        ->options(fn (): array => app(\Spiggle\VisualDashboards\DataSources\FormAuditLogDataSource::class)->actionOptions())
                        ->placeholder('Any action')
                        ->native(false)
                        ->searchable()
                        ->live(debounce: $debounce)
                        ->visible(fn (Get $get): bool => (string) $get('source_kind') === 'model' && (string) $get('data_source') === $source->key());

                    continue;
                }

                $fields[] = TextInput::make("filters.{$key}")
                    ->label($label)
                    ->live(debounce: $debounce)
                    ->visible(fn (Get $get): bool => (string) $get('source_kind') === 'model' && (string) $get('data_source') === $source->key());
            }
        }

        return $fields;
    }

    /**
     * @return array<int, Select>
     */
    protected static function joinFields(int $debounce = 300): array
    {
        return [
            Select::make('join.target')
                ->label('Link to')
                ->placeholder('None')
                ->native(false)
                ->options(fn (Get $get): array => self::joinOptions((string) $get('data_source')))
                ->live(debounce: $debounce),
            Select::make('join.local_key')
                ->label('Local key')
                ->native(false)
                ->options(['user_id' => 'user_id'])
                ->default('user_id')
                ->visible(fn (Get $get): bool => filled($get('join.target'))),
            Select::make('join.foreign_key')
                ->label('Foreign key')
                ->native(false)
                ->options(['id' => 'id'])
                ->default('id')
                ->visible(fn (Get $get): bool => filled($get('join.target'))),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected static function conditionLabel(array $state): ?string
    {
        $field = trim((string) ($state['field'] ?? ''));

        if ($field === '') {
            return null;
        }

        $operator = str_replace('_', ' ', (string) ($state['operator'] ?? 'equals'));
        $value = (string) ($state['value'] ?? '');

        return trim($field.' '.$operator.' '.$value);
    }

    /**
     * @return array<string, string>
     */
    protected static function typeIcons(): array
    {
        return [
            'hero' => 'heroicon-o-hand-raised',
            'stat_card' => 'heroicon-o-squares-2x2',
            'insight_list' => 'heroicon-o-sparkles',
            'table' => 'heroicon-o-table-cells',
            'line' => 'heroicon-o-chart-bar',
            'bar' => 'heroicon-o-chart-bar',
            'column' => 'heroicon-o-bars-3-bottom-left',
            'stacked_column' => 'heroicon-o-chart-bar',
            'scatter' => 'heroicon-o-viewfinder-circle',
            'pie' => 'heroicon-o-chart-pie',
            'donut' => 'heroicon-o-chart-pie',
            'funnel' => 'heroicon-o-funnel',
            'radialbar' => 'heroicon-o-arrow-path',
            'radar' => 'heroicon-o-signal',
            'container' => 'heroicon-o-rectangle-group',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function typeColors(): array
    {
        return [
            'hero' => 'gray',
            'stat_card' => 'warning',
            'insight_list' => 'success',
            'table' => 'gray',
            'line' => 'info',
            'bar' => 'primary',
            'column' => 'primary',
            'stacked_column' => 'info',
            'scatter' => 'warning',
            'pie' => 'success',
            'donut' => 'success',
            'funnel' => 'danger',
            'radar' => 'info',
            'radialbar' => 'warning',
            'container' => 'gray',
        ];
    }

    protected static function suggestHeading(Get $get, Set $set, ?string $type): void
    {
        $heading = trim((string) $get('heading'));
        $typeTitles = array_values(DashletTypes::labels());

        if ($heading !== '' && ! in_array($heading, $typeTitles, true)) {
            return;
        }

        $set('heading', self::defaultTitle((string) ($type ?: 'stat_card')));
    }

    /**
     * @return array<int, Select|TextInput>
     */
    protected static function filterConditionSchema(): array
    {
        return [
            Select::make('field')
                ->label('Field')
                ->options(fn (Get $get): array => self::columnOptionsForGet($get))
                ->required()
                ->searchable()
                ->live(),
            Select::make('operator')
                ->label('Operator')
                ->options([
                    'equals' => 'Equals',
                    'not_equals' => 'Not equals',
                    'contains' => 'Contains',
                    'gt' => 'Greater than',
                    'gte' => 'Greater or equal',
                    'lt' => 'Less than',
                    'lte' => 'Less or equal',
                    'is_empty' => 'Is empty',
                    'is_not_empty' => 'Is not empty',
                ])
                ->default('equals')
                ->required()
                ->live(),
            TextInput::make('value')
                ->label('Value')
                ->live(debounce: 300)
                ->visible(fn (Get $get): bool => ! in_array((string) $get('operator'), ['is_empty', 'is_not_empty'], true)),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function columnOptionsForGet(Get $get): array
    {
        $sourceKind = (string) (
            $get('source_kind', isAbsolute: true)
            ?: $get('../../source_kind')
            ?: $get('../../../source_kind')
            ?: 'model'
        );
        $sourceKey = (string) (
            $get('data_source', isAbsolute: true)
            ?: $get('../../data_source')
            ?: $get('../../../data_source')
        );
        $tableName = (string) (
            $get('table_name', isAbsolute: true)
            ?: $get('../../table_name')
            ?: $get('../../../table_name')
        );
        $filters = $get('filters', isAbsolute: true)
            ?: $get('../../filters')
            ?: $get('../../../filters')
            ?: [];
        $api = $get('api', isAbsolute: true)
            ?: $get('../../api')
            ?: [];
        $filePath = $get('file_path', isAbsolute: true)
            ?: $get('../../file_path');

        return self::columnOptions($sourceKind, $sourceKey, is_array($filters) ? $filters : [], $tableName, is_array($api) ? $api : [], $filePath);
    }

    protected static function sourceKey(Get $get): string
    {
        return match ((string) $get('source_kind')) {
            'table' => (string) $get('table_name'),
            'api' => (string) data_get($get('api'), 'url'),
            'file' => (string) $get('file_path'),
            default => (string) $get('data_source'),
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $api
     * @return array<string, string>
     */
    protected static function columnOptions(
        string $sourceKind,
        string $sourceKey,
        array $filters = [],
        string $tableName = '',
        array $api = [],
        mixed $filePath = null,
    ): array {
        try {
            $source = app(DataEngine::class)->resolveFromConfig([
                'source_kind' => $sourceKind,
                'data_source' => $sourceKey,
                'table_name' => $tableName,
                'api' => $api,
                'file' => ['path' => $filePath],
                'filters' => $filters,
            ]);

            return $source->columns(['filters' => $filters]);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, string>
     */
    protected static function joinOptions(string $sourceKey): array
    {
        if ($sourceKey === '') {
            return [];
        }

        try {
            return app(DashletRegistry::class)->dataSource($sourceKey)->joinTargets();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<int|string, string>
     */
    protected static function formOptions(): array
    {
        if (! class_exists(\Spiggle\FormBuilder\Models\Form::class)) {
            return [];
        }

        return \Spiggle\FormBuilder\Models\Form::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected static function submissionStatusOptions(): array
    {
        $statuses = config('form-builder.submissions.statuses', []);

        return is_array($statuses) ? $statuses : [];
    }

    protected static function defaultTitle(string $type): string
    {
        return FeatureCatalog::typeTitle($type);
    }

    protected static function previewHtml(Get $get): HtmlString
    {
        if (self::sourceKey($get) === '') {
            return new HtmlString('<p class="text-sm text-gray-500">Select a data source to preview.</p>');
        }

        try {
            $payload = app(DataEngine::class)->previewPayloadFromForm([
                'source_kind' => $get('source_kind'),
                'data_source' => $get('data_source'),
                'table_name' => $get('table_name'),
                'api' => $get('api') ?? [],
                'file_path' => $get('file_path'),
                'filters' => $get('filters') ?? [],
                'window' => $get('window'),
                'filter_groups' => $get('filter_groups') ?? [],
                'join' => $get('join') ?? [],
                'columns' => $get('columns') ?? [],
                'group_by' => $get('group_by') ?? [],
                'aggregations' => $get('aggregations') ?? [],
                'calculated_fields' => $get('calculated_fields') ?? [],
                'mapping' => $get('mapping') ?? [],
                'type' => $get('type') ?: 'stat_card',
                'visual' => $get('visual') ?? [],
            ]);

            return new HtmlString(view('visual-dashboards::filament.components.dashlet-viz-preview', [
                'payload' => $payload,
                'visual' => $get('visual') ?? [],
                'title' => (string) ($get('heading') ?: 'Preview'),
                'subheading' => (string) ($get('subheading') ?: ''),
                'type' => (string) ($get('type') ?: 'stat_card'),
            ])->render());
        } catch (\Throwable $exception) {
            return new HtmlString('<p class="text-sm text-danger-600">Preview unavailable: '.e($exception->getMessage()).'</p>');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function formStateFromDashlet(Dashlet $dashlet): array
    {
        $config = $dashlet->normalizedConfig();

        return [
            'source_kind' => $config['source_kind'] ?? 'model',
            'data_source' => $config['data_source'],
            'table_name' => $config['table_name'] ?? '',
            'api' => $config['api'] ?? [],
            'file_path' => data_get($config, 'file.path'),
            'filters' => $config['filters'] ?? [],
            'window' => $config['window'] ?? data_get($config, 'filters.window'),
            'filter_groups' => $config['filter_groups'] ?? [],
            'join' => $config['join'] ?? [],
            'columns' => $config['columns'] ?? [],
            'group_by' => $config['group_by'] ?? [],
            'aggregations' => $config['aggregations'] ?? [],
            'calculated_fields' => $config['calculated_fields'] ?? [],
            'mapping' => $config['mapping'] ?? [],
            'type' => $dashlet->type,
            'heading' => $config['heading'] ?: $config['title'],
            'subheading' => $config['subheading'] ?: 'Dashlet',
            'visual' => $config['visual'] ?? [],
            'access_roles' => $config['access_roles'] ?? [],
            'access_permissions' => $config['access_permissions'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function configFromFormData(array $data): array
    {
        $query = QueryConfig::fromFormData($data);
        $heading = (string) ($data['heading'] ?? $data['title'] ?? 'Dashlet');
        $visual = is_array($data['visual'] ?? null) ? $data['visual'] : [];
        $visual['color'] = ValueFormatter::colorForScheme(
            $visual['color_scheme'] ?? null,
            $visual['color'] ?? '#f59e0b'
        );

        return [
            'title' => $heading,
            'heading' => $heading,
            'subheading' => (string) ($data['subheading'] ?? ''),
            'source_kind' => $query['source_kind'],
            'data_source' => $query['data_source'],
            'table_name' => $query['table_name'],
            'api' => $query['api'],
            'file' => $query['file'],
            'filters' => array_filter($query['filters'] ?? []),
            'window' => $query['window'],
            'filter_groups' => $query['filter_groups'] ?? [],
            'join' => array_filter($query['join'] ?? []),
            'columns' => $query['columns'] ?? [],
            'group_by' => $query['group_by'] ?? [],
            'aggregations' => $query['aggregations'] ?? [],
            'calculated_fields' => $query['calculated_fields'] ?? [],
            'mapping' => $query['mapping'] ?? [],
            'visual' => $visual,
            'access_roles' => array_values(array_filter($data['access_roles'] ?? [])),
            'access_permissions' => array_values(array_filter($data['access_permissions'] ?? [])),
        ];
    }

    public static function assertAllowedType(string $type): void
    {
        FeatureCatalog::assertAllowed($type);
    }
}

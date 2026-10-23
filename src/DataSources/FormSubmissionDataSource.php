<?php



namespace Spiggle\VisualDashboards\DataSources;



use Carbon\Carbon;

use Illuminate\Support\Facades\Schema;

use Spiggle\VisualDashboards\Support\PeriodRangeFilter;
use Spiggle\VisualDashboards\Support\QueryConfig;

use Spiggle\VisualDashboards\Support\QueryFilterApplier;

use Spiggle\VisualDashboards\Support\TimeGrainBuckets;



class FormSubmissionDataSource extends BaseDataSource

{

    public function key(): string

    {

        return 'form_submissions';

    }



    public function label(): string

    {

        return 'Form submissions';

    }



    /**

     * @return array<string, string>

     */

    public function filterSchema(): array

    {

        return [

            'form_id' => 'Form',

            'form_slug' => 'Form slug',

            'status' => 'Status',

            'days' => 'Days back',

            'grain' => 'Period',

        ];

    }



    /**

     * @param  array<string, mixed>  $context

     * @return array<string, string>

     */

    public function columns(array $context = []): array

    {

        $columns = [

            'id' => 'ID',

            'form_id' => 'Form ID',

            'form_name' => 'Form',

            'status' => 'Status',

            'user_id' => 'User ID',

            'created_at' => 'Created at',

        ];



        foreach ($this->payloadColumns($context) as $key => $label) {

            $columns[$key] = $label;

        }



        return $columns;

    }



    /**

     * @return array<string, string>

     */

    public function joinTargets(): array

    {

        return [

            'users' => 'Users',

        ];

    }



    /**

     * @param  array<string, mixed>  $config

     * @return array<int, array<string, mixed>>

     */

    public function previewRows(array $config = [], int $limit = 25): array

    {

        if (! $this->available()) {

            return [];

        }



        $normalized = QueryConfig::normalize($config);

        $query = $this->baseQuery($normalized);

        QueryFilterApplier::apply($query, $normalized, $this->submissionTable());



        $rows = $query

            ->orderByDesc('created_at')

            ->limit($limit)

            ->get()

            ->map(fn ($row): array => $this->mapSubmissionRow((array) $row, $normalized))

            ->all();



        return $this->finalizeRows($rows, $config);

    }



    /**

     * @param  array<string, mixed>  $config

     */

    public function chartPayload(string $type, array $config = []): array

    {

        if (! $this->available()) {

            return $this->toChartPayload($type, ['None'], [0], 'Submissions');

        }



        $normalized = QueryConfig::normalize($config);

        $visual = is_array($config['visual'] ?? null) ? $config['visual'] : [];



        if ($this->shouldUseTimeGrain($normalized, $visual) && $this->handlesAggregationInChartPayload($normalized)) {

            return $this->timeSeriesPayload($type, $normalized, $visual);

        }



        return $this->toChartPayload($type, [], [], 'Submissions');

    }



    /**

     * @param  array<string, mixed>  $config

     */

    public function handlesAggregationInChartPayload(array $config): bool

    {

        $visual = is_array($config['visual'] ?? null) ? $config['visual'] : [];



        if (! $this->shouldUseTimeGrain($config, $visual)) {

            return false;

        }



        $mapping = is_array($config['mapping'] ?? null) ? $config['mapping'] : [];



        if (filled($mapping['series'] ?? '')) {

            return false;

        }



        $groupBy = is_array($config['group_by'] ?? null) ? $config['group_by'] : [];



        return count($groupBy) <= 1;

    }



    public function available(): bool

    {

        return class_exists(\Spiggle\FormBuilder\Models\FormSubmission::class)

            && Schema::hasTable($this->submissionTable());

    }



    /**

     * @param  array<string, mixed>  $normalized

     */

    protected function baseQuery(array $normalized): \Illuminate\Database\Query\Builder

    {

        $filters = $normalized['filters'];

        $table = $this->submissionTable();

        $query = \Illuminate\Support\Facades\DB::table($table);



        $formId = $this->resolveFormId($filters);



        if ($formId !== null) {

            $query->where('form_id', $formId);

        }



        if (filled($filters['status'] ?? null)) {

            $query->where('status', (string) $filters['status']);

        }



        $days = QueryConfig::windowDays($normalized) ?? (int) ($filters['days'] ?? 0);

        PeriodRangeFilter::applyCreatedAtWindow($query, $normalized);

        return $query;

    }



    /**

     * @param  array<string, mixed>  $filters

     */

    protected function resolveFormId(array $filters): ?int

    {

        if (filled($filters['form_id'] ?? null)) {

            return (int) $filters['form_id'];

        }



        $slug = (string) ($filters['form_slug'] ?? '');



        if ($slug === '' || ! class_exists(\Spiggle\FormBuilder\Models\Form::class)) {

            return null;

        }



        $form = \Spiggle\FormBuilder\Models\Form::query()->where('slug', $slug)->first();



        return $form ? (int) $form->getKey() : null;

    }



    /**

     * @param  array<string, mixed>  $normalized

     * @param  array<string, mixed>  $visual

     */

    protected function shouldUseTimeGrain(array $normalized, array $visual): bool

    {

        $periodGrains = $visual['period_grains'] ?? null;



        if (is_array($periodGrains) && $periodGrains !== []) {

            return true;

        }



        if ((bool) ($visual['period_filter'] ?? false)) {

            return true;

        }



        if (filled($normalized['filters']['grain'] ?? null) && blank($normalized['group_by'] ?? [])) {

            return true;

        }



        return false;

    }



    /**

     * @param  array<string, mixed>  $normalized

     * @param  array<string, mixed>  $visual

     * @return array<string, mixed>

     */

    protected function timeSeriesPayload(string $type, array $normalized, array $visual): array

    {

        $grain = TimeGrainBuckets::resolveGrain(array_replace_recursive($normalized, ['visual' => $visual]));

        [$start, $buckets] = TimeGrainBuckets::buckets($grain);

        $totals = array_fill_keys(array_keys($buckets), 0);

        $table = $this->submissionTable();



        $query = $this->baseQuery($normalized)

            ->where('created_at', '>=', $start);



        QueryFilterApplier::apply($query, $normalized, $table);



        foreach ($query->get(['created_at']) as $row) {

            $key = TimeGrainBuckets::bucketKey($grain, Carbon::parse($row->created_at));



            if (array_key_exists($key, $totals)) {

                $totals[$key]++;

            }

        }



        $labels = array_values($buckets);

        $values = array_values($totals);



        if ($type === 'stat_card') {

            return [

                'value' => array_sum($values),

                'label' => 'Submissions',

            ];

        }



        $payload = $this->toChartPayload($type, $labels, $values, 'Submissions');

        $payload['x_title'] = TimeGrainBuckets::periodAxisTitle($grain);



        return $payload;

    }



    /**

     * @param  array<string, mixed>  $row

     * @param  array<string, mixed>  $normalized

     * @return array<string, mixed>

     */

    protected function mapSubmissionRow(array $row, array $normalized): array

    {

        $data = json_decode((string) ($row['data'] ?? '{}'), true);



        if (! is_array($data)) {

            $data = [];

        }



        unset($row['data']);



        $row['form_name'] = $this->formNameForId((int) ($row['form_id'] ?? 0));



        foreach ($this->payloadColumns(['filters' => $normalized['filters']]) as $key => $label) {

            $field = str_starts_with($key, 'data.') ? substr($key, 5) : $key;

            $value = $data[$field] ?? null;



            if (is_array($value)) {

                $value = implode(', ', array_map(fn ($item) => is_scalar($item) ? (string) $item : json_encode($item), $value));

            }



            $row[$key] = $value;

        }



        return $row;

    }



    protected function formNameForId(int $formId): string

    {

        if ($formId <= 0 || ! class_exists(\Spiggle\FormBuilder\Models\Form::class)) {

            return 'Unknown';

        }



        static $names = null;



        if ($names === null) {

            $names = \Spiggle\FormBuilder\Models\Form::query()

                ->pluck('name', 'id')

                ->map(fn (mixed $name): string => (string) $name)

                ->all();

        }



        return $names[$formId] ?? 'Unknown';

    }



    /**

     * @param  array<string, mixed>  $context

     * @return array<string, string>

     */

    protected function payloadColumns(array $context): array

    {

        $formId = $this->resolveFormId(is_array($context['filters'] ?? null) ? $context['filters'] : []) ?? 0;



        if ($formId <= 0 || ! class_exists(\Spiggle\FormBuilder\Models\Form::class)) {

            return [];

        }



        $form = \Spiggle\FormBuilder\Models\Form::query()->find($formId);



        if (! $form) {

            return [];

        }



        $columns = [];



        foreach ($form->fields() as $field) {

            $name = (string) ($field['name'] ?? '');



            if ($name === '') {

                continue;

            }



            $label = (string) ($field['label_override'] ?? $field['label'] ?? $name);

            $columns['data.'.$name] = $label;

        }



        return $columns;

    }



    protected function submissionTable(): string

    {

        return (string) config('form-builder.tables.submissions', 'form_builder_submissions');

    }

}



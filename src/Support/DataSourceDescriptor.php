<?php

namespace Spiggle\VisualDashboards\Support;

class DataSourceDescriptor
{
    /**
     * @param  array<string, string>  $fields
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $group,
        public readonly string $kind,
        public readonly array $fields = [],
        public readonly array $meta = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'group' => $this->group,
            'kind' => $this->kind,
            'fields' => $this->fields,
            'meta' => $this->meta,
        ];
    }
}

<?php

namespace Spiggle\VisualDashboards\Contracts;

interface DataSource
{
    public function key(): string;

    public function label(): string;

    /**
     * @return array<string, string>
     */
    public function filterSchema(): array;

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, string>
     */
    public function columns(array $context = []): array;

    /**
     * @return array<string, string>
     */
    public function joinTargets(): array;

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, array<string, mixed>>
     */
    public function previewRows(array $config = [], int $limit = 25): array;

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function chartPayload(string $type, array $config = []): array;
}

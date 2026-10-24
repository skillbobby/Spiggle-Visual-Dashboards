<?php

namespace Spiggle\VisualDashboards\Services;

use Spiggle\VisualDashboards\Support\ModelRelationshipIntrospector;

class RelatedSourceDiscoverer
{
    /**
     * Discover Eloquent relationships for a primary data source key.
     *
     * @return array<int, array<string, mixed>>
     */
    public function discoverForSourceKey(string $sourceKey): array
    {
        return $this->normalizeRelations(
            ModelRelationshipIntrospector::relationsForSourceKey($sourceKey)
        );
    }

    /**
     * Discover relationships for a model class.
     *
     * @return array<int, array<string, mixed>>
     */
    public function discoverForModel(string $modelClass): array
    {
        return $this->normalizeRelations(
            ModelRelationshipIntrospector::relationsForModel($modelClass)
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $relations
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeRelations(array $relations): array
    {
        return collect($relations)
            ->map(function (array $relation): array {
                $name = (string) ($relation['name'] ?? '');

                return array_merge($relation, [
                    'key' => $name,
                    'label' => (string) ($relation['related_label'] ?? $name),
                    'relation_type' => (string) ($relation['type'] ?? ''),
                    'alias' => $name,
                ]);
            })
            ->values()
            ->all();
    }
}

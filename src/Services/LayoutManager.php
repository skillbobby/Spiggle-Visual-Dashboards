<?php

namespace Spiggle\VisualDashboards\Services;

use Illuminate\Support\Collection;
use Spiggle\VisualDashboards\Models\CustomDashboard;
use Spiggle\VisualDashboards\Models\Dashlet;
use Spiggle\VisualDashboards\Support\DashletAccess;

class LayoutManager
{
    /**
     * @param  array<int, array<string, int>>  $items
     */
    public function sync(CustomDashboard $dashboard, array $items): void
    {
        $resolved = $this->resolve($items);
        $dashlets = $dashboard->dashlets()->get()->keyBy('id');

        foreach ($resolved as $item) {
            $id = (int) ($item['id'] ?? 0);

            if (! $dashlets->has($id)) {
                continue;
            }

            /** @var Dashlet $dashlet */
            $dashlet = $dashlets->get($id);
            $next = [
                'grid_x' => (int) ($item['x'] ?? $dashlet->grid_x),
                'grid_y' => (int) ($item['y'] ?? $dashlet->grid_y),
                'grid_w' => (int) ($item['w'] ?? $dashlet->grid_w),
                'grid_h' => (int) ($item['h'] ?? $dashlet->grid_h),
            ];

            if (
                $dashlet->grid_x === $next['grid_x']
                && $dashlet->grid_y === $next['grid_y']
                && $dashlet->grid_w === $next['grid_w']
                && $dashlet->grid_h === $next['grid_h']
            ) {
                continue;
            }

            $dashlet->update($next);
        }
    }

    /**
     * @return array{x: int, y: int, w: int, h: int}
     */
    public function nextSlot(CustomDashboard $dashboard, int $width = 4, int $height = 4): array
    {
        $columns = $this->columns();
        $width = max(2, min($columns, $width));
        $height = max(2, $height);

        $occupied = $dashboard->dashlets()
            ->get(['id', 'grid_x', 'grid_y', 'grid_w', 'grid_h'])
            ->map(fn (Dashlet $dashlet): array => [
                'id' => (int) $dashlet->id,
                'x' => (int) $dashlet->grid_x,
                'y' => (int) $dashlet->grid_y,
                'w' => (int) $dashlet->grid_w,
                'h' => (int) $dashlet->grid_h,
            ])
            ->all();

        return $this->firstFreeSlot($occupied, $width, $height, $columns);
    }

    /**
     * @param  array<int, array<string, int|string>>  $items
     * @return array<int, array{id: int, x: int, y: int, w: int, h: int}>
     */
    public function resolve(array $items): array
    {
        $columns = $this->columns();
        $placed = [];

        foreach ($items as $item) {
            $width = max(2, min($columns, (int) ($item['w'] ?? 2)));
            $height = max(2, (int) ($item['h'] ?? 2));
            $candidate = [
                'id' => (int) ($item['id'] ?? 0),
                'x' => max(0, (int) ($item['x'] ?? 0)),
                'y' => max(0, (int) ($item['y'] ?? 0)),
                'w' => $width,
                'h' => $height,
            ];

            if ($candidate['x'] + $width > $columns || $this->overlapsAny($candidate, $placed)) {
                $slot = $this->firstFreeSlot($placed, $width, $height, $columns);
                $candidate['x'] = $slot['x'];
                $candidate['y'] = $slot['y'];
            }

            $placed[] = $candidate;
        }

        return $placed;
    }

    /**
     * @param  array<int, array<string, int>>  $items
     */
    public function hasOverlap(array $items): bool
    {
        foreach ($items as $index => $item) {
            foreach ($items as $otherIndex => $other) {
                if ($index >= $otherIndex) {
                    continue;
                }

                if ($this->collides($item, $other)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, int>  $a
     * @param  array<string, int>  $b
     */
    public function collides(array $a, array $b): bool
    {
        if ((int) ($a['id'] ?? 0) !== 0 && (int) ($a['id'] ?? 0) === (int) ($b['id'] ?? 0)) {
            return false;
        }

        return (int) $a['x'] < (int) $b['x'] + (int) $b['w']
            && (int) $a['x'] + (int) $a['w'] > (int) $b['x']
            && (int) $a['y'] < (int) $b['y'] + (int) $b['h']
            && (int) $a['y'] + (int) $a['h'] > (int) $b['y'];
    }

    /**
     * @param  array<int|string, string>  $grains
     * @return Collection<int, array<string, mixed>>
     */
    public function gridItems(CustomDashboard $dashboard, array $grains = [], bool $enforceDashletAccess = true): Collection
    {
        $registry = app(DashletRegistry::class);
        $user = auth()->user();

        return $dashboard->dashlets()
            ->orderBy('grid_y')
            ->orderBy('grid_x')
            ->get()
            ->filter(fn (Dashlet $dashlet): bool => ! $enforceDashletAccess || DashletAccess::allows($user, $dashlet->normalizedConfig(), $dashboard))
            ->values()
            ->map(function (Dashlet $dashlet) use ($registry, $grains): array {
                $config = $dashlet->normalizedConfig();
                $visual = is_array($config['visual'] ?? null) ? $config['visual'] : [];
                $grain = $grains[$dashlet->id] ?? $grains[(string) $dashlet->id] ?? null;
                $overrides = [];

                if (filled($grain) && $this->periodFilterEnabled($visual)) {
                    $overrides['filters']['grain'] = (string) $grain;
                }

                return [
                    'id' => $dashlet->id,
                    'x' => $dashlet->grid_x,
                    'y' => $dashlet->grid_y,
                    'w' => $dashlet->grid_w,
                    'h' => $dashlet->grid_h,
                    'payload' => $registry->renderPayload($dashlet, $overrides),
                ];
            });
    }

    /**
     * @param  array<int, array<string, int>>  $occupied
     * @return array{x: int, y: int, w: int, h: int}
     */
    public function firstFreeSlot(array $occupied, int $width, int $height, ?int $columns = null): array
    {
        $columns = $columns ?? $this->columns();
        $width = max(2, min($columns, $width));
        $height = max(2, $height);

        for ($y = 0; $y < 250; $y++) {
            for ($x = 0; $x <= $columns - $width; $x++) {
                $candidate = [
                    'id' => -1,
                    'x' => $x,
                    'y' => $y,
                    'w' => $width,
                    'h' => $height,
                ];

                if (! $this->overlapsAny($candidate, $occupied)) {
                    return $candidate;
                }
            }
        }

        return ['x' => 0, 'y' => 0, 'w' => $width, 'h' => $height];
    }

    /**
     * @param  array<string, int>  $candidate
     * @param  array<int, array<string, int>>  $occupied
     */
    protected function overlapsAny(array $candidate, array $occupied): bool
    {
        foreach ($occupied as $item) {
            if ($this->collides($candidate, $item)) {
                return true;
            }
        }

        return false;
    }

    protected function columns(): int
    {
        return (int) config('visual-dashboards.grid.columns', 12);
    }

    /**
     * @param  array<string, mixed>  $visual
     */
    protected function periodFilterEnabled(array $visual): bool
    {
        $configured = $visual['period_grains'] ?? null;

        if (is_array($configured) && $configured !== []) {
            return true;
        }

        return (bool) ($visual['period_filter'] ?? false);
    }
}

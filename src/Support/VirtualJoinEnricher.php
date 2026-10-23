<?php

namespace Spiggle\VisualDashboards\Support;

use Illuminate\Support\Facades\DB;

class VirtualJoinEnricher
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $join
     * @return array<int, array<string, mixed>>
     */
    public static function enrich(array $rows, array $join): array
    {
        $target = (string) ($join['target'] ?? '');

        if ($target === '' || $rows === []) {
            return $rows;
        }

        return match ($target) {
            'users' => self::enrichUsers($rows, $join),
            default => $rows,
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $join
     * @return array<int, array<string, mixed>>
     */
    protected static function enrichUsers(array $rows, array $join): array
    {
        $localKey = (string) ($join['local_key'] ?? 'user_id');
        $foreignKey = (string) ($join['foreign_key'] ?? 'id');
        $model = config('visual-dashboards.user_model', 'App\\Models\\User');

        if (! class_exists($model)) {
            return $rows;
        }

        $userTable = (new $model)->getTable();
        $ids = collect($rows)
            ->pluck($localKey)
            ->filter(fn ($id): bool => filled($id))
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return $rows;
        }

        $users = DB::table($userTable)
            ->whereIn($foreignKey, $ids)
            ->get()
            ->keyBy($foreignKey);

        return array_map(function (array $row) use ($users, $localKey, $foreignKey): array {
            $userId = $row[$localKey] ?? null;
            $user = $userId !== null ? $users->get($userId) : null;

            if ($user) {
                $row['user_name'] = $user->name ?? null;
                $row['user_email'] = $user->email ?? null;
            }

            return $row;
        }, $rows);
    }
}

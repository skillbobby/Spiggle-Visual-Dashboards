<?php

namespace Spiggle\VisualDashboards\Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Spiggle\DynamicFields\Models\CustomField;
use Spiggle\VisualDashboards\Models\CustomDashboard;
use Spiggle\VisualDashboards\Services\DetectedDataDashboardBuilder;

/**
 * Builds a dashboard from whichever Eloquent models currently have records.
 */
class AiSpendOverviewDashboardSeeder
{
    public const DASHBOARD_NAME = DetectedDataDashboardBuilder::DASHBOARD_NAME;

    public function run(Model $user): CustomDashboard
    {
        $this->ensureOverviewUserName($user);

        return app(DetectedDataDashboardBuilder::class)->sync($user);
    }

    protected function ensureOverviewUserName(Model $user): void
    {
        if (
            method_exists($user, 'setCustomFieldValue')
            && class_exists(CustomField::class)
            && CustomField::query()
                ->where('target_model', $user::class)
                ->where('name', 'first_name')
                ->exists()
        ) {
            $current = method_exists($user, 'getCustomFieldValue')
                ? trim((string) $user->getCustomFieldValue('first_name'))
                : '';

            if ($current === '') {
                $user->setCustomFieldValue('first_name', 'Ada');
            }

            return;
        }

        $custom = is_array($user->custom_fields ?? null) ? $user->custom_fields : [];

        if (trim((string) ($custom['first_name'] ?? '')) === '' && trim((string) ($user->name ?? '')) === '') {
            $user->forceFill(['name' => 'Ada Lovelace'])->save();
        }
    }
}

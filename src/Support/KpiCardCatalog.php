<?php

namespace Spiggle\VisualDashboards\Support;

class KpiCardCatalog
{
    /**
     * @return array<string, string>
     */
    public static function iconOptions(): array
    {
        return [
            '' => 'None',
            'wallet' => 'Wallet',
            'chart' => 'Chart',
            'users' => 'Users',
            'inbox' => 'Inbox',
            'document' => 'Document',
            'bolt' => 'Bolt',
            'currency' => 'Currency',
            'calendar' => 'Calendar',
            'check' => 'Check circle',
        ];
    }

    public static function iconSvg(string $key): string
    {
        $svg = match ($key) {
            'wallet' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 9m18 0V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v3"/></svg>',
            'chart' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>',
            'users' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>',
            'inbox' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 012.012 1.244l.256.512a2.25 2.25 0 002.013 1.244h3.218a2.25 2.25 0 002.013-1.244l.256-.512a2.25 2.25 0 012.012-1.244H21.75M2.25 13.5V6.75A2.25 2.25 0 014.5 4.5h15a2.25 2.25 0 012.25 2.25v6.75M2.25 13.5v6.75A2.25 2.25 0 004.5 22.5h15a2.25 2.25 0 002.25-2.25v-6.75"/></svg>',
            'document' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>',
            'bolt' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>',
            'currency' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
            'calendar' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>',
            'check' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
            default => '',
        };

        if ($svg === '') {
            return '';
        }

        return str_replace('<svg ', '<svg class="dashlet-kpi-icon-svg" width="18" height="18" ', $svg);
    }

    /**
     * @param  array<string, mixed>  $visual
     * @return array<string, mixed>
     */
    public static function normalizeSettings(array $visual): array
    {
        $kpi = is_array($visual['kpi'] ?? null) ? $visual['kpi'] : [];
        $comparison = is_array($kpi['comparison'] ?? null) ? $kpi['comparison'] : [];

        return [
            'icon' => (string) ($kpi['icon'] ?? ''),
            'metric_label' => (string) ($kpi['metric_label'] ?? ''),
            'period' => KpiPeriod::normalize((string) ($kpi['period'] ?? 'monthly')),
            'custom_days' => max(1, (int) ($kpi['custom_days'] ?? 30)),
            'value_size' => in_array($kpi['value_size'] ?? '', ['sm', 'md', 'lg', 'xl'], true)
                ? (string) $kpi['value_size']
                : 'md',
            'value_weight' => in_array($kpi['value_weight'] ?? '', ['normal', 'medium', 'semibold', 'bold'], true)
                ? (string) $kpi['value_weight']
                : 'bold',
            'comparison' => [
                'enabled' => (bool) ($comparison['enabled'] ?? false),
                'mode' => in_array($comparison['mode'] ?? '', ['manual', 'previous_period'], true)
                    ? (string) $comparison['mode']
                    : 'manual',
                'delta' => isset($comparison['delta']) && $comparison['delta'] !== ''
                    ? (float) $comparison['delta']
                    : null,
                'up' => (bool) ($comparison['up'] ?? true),
                'higher_is_better' => (bool) ($comparison['higher_is_better'] ?? true),
                'label' => (string) ($comparison['label'] ?? 'than last period'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>|null  $computed
     * @return array<string, mixed>|null
     */
    public static function comparisonPresentation(array $settings, ?array $computed = null): ?array
    {
        $comparison = $settings['comparison'] ?? [];

        if (! ($comparison['enabled'] ?? false)) {
            return null;
        }

        $mode = (string) ($comparison['mode'] ?? 'manual');
        $higherIsBetter = (bool) ($comparison['higher_is_better'] ?? true);
        $period = KpiPeriod::normalize((string) ($settings['period'] ?? 'monthly'));
        $label = trim((string) ($comparison['label'] ?? ''));

        if ($label === '' && $mode === 'previous_period') {
            $label = KpiPeriod::comparisonLabel($period);
        }

        if ($mode === 'previous_period') {
            if (! is_array($computed)) {
                return null;
            }

            $delta = (float) ($computed['delta'] ?? 0);
            $up = (bool) ($computed['up'] ?? false);
        } else {
            $delta = abs((float) ($comparison['delta'] ?? 0));
            $up = (bool) ($comparison['up'] ?? true);
        }

        if ($delta <= 0 && $mode !== 'previous_period') {
            return null;
        }

        $favorable = $higherIsBetter ? $up : ! $up;
        $arrow = $up ? '↑' : '↓';
        $percent = rtrim(rtrim(number_format($delta, 1), '0'), '.').'%';

        return [
            'arrow' => $arrow,
            'percent' => $percent,
            'label' => $label,
            'up' => $up,
            'favorable' => $favorable,
            'delta' => $arrow.$percent,
            'hint' => $label !== '' ? $label : null,
        ];
    }
}

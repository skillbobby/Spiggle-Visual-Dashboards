# Spiggle Visual Dashboards

| | |
|---|---|
| **Package** | `spiggle/visual-dashboards` v1.0.0 |

Private Laravel/Filament package for building Power BI-style dashboards on a live canvas.

## Install

```bash
composer require spiggle/visual-dashboards
php artisan migrate
```

Register the Filament plugin:

```php
use Spiggle\VisualDashboards\Filament\VisualDashboardsPlugin;

$panel->plugin(VisualDashboardsPlugin::make());
```

Run migrations:

```bash
php artisan migrate
```

## Features

- **Visual Dashboards canvas** — drag, resize, auto-save layout
- **Chart gallery** — KPI card, table, line, bar, column, pie, donut, stacked, funnel, radial gauge
- **Chart builder** — type-specific field labels, live preview, data-driven dropdowns, and a wide side panel
- **Public link** — optional anonymous access at `/visual-dashboards/{id}/{slug}` (off by default; signed-in allowed viewers can always use the share URL)
- **Viewer roles** — optional Spatie/Shield role allowlist for signed-in viewing (in-app and share URL)
- **Auto-discovery registry** — Eloquent models, Filament resources, forms, tables, registered metrics
- **All chart types included** — no licensing split
- **One sidebar item** — Overview, dashboards, and AI screens open only after Visual Dashboards is selected
- **Works without Shield** — Spatie or Filament Shield permissions apply when those packages are installed

Check the install with:

```bash
php artisan visual-dashboards:verify
```

## Architecture

See [docs/VISUAL_DASHBOARDS.md](docs/VISUAL_DASHBOARDS.md).

## License

Proprietary. All rights reserved. Not for public distribution.

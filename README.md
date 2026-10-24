![Visual Dashboards](art/banner.jpg)

# Spiggle Visual Dashboards

| | |
|---|---|
| **Package** | `spiggle/visual-dashboards` v1.1.0 |

Filament plugin for building a live dashboard on a canvas and publishing it as a page.

## Install

```bash
composer require spiggle/visual-dashboards
php artisan migrate
```

Register the plugin on the panel:

```php
use Spiggle\VisualDashboards\Filament\VisualDashboardsPlugin;

$panel->plugin(VisualDashboardsPlugin::make());
```

Check the install:

```bash
php artisan visual-dashboards:verify
```

## Build a dashboard

1. Open **Visual Dashboards** and create a new dashboard. Name it, then open the canvas.
2. Choose **Insert visual** and pick a chart. Bars work well when you want to compare categories.
3. Set the source category to **Models** and choose the model that holds the records. For the bars, pick the category field. For the length, pick **Row count**, or another measure. Save the chart.
4. Insert a **KPI card** the same way when the page needs a headline number.
5. Drag a card by its header. It snaps into the nearest gap. Drop it on another card to place it in front or behind.
6. Open **Dashboard settings**, turn on **Public link**, and save.
7. Open the share URL. Signed-in allowed viewers can always use it. With the public link on, anyone with the URL can view the page.

![Sales Overview, with live admissions, tasks, and department charts](art/dashboard.jpg)

## Features

- **Visual Dashboards canvas** — drag, resize, snap into gaps, and stack cards in front or behind
- **Chart gallery** — KPI card, table, line, bar, column, pie, donut, stacked, funnel, radial gauge
- **Live models** — chart fields come from Eloquent models that already have records, including related names
- **Public link** — optional anonymous access at `/visual-dashboards/{id}/{slug}`
- **Viewer roles** — optional Spatie or Shield allowlist for signed-in viewing, including per dashlet
- **One sidebar item** — Visual Dashboards opens the dashboard list
- **Works without Shield** — Spatie or Filament Shield permissions apply when those packages are installed

## Architecture

See [docs/VISUAL_DASHBOARDS.md](docs/VISUAL_DASHBOARDS.md).

## License

Proprietary. All rights reserved. Not for public distribution.

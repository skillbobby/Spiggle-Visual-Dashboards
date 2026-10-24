# Visual Dashboards Architecture

Visual Dashboards is a single private package (`spiggle/visual-dashboards`). There is no core/pro split and no licensing layer.

## Flow

The Filament sidebar shows one **Visual Dashboards** item, which opens the dashboard list. AI spend records are application data and are not part of this package's menu.

1. **Create dashboard** — a single name card. Slug and heading are filled from the name. Public link and viewer roles stay on the edit page (public link is off by default)
2. **Design canvas** — redirected to `ViewCustomDashboard` after create
3. **Insert visual** — chart gallery slide-over
4. **Configure** — data bindings with a live preview, then optional style
5. **Layout** — drag, resize, auto-save

## Visibility and access

| Setting | Behavior |
|---------|----------|
| **Public link** (`is_published`) | Off by default. Guests are redirected to login. Signed-in users who can view the dashboard can still open `/visual-dashboards/{id}/{slug}`. When on, anonymous visitors can open the link. Enforced in the HTTP controller and Livewire public view. |
| **Viewer roles** (`viewer_roles`) | Empty = any authenticated user with Custom Dashboard view permission. When set, the user needs at least one listed role (owners and users with Update still have access). Applies to in-app and share-URL access when signed in; does **not** restrict anonymous Public link viewers. |

The former **Default dashboard** (`is_default`) toggle was removed from settings UI: nothing in the app used it as a landing destination, so it was confusing. The column remains for backward compatibility with import/export.

## Key classes

| Class | Role |
|-------|------|
| `VisualDashboardCanvas` | Livewire canvas: gallery, slide-over panel, grid |
| `VisualDashboardDataSourceRegistry` | Runtime discovery of data sources |
| `RelatedSourceDiscoverer` | Eloquent relationship discovery for linked sources |
| `QueryJoinApplier` | Joins related model fields into query rows |
| `VisualTypes` | Chart gallery catalog |
| `FeatureCatalog` | Allowed visual types (all included) |
| `LayoutManager` | Grid placement & persistence |
| `DashletRegistry` / `DataEngine` | Query execution |

## Data source auto-discovery

`VisualDashboardDataSourceRegistry` aggregates at runtime:

| Group | Discovery |
|-------|-----------|
| Metrics | Config-registered `DataSource` classes + Form Builder when installed |
| Models | `ModelDiscoverer` or filesystem scan |
| Filament Resources | Panel resource registration |
| Forms | Form Builder `Form` records |
| Tables | Config allowlist + model tables |

New Filament resources and models appear automatically without editing discovery code.

## Linked / related data sources

When a **Models** primary source is selected (`model:App\Models\Order`), relationships are discovered lazily via `RelatedSourceDiscoverer` / `ModelRelationshipIntrospector`:

- `belongsTo` — e.g. Order → Customer via `customer_id`
- `hasMany` / `hasOne` — child collections are aggregated (sum for numeric fields, first value for text)
- `belongsToMany` — pivot-linked models via Eloquent `with()`

Saved dashlet config uses a `query` block:

```json
{
  "query": {
    "primary_source": "model:App\\Models\\Order",
    "joins": [
      {
        "relation": "customer",
        "alias": "customer",
        "fields": ["name", "email"]
      }
    ],
    "selected_fields": ["total", "customer.name"]
  }
}
```

Joined fields appear in the UI as `Customer → Name` and are stored as `customer.name` in config.

## MVP status

### Working now

- Unified private package (no Pro/licensing)
- Canvas with drag/resize/save
- Chart gallery + KPI card
- Properties + Data source slide-over tabs
- Data source registry
- Create → view canvas redirect
- All chart types enabled
- Live preview while editing a visual
- Published dashboards reachable at `/visual-dashboards/{id}/{slug}` when **Public link** is enabled (off by default)

### Follow-up

- Richer data source query builder in panel
- Theme polish in `filament-spiggle-theme`

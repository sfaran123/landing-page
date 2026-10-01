# AuraTech Smart Dashboard

A customizable, business-type-aware dashboard module for the AuraTech POS/ERP (Laravel).
It keeps everything the current dashboard shows (quick-action bar, revenue/profit/debt KPIs,
best sellers by month and year, low stock, revenue chart, cash flow, payment methods, latest
transactions, supplier debt) and adds smart alerts, comparisons, forecasts and a layout editor.

Hebrew / RTL first, light and dark mode, works on phones, **zero external JS libraries**
(charts and the drag-and-drop grid are built in), so it runs on any customer server, even offline.

## What you get

| Page | URL | Who |
|---|---|---|
| Dashboard | `/smart-dashboard` | every logged-in user |
| Layout editor | `/smart-dashboard/editor` | admins edit templates; every user can arrange a personal board |

**Dashboard**
- Period chips (today … year, custom range) with like-for-like comparison (month-to-date vs last month to the same day).
- User and branch filters. Cashier roles can be locked to their own sales (`own_data_role_ids`).
- 32 widgets: KPIs with trend and sparkline, charts, heatmap of busy hours, debt aging, deliveries pipeline, open tables, cheques due, expiring batches and more.
- **Smart insights**: 11 rules that watch the data and give an action button. Examples: "2 products that sell every day are out of stock", "today is 20% below the usual Thursday", month-end forecast, customers owing money for more than 60 days, cheques due this week, stuck orders, rising products, returns rate, low margin.
- Ctrl+K command palette that jumps to any screen of the system (all the existing menu items).
- Loads only the widgets on screen, in one batched request, cached on the server, and refreshes itself automatically.
- Every widget: open the full report, enlarge it, or export it to Excel (CSV).

**Editor**
- Save a layout for a **business type**, a **role** or a **single user**.
  Resolution order: user → role → business type → built-in template.
- Drag to move, drag the corner to resize, widgets push each other out of the way. Undo/redo, keyboard shortcuts (Ctrl+S, Ctrl+Z, Delete, arrows).
- Widget library with search, categories, "recommended for this business" and "already on the board" badges. Widgets that this installation's database can't support are shown as unavailable instead of breaking.
- Inspector for each widget: title, size, and its options (period, metric, number of rows, …).
- **Smart builder**: pick the business type and what the business actually does (stock, expiry dates, credit customers, supplier credit, cheques, deliveries, pre-orders, tables, quotations, several cashiers, returns). It builds a matching layout. **Auto-detect** reads the last months of real data, ticks the activities that are in use, and suggests the business type.
- Settings: business name, installation business type, monthly/daily sales targets, auto-refresh interval, reset all personal layouts.

## Install (inside the AuraTech Laravel project)

```bash
# 1. copy this folder to packages/auratech/smart-dashboard and add to the app's composer.json:
#    "repositories": [{ "type": "path", "url": "packages/auratech/smart-dashboard" }]
composer require auratech/smart-dashboard:@dev

# 2. create the two small tables (sd_layouts, sd_settings)
php artisan migrate

# 3. (optional) publish the config to adjust table/column names, quick actions, roles
php artisan vendor:publish --tag=smart-dashboard-config

# 4. check the mapping against this customer's database and time every widget
php artisan smart-dashboard:doctor
```

Open `/smart-dashboard`. When you're happy, set `'replace_default_dashboard' => true`, which redirects `/dashboard` to the new one.
To show it inside the existing admin layout (sidebar etc.), set `'extends' => 'backend.layout.main'` and `'section' => 'content'` in the config.

### Configuration highlights (`config/smart-dashboard.php`)
- `schema` is the **only** place that knows table and column names. It defaults to the standard AuraTech/SalePro schema. `where` handles installs that keep purchases inside `sales` (`transaction_type = purchase`).
- `quick_actions` and `palette` hold the existing top buttons and menu links. Verify the POS URL.
- `editor_role_ids` sets who can edit templates. `allow_personal_layouts` lets every user arrange his own board. `own_data_role_ids` locks roles to their own sales.
- `business_types` has six built-in types (retail, grocery, restaurant, wholesale, e-commerce, services), each with its own default layout.
- `cache_ttl` is in seconds (default 120).

### Adding a widget
Create a class extending `AuraTech\SmartDashboard\Widgets\Widget` (implement `key()`, `meta()`, `data()`; declare `requires()` so it hides itself when a table is missing), then add it to `widgets` in the config. It appears in the editor library automatically. The UI renders by `type`: `kpi, chart, table, tabs-table, insights, actions, progress, heatmap, aging, pipeline, tiles, compare`.

## Run the demo (no Laravel needed)

```bash
php -S localhost:8080 demo/server.php     # http://localhost:8080  (add ?as=cashier for a non-admin)
php demo/build-static.php                 # one self-contained HTML file in demo/dist/
php tests/run.php                         # 691 checks against a seeded database
```

The demo uses ~13 months of invented data for a nuts & dried-fruit shop (SQLite). No real customer data.

## Layout

```
config/smart-dashboard.php        all installation-specific settings
database/migrations/              sd_layouts, sd_settings
routes/web.php                    pages + JSON API (/smart-dashboard/api/*)
src/DashboardService.php          the whole backend behind one framework-free API
src/Widgets/Providers/*           32 widgets (raw parameterised SQL, MySQL/MariaDB/SQLite/Postgres)
src/Insights/InsightEngine.php    smart alerts
src/Layouts/                      presets per business type, activities + auto-detect, layout store
src/Http/Controllers/             thin Laravel controllers
resources/assets/                 sd.css, sd-core.js, sd-dashboard.js, sd-editor.js (no dependencies)
resources/views/                  Blade wrappers + page.php
demo/  tests/
```

## Security notes
- All SQL is parameterised; table/column names from config are validated as identifiers.
- Layout JSON is sanitised (known widget keys only, clamped sizes, HTML stripped from titles).
- Template editing is limited to `editor_role_ids`; users can only save their own personal layout.
- Optional per-widget permissions via Laravel's `can()` (`enforce_widget_permissions`).

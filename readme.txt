=== Kontentainment Charts ===
Contributors: kollectivco
Tags: music, charts, artists, tracks, albums
Requires at least: 6.0
Tested up to: 6.5
Stable tag: 4.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Premium public-facing music charts experience and control center for WordPress with plugin-based routing, editorial frontend, and plugin-owned admin views.

== Description ==

Kontentainment Charts is a WordPress plugin for chart-led music publishing experiences with public chart pages and an internal admin control center UI.

Features:

* Home page and chart index
* Top Artists, Top Tracks, Top Albums
* Hot 100 Tracks and Hot 100 Artists
* Track and artist detail routes
* GitHub-based update flow for future plugin versions
* Hybrid admin architecture with lightweight wp-admin controls and a full custom dashboard at `/charts-dashboard`

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin through the WordPress plugins screen.
3. Visit Settings > Permalinks once or reactivate the plugin to refresh routes.

== Changelog ==

= 4.0.0 =
* Added a first-time onboarding flow, production setup checklist, guided first live data workflow, source-specific upload guidance, first publish review summary, and improved zero-data public/admin states without reintroducing any demo content.

= 3.4.1 =
* Removed all automatic demo seeding and mock fallback content, added legacy demo cleanup, and switched the plugin to production-style empty states for fresh installs.

= 3.4.0 =
* Added operational QA tools with filtered ingestion diagnostics, duplicate upload protection, dry-run validation mode, export/debug helpers, and reprocessing plus rollback publishing controls.

= 3.3.0 =
* Added real XLSX/XLS support, stronger parser validation, smarter matching confidence levels, row-level validation feedback, improved dropped-out visibility, and more flexible scoring methodology controls.

= 3.2.0 =
* Added the real Phase 3.2 source-to-chart pipeline with upload metadata, source-specific parsing, weighted generation, trend analysis, draft generation, and publishing status flow.

= 3.1.1 =
* Added a visible plugin-row "Check for updates" link and a forced GitHub refresh action inside wp-admin tools.
* Pushed the real Phase 3.1 backend for source uploads, parsing foundations, matching persistence, scoring rules, and ingestion logs.

= 3.1.0 =
* Added real source uploads, parsing foundations, matching queue persistence, scoring rules persistence, and ingestion logs for the Phase 3.1 backend layer.

= 3.0.0 =
* Added real Phase 3 backend tables, CRUD wiring, publishing/archive state flow, persisted settings, and capability-based access across the admin and public chart data layer.

= 2.1.3 =
* Added a shared dark/light theme system for wp-admin pages and the custom dashboard, with dark mode as the default and persistent theme switching.

= 2.1.2 =
* Improved admin and custom dashboard contrast, surface separation, and readability.

= 2.1.1 =
* Corrected GitHub update versioning for the hybrid dashboard release.

= 2.1.0 =
* Added hybrid admin architecture with lightweight wp-admin controls and a full custom dashboard at `/charts-dashboard`.

= 2.0.0 =
* Added Phase 2 plugin-owned admin dashboard UI for Kontentainment Charts.
* Renamed plugin and public brand references to Kontentainment Charts.

= 1.1.0 =
* Editorial frontend polish pass with richer chart summaries, deeper single pages, refined row styling, hover states, and improved mobile behavior.

= 1.0.0 =
* Initial Phase 1 public charts plugin.

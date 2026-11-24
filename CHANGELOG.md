# Changelog

All notable changes to the CheckpointHA plugin.

## [1.3] - 2026-11-24

### Fixed

- Plugin page (and settings page) failed on LibreNMS 26.11.0 with
  `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'snmp_disable' in
  'WHERE'` — `Error calling hook LibreNMS\Interfaces\Plugins\Hooks\SinglePageHook
  for CheckpointHA`, which left `/plugin/CheckpointHA` (and Plugin Admin >
  Settings) empty. LibreNMS 26.11.0 dropped `devices.snmp_disable` (migration
  `2026_09_22_150000_remove_obsolete_device_fields`, part of the
  polling-methods/secrets refactor) and moved the SNMP state into the
  `device_polling_methods` table.
- The device filter now lives in one place, `Support/CheckpointDevices::query()`:
  it keeps `snmp_disable = 0` when the column exists (≤ 26.10.x) and otherwise
  requires an enabled `snmp` row in `device_polling_methods` (≥ 26.11.0). Both
  `Page.php` and `Settings.php` use it, so the plugin works on 26.10.2 and
  26.11.0 without changes.

## [1.2] - 2026-08-11

### Changed

- Column filters replaced with the DataTables "Dropdown: Search only" pattern:
  each column header now has a right-floated search-icon button
  (`.cp-header-search`) that opens a dropdown containing a text input, instead
  of the always-visible `<select>` dropdowns. Typing performs a case-insensitive
  substring `column.search()` (previously an exact `^value$` match) on
  `keyup/change/clear`, and active searches highlight the button via
  `.cp-search-active`.
- The search dropdowns are integrated into the single header row: the separate
  second `<thead>` row was removed and `orderCellsTop` is no longer used.
- The search button click calls `e.stopPropagation()` so it doesn't bubble to
  the sortable `th` and sort the column; the menu is toggled via
  `$.fn.dropdown('toggle')` with a manual `.open` class fallback, and outside
  clicks close any open menu.
- "Clear Filters" resets the new search inputs and their active-state
  highlights (in addition to the global search).

## [1.1] - 2026-08-06

### Added

- Settings page (Overview > Plugins > Plugin Admin > Settings) listing every
  Check Point device with a checkbox, saved into the core `plugins` table
  `settings` JSON column via the standard `plugin.update` route.
- The Cluster IPs page is now filtered by the device selection: only checked
  devices are walked (strict), and the page shows a "no devices selected"
  hint when the selection is empty.
- New `Settings.php` hook (SettingsHook) with `resources/views/settings.blade.php`
  (checkbox table with select-all toggle); selection is intersected with the
  current Check Point device list on both sides so stale device IDs are
  harmless.
- New "Hostname" column as the first column of the cluster IP table,
  showing the device hostname (`Device::hostname`) on every row.
- The page now renders a single unified DataTables table (replacing the
  per-device panels): all devices are flattened into one table with a
  global row counter.
- Per-column filter dropdowns below the table header (exact match per
  column).
- New "Clear Filters" button that resets all column dropdown filters and
  the global search.
- DataTables styled with the Bootstrap 3 integration (the same Bootstrap
  3.4.1 that LibreNMS 26.6.1 itself ships, so the table matches the default
  LibreNMS look).
- Export of the whole table (respecting active filters) as CSV and Print,
  via the DataTables Buttons extension.
- Devices whose SNMP table could not be read are listed as warning alerts
  above the table instead of inline sections.
- DataTables assets are vendored locally under `js/` and `css/` and inlined
  into the page server-side (LibreNMS 26.6.1 has no plugin static-file
  route), so no CDN access is needed at runtime.

### Fixed

- Plugin got auto-disabled with `array_map(): Argument #2 ($array) must be of
  type array, Illuminate\Database\Eloquent\Collection given` when opening the
  settings page: LibreNMS 26.6.1's `SettingsHook::handle()` calls `data()`
  twice (once with the stored settings, then again with that result as the
  `$settings` argument). `Settings::data()` is now idempotent and returns the
  already-built view data on the second call.

### Changed

- The first column of the cluster IP table (header "Hostname") now shows the
  device's LibreNMS `sysName` (header renamed to "sysName"), falling back to
  `hostname` when `sysName` hasn't been polled yet.
- The device list on the settings page shows `sysName` the same way (still
  falling back to `hostname` when `sysName` hasn't been polled yet).
- Export buttons (CSV/Print) moved after the search box.
- Search box moved to the far left of the table toolbar.
- DataTables integration switched to the Bootstrap 3 files
  (`dataTables.bootstrap.*`, `buttons.bootstrap.*`, matching the vendored
  DataTables 1.13.8 / Buttons 2.4.2 versions). LibreNMS 26.6.1 actually
  ships Bootstrap 3.4.1 CSS (not Bootstrap 4/5 — there is no
  `.btn-secondary`/`.page-item`/`.form-select` in its stylesheets), so the
  Bootstrap 4/5 integration files produced unstyled export buttons and
  non-native form controls; the Bootstrap 3 files emit `btn btn-default
  btn-sm`, `pagination pagination-sm` and `form-control` classes that
  LibreNMS styles natively, including dark-mode overrides.

## [1.0] - 2026-08-06

### Added

- New "Interface Alias" column in the cluster IP table, showing the
  standard IF-MIB `ifAlias` (ifXTable `.1.3.6.1.2.1.31.1.1.1.18`) of the
  matched interface, placed between the Interface and Interface IP columns.

  Implementation notes:
  - `ClusterIpTable` walks the ifXTable `ifName` (column 1) and `ifAlias`
    (column 18) subtrees (`.1.3.6.1.2.1.31.1.1.1.{1,18}`).
  - Rows are joined to ifAlias by `ifName` (matching `fwNetIfName`).
  - The ifX walk is optional decoration: failures are silently ignored and
    do not affect the existing error handling.
  - Views (page.blade.php) needed no changes; the table iterates the
    `ClusterIpTable::COLUMNS` map.

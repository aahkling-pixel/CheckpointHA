# CheckpointHA

A LibreNMS plugin that shows one global page listing the **Check Point cluster IPs** (High Availability `haClusterIpTable`) of every enabled Check Point device — so you don't have to open each device's overview page.

## Features

- Single table with all cluster IP rows from all selected Check Point devices.
- Columns: sysName, Interface, Interface Alias (`ifAlias`), Interface IP, Cluster IP, Netmask and Member Network (as CIDR).
- Per-column search (dropdown in each column header), global search, sorting and pagination via DataTables.
- Export the whole filtered table as CSV or print it.
- Device selection on a settings page; only checked devices are walked.
- Devices whose SNMP tables could not be read are reported as warning alerts above the table.
- No external CDN access needed: DataTables assets are vendored locally and inlined into the page.
- Dark-mode friendly (uses the same Bootstrap 3.4.1 classes LibreNMS ships).

## Requirements

- LibreNMS 26.6.1 or compatible (plugin hooks: `MenuEntryHook`, `PageHook`, `SettingsHook`).
- At least one Check Point device already discovered by LibreNMS:
  - not disabled and not SNMP-disabled,
  - `sysObjectID` under the Check Point enterprise tree (`.1.3.6.1.4.1.2620`),
  - reachable via SNMP (the plugin walks the device live when you open the page).
- The user needs the `global-read` permission to see the menu entry.

The plugin reads these MIB objects:

| Table | OID | Used for |
|---|---|---|
| `fwNetIfTable` | `.1.3.6.1.4.1.2620.1.1.27` | interface names and interface IPs |
| `haClusterIpTable` | `.1.3.6.1.4.1.2620.1.5.15` | cluster IP, netmask, member network |
| `ifXTable` (IF-MIB) | `.1.3.6.1.2.1.31.1.1.1.{1,18}` | ifName / ifAlias decoration (optional) |

Cluster rows are joined to the interface whose IP lies inside the member network.

## Installation

Copy this folder into your LibreNMS install:

```bash
cp -r CheckpointHA /opt/librenms/app/Plugins/CheckpointHA
```

Then enable it in the UI under **Overview > Plugins > Plugin Admin** (toggle *Enabled*). A new entry **Check Point Cluster IPs** appears under the **Plugins** menu.

> Note: only `Menu.php`, `Page.php` and `Settings.php` may exist at the top level of the plugin folder (LibreNMS treats every top-level PHP file there as a hook). Helper classes live in `Support/`.

## Usage

### 1. Select devices

Open **Overview > Plugins > Plugin Admin > Settings** next to CheckpointHA (requires the `plugin.admin` permission). You get a checkbox list of every discovered Check Point device:

- Tick the devices whose cluster IPs should be listed on the page (or use the select-all checkbox at the top).
- Press **Save**.

Only checked devices are ever shown on the Cluster IPs page — the selection is strict. Stale entries (devices that were removed or are no longer Check Point devices) are ignored automatically.

If no device is selected, the page shows a "no devices selected" hint instead.

### 2. View the cluster IPs

Open **Plugins > Check Point Cluster IPs** (or browse directly to `/plugin/CheckpointHA`). All rows of all selected devices are flattened into one table:

- Click the magnifier icon in a column header to open that column's search box; typing filters case-insensitively by substring. Active searches highlight the icon.
- Use the global search box (top left) to filter across all columns.
- **Export CSV** downloads the whole filtered table; **Print** opens a printable view.
- **Clear Filters** resets the global search and all column searches.
- Sorting and page length work as usual (10/25/50/All rows per page).

Devices that could not be polled show a warning alert above the table with the SNMP error message. An empty cluster table is not an error — the device simply has no cluster IPs.

### 3. Notes

- The page performs serial SNMP walks per device, so it can be slow with many devices. A loading overlay is shown while the page loads.
- The first column shows the device's `sysName`, falling back to its hostname when `sysName` hasn't been polled yet.
- The menu entry is visible to users with the `global-read` permission; the page itself doesn't add extra authorization beyond LibreNMS's login requirement.

## Files

```
CheckpointHA/
├── Menu.php                      # MenuEntryHook: Plugins menu entry (global-read gated)
├── Page.php                      # PageHook: the global Cluster IPs page
├── Settings.php                  # SettingsHook: device selection checkboxes
├── Support/
│   └── ClusterIpTable.php        # SNMP walking/joining logic (not a hook)
├── resources/views/
│   ├── menu.blade.php            # menu link + loading overlay
│   ├── page.blade.php            # DataTables table + inline vendored assets
│   └── settings.blade.php        # settings form (checkbox list)
├── css/                          # vendored DataTables CSS (inlined server-side)
└── js/                           # vendored DataTables JS (inlined server-side)
```

Settings are stored by LibreNMS itself in the `plugins` table (`settings` JSON column) via the core `plugin.update` route — no schema changes.

## License

GPLv3. See the headers in the source files.

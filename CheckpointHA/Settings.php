<?php

/*
 * CheckpointHA Settings hook
 *
 * Settings page (Overview > Plugins > Plugin Admin > Settings)
 * listing every Check Point device with a checkbox, so the
 * Cluster IPs page only shows the devices selected here.
 *
 * Settings are stored by LibreNMS in the plugins table
 * (settings column, JSON) via the core plugin.update route.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @package    LibreNMS
 * @link       http://librenms.org
 * @copyright  2026
 */

namespace App\Plugins\CheckpointHA;

use App\Plugins\CheckpointHA\Support\CheckpointDevices;
use App\Plugins\Hooks\SettingsHook;

class Settings extends SettingsHook
{
    public function data(array $settings = []): array
    {
        // LibreNMS 26.6.1 quirk: SettingsHook::handle() calls data() twice —
        // once with the stored settings, then again with that result as the
        // $settings argument. Make data() idempotent so the second call just
        // returns the already-built view data (else $settings['devices'] is a
        // Collection and array_map() throws, disabling the plugin).
        if (isset($settings['devices']) && $settings['devices'] instanceof \Illuminate\Database\Eloquent\Collection) {
            return $settings;
        }

        $devices = CheckpointDevices::query()
            ->orderBy('hostname')
            ->get(['device_id', 'hostname', 'sysName']);

        // keep only selection entries that still match a Check Point device
        $selected = array_values(array_intersect(
            array_map('intval', $settings['devices'] ?? []),
            $devices->pluck('device_id')->map(fn ($id) => (int) $id)->all()
        ));

        return [
            'devices' => $devices,
            'selected' => $selected,
        ];
    }
}

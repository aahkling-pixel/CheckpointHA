<?php

/*
 * CheckpointHA Page hook
 *
 * Global page (/plugin/CheckpointHA) listing the cluster IP
 * table of every Check Point device, so you don't have to
 * open each device's overview page.
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

use App\Models\Device;
use App\Plugins\CheckpointHA\Support\ClusterIpTable;
use App\Plugins\Hooks\PageHook;

class Page extends PageHook
{
    public function authorize(\Illuminate\Contracts\Auth\Authenticatable $user): bool
    {
        return true;
    }

    public function data(array $settings = []): array
    {
        // only walk the devices selected on the settings page
        $selected = array_map('intval', $settings['devices'] ?? []);

        $devices = Device::query()
            ->where('disabled', 0)
            ->where('snmp_disable', 0)
            ->where('sysObjectID', 'like', '.1.3.6.1.4.1.2620%')
            ->whereIn('device_id', $selected)
            ->orderBy('hostname')
            ->get();

        $rows = [];
        $errors = [];
        $counter = 1;
        foreach ($devices as $device) {
            $data = (new ClusterIpTable)->rows($device);

            foreach ($data['rows'] as $row) {
                $row['index'] = $counter++;
                $rows[] = $row;
            }

            if ($data['error']) {
                $errors[$device->hostname] = $data['error'];
            }
        }

        return [
            'title' => 'Check Point Cluster IPs',
            'device_count' => $devices->count(),
            'columns' => ClusterIpTable::COLUMNS,
            'rows' => $rows,
            'errors' => $errors,
        ];
    }
}

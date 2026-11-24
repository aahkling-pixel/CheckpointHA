<?php

/*
 * Shared base query for the Check Point devices the plugin may walk.
 *
 * Lives in a subdirectory so PluginProvider's
 * app/Plugins/<dir>/<file>.php glob does not treat it as a hook.
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

namespace App\Plugins\CheckpointHA\Support;

use App\Models\Device;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class CheckpointDevices
{
    /**
     * Enabled (not disabled, not SNMP-less) Check Point devices.
     *
     * LibreNMS 26.11.0 dropped devices.snmp_disable (migration
     * 2026_09_22_150000_remove_obsolete_device_fields) and moved the SNMP
     * state to device_polling_methods, so the SNMP filter depends on the
     * schema of the running LibreNMS:
     *  - <= 26.10.x: devices.snmp_disable = 0
     *  - >= 26.11.0: an enabled 'snmp' row in device_polling_methods
     */
    public static function query(): Builder
    {
        $query = Device::query()
            ->where('disabled', 0)
            ->where('sysObjectID', 'like', '.1.3.6.1.4.1.2620%');

        if (Schema::hasColumn('devices', 'snmp_disable')) {
            $query->where('snmp_disable', 0);
        } else {
            $query->whereHas('pollingMethods', function (Builder $method): void {
                $method->where('method_type', 'snmp')->where('enabled', 1);
            });
        }

        return $query;
    }
}

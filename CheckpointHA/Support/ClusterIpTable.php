<?php

/*
 * Shared logic for reading the Check Point haClusterIpTable
 * (.1.3.6.1.4.1.2620.1.5.15) and fwNetIfTable
 * (.1.3.6.1.4.1.2620.1.1.27), joining them into a single
 * per-cluster-IP table, and decorating each row with the
 * standard IF-MIB ifAlias.
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
use SnmpQuery;

class ClusterIpTable
{
    // haClusterIpTable ::= { ha 15 }, ha ::= { products 5 }, products ::= { checkpoint 1 }, checkpoint ::= { enterprises 2620 }
    private const CLUSTER_TABLE_OID = '.1.3.6.1.4.1.2620.1.5.15';

    // haClusterIpEntry ::= { haClusterIpTable 1 } -> <prefix>.<column>.<index>
    private const CLUSTER_ENTRY_PREFIX = '1.3.6.1.4.1.2620.1.5.15.1';

    // fwNetIfTable ::= { fw 27 }, fw ::= { products 1 }
    private const FW_NET_IF_TABLE_OID = '.1.3.6.1.4.1.2620.1.1.27';

    // fwNetIfEntry ::= { fwNetIfTable 1 } -> <prefix>.<column>.<index>
    private const FW_NET_IF_ENTRY_PREFIX = '1.3.6.1.4.1.2620.1.1.27.1';

    // ifXTable ::= { ifMIBObjects 31 }, ifMIBObjects ::= { mib-2 31 } -> <prefix>.<column>.<ifIndex>
    // ifName = column 1, ifAlias = column 18
    private const IFX_ENTRY_PREFIX = '1.3.6.1.2.1.31.1.1.1';

    // row key => display label
    public const COLUMNS = [
        'hostname' => 'sysName',
        'interface' => 'Interface',
        'if_alias' => 'Interface Alias',
        'if_ip' => 'Interface IP',
        'cluster_ip' => 'Cluster IP',
        'netmask' => 'Netmask',
        'member_net' => 'Member Network',
    ];

    /**
     * Fetch the cluster IP rows for a device.
     *
     * @return array{rows: array<int, array<string, string>>, error: ?string}
     */
    public function rows(Device $device): array
    {
        // fwNetIfTable -> candidate interfaces (name + ip, skip unnumbered)
        $interfaces = [];
        $ifResponse = SnmpQuery::device($device)->numeric()->walk(self::FW_NET_IF_TABLE_OID);
        $ifRows = [];
        foreach ($ifResponse->values() as $oid => $value) {
            if (preg_match('/^' . preg_quote(self::FW_NET_IF_ENTRY_PREFIX, '/') . '\.(\d+)\.([0-9.]+)$/', ltrim((string) $oid, '.'), $m)) {
                $ifRows[$m[2]][(int) $m[1]] = $value;
            }
        }
        foreach ($ifRows as $row) {
            if (isset($row[2], $row[3])) {
                $ipLong = ip2long($row[3]);
                if ($row[3] !== '0.0.0.0' && $ipLong !== false) {
                    $interfaces[] = ['name' => $row[2], 'ip' => $row[3], 'ipLong' => $ipLong];
                }
            }
        }

        // ifXTable -> ifName => ifAlias
        $ifAliases = $this->ifAliases($device);

        // haClusterIpTable, joined to the interface on the same network
        $response = SnmpQuery::device($device)->numeric()->walk(self::CLUSTER_TABLE_OID);
        $clusterRows = [];
        foreach ($response->values() as $oid => $value) {
            if (preg_match('/^' . preg_quote(self::CLUSTER_ENTRY_PREFIX, '/') . '\.(\d+)\.([0-9.]+)$/', ltrim((string) $oid, '.'), $m)) {
                $clusterRows[$m[2]][(int) $m[1]] = $value;
            }
        }
        ksort($clusterRows);

        $rows = [];
        $counter = 1;
        foreach ($clusterRows as $row) {
            $if = $this->findInterface($interfaces, $row[5] ?? '', $row[6] ?? '');

            $rows[] = [
                'index' => $counter++,
                'hostname' => $device->sysName ?: $device->hostname,
                'interface' => $if['name'] ?? '',
                'if_alias' => $if === null ? '' : ($ifAliases[$if['name']] ?? ''),
                'if_ip' => $if['ip'] ?? '',
                'cluster_ip' => $row[3] ?? '',
                'netmask' => $row[4] ?? '',
                'member_net' => $this->toCidr($row[5] ?? '', $row[6] ?? ''),
            ];
        }

        // an empty response is a legit empty table, not a failure
        $error = null;
        if (empty($rows)) {
            $msg = $response->getErrorMessage();
            if (! $response->isValid() && $msg && $msg !== 'Empty Output') {
                $error = $msg;
            } else {
                $msg = $ifResponse->getErrorMessage();
                if (! $ifResponse->isValid() && $msg && $msg !== 'Empty Output') {
                    $error = $msg;
                }
            }
        }

        return ['rows' => $rows, 'error' => $error];
    }

    /**
     * Find the first interface whose IP address lies within the given network.
     */
    private function findInterface(array $interfaces, string $memberNet, string $memberMask): ?array
    {
        $netLong = ip2long($memberNet);
        $maskLong = ip2long($memberMask);
        if ($netLong === false || $maskLong === false) {
            return null;
        }

        $net = $netLong & $maskLong;
        foreach ($interfaces as $if) {
            if (($if['ipLong'] & $maskLong) === $net) {
                return $if;
            }
        }

        return null;
    }

    /**
     * Walk ifName (column 1) and ifAlias (column 18) from the standard
     * ifXTable and map ifName => ifAlias. The alias is optional
     * decoration, so failures here are silently ignored.
     *
     * @return array<string, string>
     */
    private function ifAliases(Device $device): array
    {
        $names = [];
        $nameResponse = SnmpQuery::device($device)->numeric()->walk(self::IFX_ENTRY_PREFIX . '.1');
        foreach ($nameResponse->values() as $oid => $value) {
            if (preg_match('/^' . preg_quote(self::IFX_ENTRY_PREFIX, '/') . '\.1\.([0-9]+)$/', ltrim((string) $oid, '.'), $m)) {
                $names[(int) $m[1]] = $value;
            }
        }

        $aliases = [];
        $aliasResponse = SnmpQuery::device($device)->numeric()->walk(self::IFX_ENTRY_PREFIX . '.18');
        foreach ($aliasResponse->values() as $oid => $value) {
            if (preg_match('/^' . preg_quote(self::IFX_ENTRY_PREFIX, '/') . '\.18\.([0-9]+)$/', ltrim((string) $oid, '.'), $m)) {
                $aliases[(int) $m[1]] = $value;
            }
        }

        $ifAliases = [];
        foreach ($names as $index => $name) {
            $ifAliases[$name] = $aliases[$index] ?? '';
        }

        return $ifAliases;
    }

    private function toCidr(string $ip, string $mask): string
    {
        if ($ip === '') {
            return '';
        }

        if ($mask === '') {
            return $ip;
        }

        $long = ip2long($mask);
        if ($long === false) {
            return $ip;
        }

        $prefix = substr_count(decbin($long), '1');

        return $prefix > 0 ? $ip . '/' . $prefix : $ip;
    }
}

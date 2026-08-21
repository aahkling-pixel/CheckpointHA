<?php

/*
 * CheckpointHA Menu hook
 *
 * Adds an entry under the Plugins menu linking to the
 * global Check Point Cluster IPs page.
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

use App\Plugins\Hooks\MenuEntryHook;

class Menu extends MenuEntryHook
{
    public function authorize(\Illuminate\Contracts\Auth\Authenticatable $user): bool
    {
        return $user->can('global-read');
    }

    public function data(): array
    {
        return [
            'url' => route('plugin.page', ['plugin' => 'CheckpointHA']),
        ];
    }
}

<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Upgrade steps.
 *
 * @package    local_vdsnoemail
 * @copyright  2026 onwards VdS Schadenverhütung
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Plugin upgrade hook.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool
 */
function xmldb_local_vdsnoemail_upgrade($oldversion): bool {
    if ($oldversion < 2026110500) {
        // The 1.0.0 install hook never ran on sites that already had the plugin installed,
        // and users created since then were never flagged. Run the full migration now.
        $changed = \local_vdsnoemail\local\flagger::migrate();
        mtrace("local_vdsnoemail: updated the no-email flag of {$changed} user(s).");

        upgrade_plugin_savepoint(true, 2026110500, 'local', 'vdsnoemail');
    }

    return true;
}

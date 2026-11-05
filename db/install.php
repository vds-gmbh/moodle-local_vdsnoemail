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
 * Install hook: create the "no email" profile field and flag existing users.
 *
 * @package    local_vdsnoemail
 * @copyright  2026 onwards VdS Schadenverhütung
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Plugin install hook.
 *
 * Idempotent: safe to run when the field, the data, or the recertify setting already exist.
 *
 * @return bool
 */
function xmldb_local_vdsnoemail_install(): bool {
    $changed = \local_vdsnoemail\local\flagger::migrate();
    mtrace("local_vdsnoemail: updated the no-email flag of {$changed} user(s).");
    return true;
}

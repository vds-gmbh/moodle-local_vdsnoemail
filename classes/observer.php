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

namespace local_vdsnoemail;

use local_vdsnoemail\local\flagger;

/**
 * Event observers keeping the "no email" flag current for new and changed users.
 *
 * @package    local_vdsnoemail
 * @copyright  2026 onwards VdS Schadenverhütung
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * Flag a newly created user if their email follows the placeholder convention.
     *
     * @param \core\event\user_created $event The event.
     * @return void
     */
    public static function user_created(\core\event\user_created $event): void {
        flagger::sync_user((int) $event->objectid);
    }

    /**
     * Re-evaluate the flag after a user's profile was changed.
     *
     * @param \core\event\user_updated $event The event.
     * @return void
     */
    public static function user_updated(\core\event\user_updated $event): void {
        flagger::sync_user((int) $event->objectid);
    }
}

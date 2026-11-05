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

namespace local_vdsnoemail\local;

/**
 * Keeps the "no email" profile field in sync with the VdS random-...@vds.de convention.
 *
 * Users whose email matches the convention are flagged with '1', all other users
 * that carry the flag are reset to '0'. This reproduces the behaviour of the
 * former hardcoded regex in local_recertify, but stores the result in the
 * profile field that local_recertify reads.
 *
 * @package    local_vdsnoemail
 * @copyright  2026 onwards VdS Schadenverhütung
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class flagger {

    /** @var string Shortname of the profile field created by this plugin. */
    const DEFAULT_SHORTNAME = 'noemail';

    /** @var string Pattern identifying generated placeholder addresses. */
    const EMAIL_REGEX = '/^random-[0-9a-z]+@vds\.de$/i';

    /** @var string SQL LIKE pre-filter matching EMAIL_REGEX (superset). */
    const EMAIL_LIKE = 'random-%@vds.de';

    /**
     * Run the full migration: create the field, wire local_recertify, flag all users.
     *
     * Idempotent, safe to call from install and upgrade.
     *
     * @return int Number of user flags that were changed.
     */
    public static function migrate(): int {
        $fieldid = self::ensure_profile_field(self::DEFAULT_SHORTNAME);
        self::wire_recertify(self::DEFAULT_SHORTNAME);
        // Use the field local_recertify actually reads, which may be a different one an admin configured.
        $fieldid = self::get_fieldid() ?? $fieldid;
        return self::sync_all($fieldid);
    }

    /**
     * Whether an email address follows the VdS placeholder convention.
     *
     * @param string|null $email Email address.
     * @return bool
     */
    public static function is_placeholder_email(?string $email): bool {
        return $email !== null && preg_match(self::EMAIL_REGEX, trim($email)) === 1;
    }

    /**
     * Resolve the id of the profile field local_recertify uses as "no email" marker.
     *
     * @return int|null Field id, or null if the setting is empty or the field does not exist.
     */
    public static function get_fieldid(): ?int {
        global $DB;
        $shortname = get_config('local_recertify', 'noemailprofilefield');
        if (empty($shortname)) {
            return null;
        }
        $id = $DB->get_field('user_info_field', 'id', ['shortname' => $shortname]);
        return $id ? (int) $id : null;
    }

    /**
     * Set the flag of a single user according to their current email address.
     *
     * @param int $userid User id.
     * @param int|null $fieldid Profile field id, resolved from the local_recertify setting if null.
     * @return bool True if the stored value was changed.
     */
    public static function sync_user(int $userid, ?int $fieldid = null): bool {
        global $DB;

        $fieldid = $fieldid ?? self::get_fieldid();
        if ($fieldid === null) {
            return false;
        }

        $user = $DB->get_record('user', ['id' => $userid], 'id, email, deleted');
        if (!$user || $user->deleted) {
            return false;
        }

        return self::set_flag($userid, $fieldid, self::is_placeholder_email($user->email));
    }

    /**
     * Bring the flag of every non-deleted user in line with their email address.
     *
     * @param int $fieldid Profile field id.
     * @return int Number of users whose flag was changed.
     */
    public static function sync_all(int $fieldid): int {
        global $DB;

        $likepattern = $DB->sql_like('u.email', ':emailpattern', false);
        $sql = "SELECT u.id, u.email
                  FROM {user} u
             LEFT JOIN {user_info_data} d ON d.userid = u.id AND d.fieldid = :fieldid
                 WHERE u.deleted = 0
                   AND ($likepattern OR d.data = :flagged)";
        $candidates = $DB->get_recordset_sql($sql, [
            'fieldid' => $fieldid,
            'emailpattern' => self::EMAIL_LIKE,
            'flagged' => '1',
        ]);

        $count = 0;
        foreach ($candidates as $candidate) {
            if (self::set_flag((int) $candidate->id, $fieldid, self::is_placeholder_email($candidate->email))) {
                $count++;
            }
        }
        $candidates->close();

        return $count;
    }

    /**
     * Create the checkbox profile field if it does not yet exist.
     *
     * @param string $shortname Field shortname.
     * @return int The existing or newly created field id.
     */
    public static function ensure_profile_field(string $shortname): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/profile/lib.php');

        $existingid = $DB->get_field('user_info_field', 'id', ['shortname' => $shortname]);
        if ($existingid) {
            return (int) $existingid;
        }

        $catname = get_string('profilecategoryname', 'local_vdsnoemail');
        $categoryid = $DB->get_field('user_info_category', 'id', ['name' => $catname]);
        if (!$categoryid) {
            $catsortorder = (int) $DB->get_field_sql(
                "SELECT COALESCE(MAX(sortorder), 0) + 1 FROM {user_info_category}"
            );
            $categoryid = $DB->insert_record('user_info_category', (object) [
                'name' => $catname,
                'sortorder' => $catsortorder,
            ]);
        }

        $fieldsortorder = (int) $DB->get_field_sql(
            "SELECT COALESCE(MAX(sortorder), 0) + 1
               FROM {user_info_field}
              WHERE categoryid = :categoryid",
            ['categoryid' => $categoryid]
        );

        $fieldid = $DB->insert_record('user_info_field', (object) [
            'shortname' => $shortname,
            'name' => get_string('profilefieldname', 'local_vdsnoemail'),
            'datatype' => 'checkbox',
            'description' => '',
            'descriptionformat' => FORMAT_HTML,
            'categoryid' => $categoryid,
            'sortorder' => $fieldsortorder,
            'required' => 0,
            'locked' => 1,
            'visible' => PROFILE_VISIBLE_PRIVATE,
            'forceunique' => 0,
            'signup' => 0,
            'defaultdata' => '0',
            'defaultdataformat' => FORMAT_MOODLE,
            'param1' => '',
            'param2' => '',
            'param3' => '',
            'param4' => '',
            'param5' => '',
        ]);

        return (int) $fieldid;
    }

    /**
     * Point local_recertify at the profile field, unless an admin configured another one.
     *
     * @param string $shortname Field shortname.
     * @return void
     */
    public static function wire_recertify(string $shortname): void {
        $current = get_config('local_recertify', 'noemailprofilefield');
        if (empty($current)) {
            set_config('noemailprofilefield', $shortname, 'local_recertify');
        }
    }

    /**
     * Write the flag value for a user, touching the database only when it changes.
     *
     * A missing record is treated like '0', so unflagged users get no new row.
     *
     * @param int $userid User id.
     * @param int $fieldid Profile field id.
     * @param bool $flag Whether the user has no real email address.
     * @return bool True if the stored value was changed.
     */
    private static function set_flag(int $userid, int $fieldid, bool $flag): bool {
        global $DB;

        $value = $flag ? '1' : '0';
        $record = $DB->get_record('user_info_data', ['userid' => $userid, 'fieldid' => $fieldid]);

        if (!$record) {
            if (!$flag) {
                return false;
            }
            $DB->insert_record('user_info_data', (object) [
                'userid' => $userid,
                'fieldid' => $fieldid,
                'data' => $value,
                'dataformat' => FORMAT_MOODLE,
            ]);
            return true;
        }

        if ((string) $record->data === $value) {
            return false;
        }
        $DB->set_field('user_info_data', 'data', $value, ['id' => $record->id]);
        return true;
    }
}

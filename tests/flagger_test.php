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
 * Tests for the no-email flagger and its event observers.
 *
 * @package    local_vdsnoemail
 * @copyright  2026 onwards VdS Schadenverhütung
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_vdsnoemail\local\flagger
 * @covers     \local_vdsnoemail\observer
 */
final class flagger_test extends \advanced_testcase {

    /**
     * Read the stored flag of a user.
     *
     * @param int $userid User id.
     * @return string|null Stored value, or null if there is no record.
     */
    private function get_flag(int $userid): ?string {
        global $DB;
        $value = $DB->get_field('user_info_data', 'data', [
            'userid' => $userid,
            'fieldid' => flagger::get_fieldid(),
        ]);
        return $value === false ? null : (string) $value;
    }

    /**
     * Placeholder detection follows the former local_recertify regex.
     *
     * @return void
     */
    public function test_is_placeholder_email(): void {
        $this->assertTrue(flagger::is_placeholder_email('random-a1b2c3@vds.de'));
        $this->assertTrue(flagger::is_placeholder_email('RANDOM-ABC@VDS.DE'));
        $this->assertFalse(flagger::is_placeholder_email('max.mustermann@vds.de'));
        $this->assertFalse(flagger::is_placeholder_email('random-abc@vds.de.example.com'));
        $this->assertFalse(flagger::is_placeholder_email('random-a_b@vds.de'));
        $this->assertFalse(flagger::is_placeholder_email(''));
        $this->assertFalse(flagger::is_placeholder_email(null));
    }

    /**
     * The migration creates the field, wires local_recertify and flags existing users.
     *
     * @return void
     */
    public function test_migrate_flags_existing_users(): void {
        global $DB;
        $this->resetAfterTest();

        // Simulate a site where the install hook never ran: no field, no setting.
        $DB->delete_records('user_info_field', ['shortname' => 'noemail']);
        set_config('noemailprofilefield', '', 'local_recertify');

        // Without a field the observer cannot act, so these users stay unflagged until migrate().
        $placeholder = $this->getDataGenerator()->create_user(['email' => 'random-abc123@vds.de']);
        $real = $this->getDataGenerator()->create_user(['email' => 'real@example.com']);

        $changed = flagger::migrate();

        $this->assertEquals('noemail', get_config('local_recertify', 'noemailprofilefield'));
        $this->assertTrue($DB->record_exists('user_info_field', ['shortname' => 'noemail', 'datatype' => 'checkbox']));
        $this->assertSame(1, $changed);
        $this->assertSame('1', $this->get_flag($placeholder->id));
        $this->assertNull($this->get_flag($real->id));

        // A second run changes nothing.
        $this->assertSame(0, flagger::migrate());
    }

    /**
     * The migration does not override a field an admin configured in local_recertify.
     *
     * @return void
     */
    public function test_migrate_keeps_configured_field(): void {
        $this->resetAfterTest();

        $custom = $this->getDataGenerator()->create_custom_profile_field([
            'shortname' => 'customnoemail',
            'name' => 'Custom no email',
            'datatype' => 'checkbox',
        ]);
        set_config('noemailprofilefield', 'customnoemail', 'local_recertify');
        $user = $this->getDataGenerator()->create_user(['email' => 'random-xyz@vds.de']);

        flagger::migrate();

        $this->assertEquals('customnoemail', get_config('local_recertify', 'noemailprofilefield'));
        $this->assertEquals($custom->id, flagger::get_fieldid());
        $this->assertSame('1', $this->get_flag($user->id));
    }

    /**
     * New users are flagged through the user_created observer.
     *
     * @return void
     */
    public function test_observer_flags_new_user(): void {
        $this->resetAfterTest();
        flagger::migrate();

        $placeholder = $this->getDataGenerator()->create_user(['email' => 'random-new1@vds.de']);
        $real = $this->getDataGenerator()->create_user(['email' => 'new@example.com']);

        $this->assertSame('1', $this->get_flag($placeholder->id));
        $this->assertNull($this->get_flag($real->id));
    }

    /**
     * Changing the email address re-evaluates the flag through the user_updated observer.
     *
     * @return void
     */
    public function test_observer_follows_email_change(): void {
        global $CFG;
        require_once($CFG->dirroot . '/user/lib.php');
        $this->resetAfterTest();
        flagger::migrate();

        $user = $this->getDataGenerator()->create_user(['email' => 'random-chg1@vds.de']);
        $this->assertSame('1', $this->get_flag($user->id));

        user_update_user((object) ['id' => $user->id, 'email' => 'now.real@example.com'], false);
        $this->assertSame('0', $this->get_flag($user->id));

        user_update_user((object) ['id' => $user->id, 'email' => 'random-chg2@vds.de'], false);
        $this->assertSame('1', $this->get_flag($user->id));
    }

    /**
     * Without a configured field the observer does nothing.
     *
     * @return void
     */
    public function test_sync_user_without_field_is_noop(): void {
        $this->resetAfterTest();
        set_config('noemailprofilefield', '', 'local_recertify');

        $user = $this->getDataGenerator()->create_user(['email' => 'random-none@vds.de']);

        $this->assertNull(flagger::get_fieldid());
        $this->assertFalse(flagger::sync_user($user->id));
    }
}

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
 * A scheduled task for scripted database integrations.
 *
 * @package    local_cohortcreation
 * @copyright  2016 ROelmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_cohortcreation\task;
use stdClass;

/**
 * A scheduled task for scripted database integrations.
 *
 * @copyright  2016 ROelmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cohortcreation extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('cohortcreation', 'local_cohortcreation');
    }

    /**
     * Run sync.
     */
    public function execute() {

        /* Add USERs to managed site cohorts *
         * ================================= */
        global $DB, $CFG;
        require_once($CFG->dirroot.'/cohort/lib.php');

        /* Source data does not include timecreated - therefore select all users with
         * timecreated < 1 will give new users, if a timecreated stamp is added to the
         * record when processing. This saves processing every user every time.
         */
        $allusers = $DB->get_records_select('user', "`timecreated` < 1");

        // Get the database id for each named cohort.
        $cohorts = array('mng_all_staff', 'mng_all_students', 'mng_all_users');
        foreach ($cohorts as $result) {
            $cohort[$result] = $DB->get_record('cohort', array('idnumber' => $result), '*', MUST_EXIST);
        }

        foreach ($allusers as $user) {
            $record['userid'] = $userupdate['id'] = $user->id;
            $userupdate['timecreated'] = time();
//            print_r($userupdate);
            $DB->update_record('user', $userupdate);

            // Add to all users if not already there.
            $record['cohortid'] = $cohort['mng_all_users']->id;
            if (!$DB->record_exists('cohort_members',
                array('cohortid' => $record['cohortid'], 'userid' => $record['userid']))) {
                $DB->insert_record('cohort_members', $record);
            }
            // Determine if staff or student.
            switch (true) {
                case strstr($user->email, "@glos.ac.uk"):
                    $record['cohortid'] = $cohort['mng_all_staff']->id;
                    break;
                case strstr($user->email, "@connect.glos.ac.uk"):
                    $record['cohortid'] = $cohort['mng_all_students']->id;
                    break;
            }
            /* Just in case user has unusual email, this wont have changed from mng_all_users
             * So do not add to staff or student as we wont know which. */
            if ($record['cohortid'] != $cohort['mng_all_users']->id) {
                // Make sure record doesn't already exist and add.
                if (!$DB->record_exists('cohort_members',
                    array('cohortid' => $record['cohortid'], 'userid' => $record['userid']))) {
                    $DB->insert_record('cohort_members', $record);
                }
            }

        }

    }
}
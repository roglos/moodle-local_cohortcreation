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

        /* Override sqlallusers so that if this has run in the last 48hrs it only picks up users modified in 48hrs.
         * ISSUE: Users are populated externally and do not have timemodified/timecreated set.
         * ========================================================================================================
         * $lastrun = $DB->get_record('task_scheduled',
         *     array('classname' => '\local_cohortcreation\task\cohortcreation'), '*', MUST_EXIST);
         * $sqlallusers = "SELECT * FROM {user}";
         *
         * if ($lastrun['lastruntime'] < time()-172800) {
         *     $sqlallusers = "SELECT * FROM {user} WHERE 'timemodified' > time()-172800";
         * } */
        $allusers = $DB->get_recordset('user');

        // Get the database id for each named cohort.
        $cohorts = array('mng_all_staff', 'mng_all_students', 'mng_all_users');
        foreach ($cohorts as $result) {
            $cohort[$result] = $DB->get_record('cohort', array('idnumber' => $result), '*', MUST_EXIST);
        }

        foreach ($allusers as $user) {
            $record['userid'] = $user->id;
            $record['timeadded'] = time();
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
            /* Just in case user has unusual email, this wont have changed -
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
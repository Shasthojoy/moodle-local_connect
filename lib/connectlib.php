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
 * Functions and classes used for Connect
 *
 * @package    local
 * @subpackage connect
 * @copyright  2013 University of Kent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Returns a list of a user's courses
 */
function connect_get_user_courses($username) {
    global $CONNECTDB;

    // Select all our courses.
    $sql = "SELECT e.login username, e.moodle_id enrolmentid, c.moodle_id courseid, e.role, c.module_title FROM `enrollments` e
                LEFT JOIN `courses` c
                    ON c.module_delivery_key = e.module_delivery_key
            WHERE e.login=:username";

    return $CONNECTDB->get_records_sql($sql, array(
        "username" => $username
    ));
}

/**
 * Returns a list of this user's courses
 */
function connect_get_my_courses() {
    global $USER;
    return connect_get_user_courses($USER->username);
}

/**
 * Moodle-ify an enrolment grabbed from Connect
 */
function connect_translate_enrolment($enrolment) {
    global $DB;

    // Get role.
    $roledata = connect_role_translate($enrolment['role']);
    $shortname = $roledata['shortname'];
    $role = $DB->get_record('role', array('shortname' => $shortname));
    $enrolment['roleid'] = $role->id;

    // Get user.
    $user = $DB->get_record('user', array('username' => $enrolment['username']));
    $enrolment['userid'] = $user->id;

    return $enrolment;
}

/**
 * Check if this enrolment is valid.
 * In order for a course to be valid it must:
 *   - Have a valid course id in Moodle
 */
function connect_filter_enrolment($enrolment) {
    return !empty($enrolment['courseid']) && !empty($enrolment['roleid']) && !empty($enrolment['userid']);
}

/**
 * Translates a Connect role into Moodle role
 */
function connect_role_translate($role) {
    switch ($role) {
        case "convenor":
            return array(
                "shortname" => "convenor",
                "name" => "Convenor",
                "parent_id" => 3
            );
        case "teacher":
            return array(
                "shortname" => "sds_teacher",
                "name" => "Teacher (sds)",
                "parent_id" => 3
            );
        case "student":
            return array(
                "shortname" => "sds_student",
                "name" => "Student (sds)",
                "parent_id" => 5
            );
        default:
          throw new moodle_exception("Unknown role: $role!");
    }
}

/**
 * Check to see if a user is enrolled on a given module in Moodle
 */
function connect_check_enrolment($enrolment) {
    global $DB;

    // Course context.
    $context = context_course::instance($enrolment['courseid'], MUST_EXIST);

    $sql = "SELECT ue.*
              FROM {user_enrolments} ue
              JOIN {enrol} e ON (e.id = ue.enrolid AND e.courseid = :courseid)
              JOIN {user} u ON u.id = ue.userid
              JOIN {role_assignments} ra ON ra.userid = u.id AND contextid = :contextid
             WHERE ue.userid = :userid AND ue.status = :active AND e.status = :enabled AND u.deleted = 0 AND ra.roleid = :roleid";
    $params = array(
        'enabled' => ENROL_INSTANCE_ENABLED,
        'active' => ENROL_USER_ACTIVE,
        'userid' => $enrolment['userid'],
        'courseid' => $enrolment['courseid'],
        'roleid' => $enrolment['roleid'],
        'contextid' => $context->id
    );

    return (!$enrolments = $DB->get_records_sql($sql, $params));
}

/**
 * Send a Connect enrolment to Moodle
 */
function connect_send_enrolment($enrolment) {
    if (!enrol_try_internal_enrol($enrolment['courseid'], $enrolment['userid'], $enrolment['roleid'])) {
        return false;
    }
    return true;
}

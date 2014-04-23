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
 * Local stuff for Moodle Connect
 *
 * @package    local_connect
 * @copyright  2014 Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_connect;

defined('MOODLE_INTERNAL') || die();

/**
 * Connect Sync Scripts
 */
class sync
{
    /**
     * Map a dataset to an id array
     */
    private static function map_set($data) {
        $ids = array();

        foreach ($data as $datum) {
            $ids[] = $datum->id;
        }

        return $ids;
    }

    /**
     * Grabs a list of Connect enrolments, somewhat mapped to Moodle data
     */
    public static function get_connect_enrolments() {
        global $DB;

        $data = $DB->get_records_sql("SELECT ce.id, cu.mid as userid, cc.mid as courseid, cr.mid as roleid
                                        FROM {connect_enrolments} ce
                                        INNER JOIN {connect_user} cu ON cu.id = ce.userid
                                        INNER JOIN {connect_course} cc ON cc.id = ce.courseid
                                        INNER JOIN {connect_role} cr ON cr.id = ce.roleid
                                        WHERE cc.mid != 0 AND ce.deleted = 0");

        return $data;
    }

    /**
     * Grabs a list of Moodle enrolments
     */
    public static function get_moodle_enrolments() {
        global $DB;

        $data = $DB->get_records_sql("SELECT ue.userid, e.courseid, r.id as roleid
                                        FROM {user_enrolments} ue
                                        INNER JOIN {user} u on u.id = ue.userid
                                        INNER JOIN {enrol} e ON e.id = ue.enrolid
                                        INNER JOIN {course} c ON c.id = e.courseid
                                        INNER JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
                                        INNER JOIN {role_assignments} ra ON ra.userid = u.id AND ra.contextid = ctx.id
                                        INNER JOIN {role} r ON r.id = ra.roleid AND r.shortname IN ('sds_student', 'sds_teacher', 'convenor')");

        return $data;
    }

    /**
     * Grab a list of enrolments due to be created.
     * This is not just one SQL statement because custard sucks at temporary tables.
     *
     * @param boolean $role_only Only check if roleid matches 
     */
    private static function compare_enrolments($role_only = false) {
        // Grab a list of Moodle enrolments, then map them up in a
        // data structure we can easily query.
        $data = self::get_moodle_enrolments();
        $moodle = array();
        foreach ($data as $row) {
            if (!isset($moodle[$row->userid])) {
                $moodle[$row->userid] = array();
            }

            $moodle[$row->userid][$row->courseid] = $row->roleid;
        }

        $connect = self::get_connect_enrolments();

        $ids = array();

        foreach ($connect as $enrolment) {
            if (!isset($moodle[$enrolment->userid]) || !isset($moodle[$enrolment->userid][$enrolment->courseid])) {
                if (!$role_only) {
                    $ids[] = $enrolment->id;
                }

                continue;
            }

            if ($role_only && $moodle[$enrolment->userid][$enrolment->courseid] != $enrolment->roleid) {
                $ids[] = $enrolment->id;
            }
        }

        return $ids;
    }

    /**
     * Grab a list of enrolments due to be created.
     */
    public static function get_new_enrolments() {
        return self::compare_enrolments(false);
    }

    /**
     * Grab a list of enrolments that have changed role.
     */
    public static function get_changed_enrolments() {
        return self::compare_enrolments(true);
    }

    /**
     * Grab a list of enrolments due to be deleted (that have not yet been deleted)
     */
    public static function get_deleted_enrolments() {
        global $DB;

        $data = $DB->get_records_sql("SELECT ce.id
                                        FROM {connect_enrolments} ce
                                            INNER JOIN {connect_user} cu ON ce.userid=cu.id
                                            INNER JOIN {connect_course} cc ON ce.courseid=cc.id
                                            INNER JOIN {user_enrolments} ue ON ue.userid=cu.mid
                                            INNER JOIN {enrol} e ON e.id=ue.enrolid AND e.courseid=cc.mid
                                        WHERE ce.deleted=1");

        return self::map_set($data);
    }

    /**
     * Grab a list of enrolments that exist in Moodle in one of the SDS formats,
     * but that dont exist in SDS.
     *
     * I *suppose* there are valid reasons for people doing this, but we dont allow
     * the assignment of these roles so we have to assume Connect has screwed up in
     * the past, which used to happen a lot with the Ruby version.
     */
    public static function get_extra_enrolments() {
    }
}
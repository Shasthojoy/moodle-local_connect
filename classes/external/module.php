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
 * @copyright  2015 Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_connect\external;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");

use external_api;
use external_value;
use external_single_structure;
use external_multiple_structure;
use external_function_parameters;

/**
 * Connect's module external services.
 */
class module extends external_api
{
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function search_parameters() {
        return new external_function_parameters(array(
            'module_code' => new external_value(
                PARAM_RAW,
                'The search string',
                VALUE_DEFAULT,
                ''
            )
        ));
    }

    /**
     * Expose to AJAX
     * @return boolean
     */
    public static function search_is_allowed_from_ajax() {
        return true;
    }

    /**
     * Search a list of modules.
     *
     * @param $modulecode
     * @return array [string]
     * @throws \invalid_parameter_exception
     */
    public static function search($modulecode) {
        global $DB;

        $params = self::validate_parameters(self::search_parameters(), array(
            'module_code' => $modulecode
        ));
        $modulecode = $params['module_code'];

        $like = $DB->sql_like('module_code', ':modulecode');

        return $DB->get_records_select('connect_course', $like, array(
            'modulecode' => "%{$modulecode}%"
        ));
    }

    /**
     * Returns description of search() result value.
     *
     * @return external_description
     */
    public static function search_returns() {
        return new external_multiple_structure(new external_value(PARAM_RAW, 'The module information.'));
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_my_parameters() {
        return new external_function_parameters(array());
    }

    /**
     * Expose to AJAX
     * @return boolean
     */
    public static function get_my_is_allowed_from_ajax() {
        return true;
    }

    /**
     * Search a list of modules.
     *
     * @return array [string]
     * @throws \invalid_parameter_exception
     */
    public static function get_my() {
        global $DB;

        $courses = array();
        if (!has_capability("local/connect:helpdesk", \context_system::instance())) {
            $cats = \local_connect\util\helpers::get_connect_course_categories();
            $courses = \local_connect\course::get_by_category($cats);
        } else {
            $courses = \local_connect\course::get_all();
        }

        // Grab a list of campus IDs.
        $campusids = array();
        $campuses = $DB->get_recordset('connect_campus');
        foreach ($campuses as $campus) {
            $campusids[$campus->id] = $campus->name;
        }
        $campuses->close();

        // Find all merged modules.
        $merged = array();
        foreach ($courses as $course) {
            if (empty($course->mid)) {
                continue;
            }

            if (!isset($merged[$course->mid])) {
                $merged[$course->mid] = array();
            }

            $merged[$course->mid][] = $course;
        }

        $merged = array_filter($merged, function($a) {
            return count($a) > 1;
        });

        // Process everything.
        $mergerefs = array();
        $out = array();
        foreach ($courses as $course) {
            $coursedata = $course->get_data();

            if (isset($campusids[$coursedata->campusid])) {
                $coursedata->campus = $campusids[$coursedata->campusid];
            }

            if (!isset($merged[$course->mid])) {
                $out[] = $coursedata;
                continue;
            }

            if (isset($mergerefs[$course->mid])) {
                $obj = $mergerefs[$course->mid];
                $obj->children[] = $coursedata;
                continue;
            }

            // This is a merged module, create a skeleton.
            $merge = clone($coursedata);
            $merge->module_title = $course->shortname;
            $merge->campus_desc = $course->campus_name;
            $merge->children = array($coursedata);

            $mergerefs[$course->mid] = $merge;

            $out[] = $merge;
        }

        return $out;
    }

    /**
     * Returns description of get_my() result value.
     *
     * @return external_description
     */
    public static function get_my_returns() {
        return new external_multiple_structure(new external_value(PARAM_RAW, 'DA Page List.')); // TODO?
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function push_parameters() {
        return new external_function_parameters(array(
            'id' => new external_value(
                PARAM_INT,
                'The module to push',
                VALUE_DEFAULT,
                ''
            )
        ));
    }

    /**
     * Expose to AJAX
     * @return boolean
     */
    public static function push_is_allowed_from_ajax() {
        return true;
    }

    /**
     * Push a module 
     *
     * @param $id
     * @return bool
     * @throws \invalid_parameter_exception
     */
    public static function push($id) {
        $params = self::validate_parameters(self::push_parameters(), array(
            'id' => $id
        ));

        $course = \local_connect\course::get($params['id']);
        return $course->create_in_moodle();
    }

    /**
     * Returns description of push() result value.
     *
     * @return external_description
     */
    public static function push_returns() {
        return new external_single_structure(new external_value(PARAM_BOOL, 'Success or failue (true/false).'));
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function unlink_parameters() {
        return new external_function_parameters(array(
            'id' => new external_value(
                PARAM_INT,
                'The module to unlink',
                VALUE_DEFAULT,
                ''
            )
        ));
    }

    /**
     * Expose to AJAX
     * @return boolean
     */
    public static function unlink_is_allowed_from_ajax() {
        return true;
    }

    /**
     * Unlink a module 
     *
     * @param $id
     * @return bool
     * @throws \invalid_parameter_exception
     */
    public static function unlink($id) {
        $params = self::validate_parameters(self::unlink_parameters(), array(
            'id' => $id
        ));

        $course = \local_connect\course::get($params['id']);
        return $course->unlink();
    }

    /**
     * Returns description of unlink() result value.
     *
     * @return external_description
     */
    public static function unlink_returns() {
        return new external_single_structure(new external_value(PARAM_BOOL, 'Success or failue (true/false).'));
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function merge_parameters() {
        return new external_function_parameters(array(
            'id' => new external_value(
                PARAM_INT,
                'The module to merge',
                VALUE_DEFAULT,
                ''
            ),
            'moodleid' => new external_value(
                PARAM_INT,
                'The Moodle ID to assign this to.',
                VALUE_DEFAULT,
                ''
            )
        ));
    }

    /**
     * Expose to AJAX
     * @return boolean
     */
    public static function merge_is_allowed_from_ajax() {
        return true;
    }

    /**
     * Merge a module 
     *
     * @param $id
     * @param $moodleid
     * @return bool
     * @throws \invalid_parameter_exception
     */
    public static function merge($id, $moodleid) {
        $params = self::validate_parameters(self::merge_parameters(), array(
            'id' => $id,
            'moodleid' => $moodleid
        ));

        $course = \local_connect\course::get($params['id']);
        return $course->link($params['moodleid']);
    }

    /**
     * Returns description of merge() result value.
     *
     * @return external_description
     */
    public static function merge_returns() {
        return new external_single_structure(new external_value(PARAM_BOOL, 'Success or failue (true/false).'));
    }
}
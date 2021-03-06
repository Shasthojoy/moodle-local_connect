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
 * @copyright  2016 Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_connect;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . "/enrollib.php");
require_once($CFG->libdir . "/accesslib.php");
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/aspirelists/lib.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');

/**
 * Connect courses container.
 */
class course extends data
{
    /** Shortname Extension Cache */
    private $_shortname_extension;

    /** Sibling cache */
    private $_siblings;

    /**
     * The name of our connect table.
     */
    protected static function get_table() {
        return 'connect_course';
    }

    /**
     * A list of valid fields for this data object.
     */
    protected final static function valid_fields() {
        return array(
            "id", "mid", "module_delivery_key", "session_code", "module_version", "credit_level",
            "campusid", "module_week_beginning", "module_length", "week_beginning_date", "module_title",
            "module_code", "module_code_sds", "category", "department", "interface", "deleted"
        );
    }

    /**
     * Returns an array of fields that link to other databasepods.
     * fieldname -> classname
     */
    protected static function linked_fields() {
        return array(
            'campusid' => '\\local_connect\\campus'
        );
    }

    /**
     * A list of immutable fields for this data object.
     */
    protected static function immutable_fields() {
        return array("id", "module_delivery_key", "session_code", "credit_level", "department", "interface", "deleted");
    }

    /**
     * Save to the Connect database
     *
     * @return boolean
     */
    public function save() {
        if (!empty($this->mid) && !empty($this->_shortname_extension)) {
            course_ext::set($this->mid, $this->_shortname_extension);
        }

        return parent::save();
    }

    /**
     * Get our URL.
     */
    public function get_url() {
        return new \moodle_url('/local/connect/browse/course.php', array(
            'id' => $this->id
        ));
    }

    /**
     * Here is the big sync method.
     * @param bool $dry
     * @return bool|int
     * @throws \moodle_exception
     */
    public function sync($dry = false) {
        global $DB;

        // Do we need to re-map our category?
        if (!$dry && $this->category < 1) {
            $this->map_category();
            if (!$dry && $this->category > 0) {
                $this->save();
            }
        }

        // If we are not in Moodle, we have nothing to do!
        if (!$this->is_in_moodle()) {
            return self::STATUS_NONE;
        }

        // Only sync primaries.
        if ($this->is_version_merged()) {
            $primary = $this->get_primary_version();
            if ($primary->id !== $this->id) {
                return self::STATUS_NONE;
            }
        }

        // Have we changed at all?
        if ($this->is_locked() && $this->has_changed()) {
            if (!$dry) {
                $this->update_moodle();
            }

            return self::STATUS_MODIFY;
        }

        return self::STATUS_NONE;
    }

    /**
     * Get my siblings.
     */
    public function get_siblings() {
        if (empty($this->mid)) {
            return array();
        }

        if (!isset($this->_siblings)) {
            $this->_siblings = static::get_by('mid', $this->mid, true);
        }

        return $this->_siblings;
    }

    /**
     * Are we only merged with different versions of the same course?
     */
    public function is_version_merged() {
        global $DB;

        if (!$this->is_in_moodle()) {
            return false;
        }

        // Count the number of distinct module_versions.
        $sql = <<<SQL
        SELECT COUNT(DISTINCT c.module_version) as versions, COUNT(DISTINCT c.module_code) as codes
        FROM {connect_course} c
        WHERE c.mid = :mid
SQL;
        $counts = $DB->get_record_sql($sql, array(
            'mid' => $this->mid
        ));

        return $counts->codes == 1 && $counts->versions > 1;
    }

    /**
     * Which interface are we from?
     * This will currently be 'SDS' or 'SITS'
     */
    protected function _pretty_interface() {
        return $this->interface == \local_connect\core::INTERFACE_SITS ? 'SITS' : 'SDS';
    }

    /**
     * Returns the latest version of a course if we are version merged.
     */
    public function get_primary_version() {
        if (!$this->is_version_merged()) {
            throw new \moodle_exception("get_primary_version called on un-versioned course.");
        }

        $primary = $this;

        $courses = $this->get_siblings();
        foreach ($courses as $course) {
            if ((int)$course->module_version > (int)$primary->module_version) {
                $primary = $course;
            }
        }

        return $primary;
    }

    /**
     * Adds the shortname date if required.
     * @param $val
     * @return string
     */
    private function append_date($val) {
        if (preg_match('/\(\d+\/\d+\)/is', $val) === 0) {
            $val .= " {$this->bracket_period}";
        }

        return $val;
    }

    public function set_shortname_ext($ext) {
        $this->_shortname_extension = $ext;
    }

    /**
     * Returns the shortname extension
     */
    public function _get_shortname_ext() {
        if (!isset($this->_shortname_extension)) {
            $obj = course_ext::get_by('coursemid', $this->mid);
            if ($obj) {
                $this->_shortname_extension = $obj->extension;
            } else {
                $this->_shortname_extension = "";
            }
        }

        return $this->_shortname_extension;
    }

    /**
     * Returns the shortname
     */
    public function _get_shortname() {
        if ($this->interface == \local_connect\core::INTERFACE_SITS) {
            return $this->module_delivery_key;
        }

        // Are we a version-only course?
        if ($this->is_version_merged()) {
            $primary = $this->get_primary_version();
            if ($primary->id !== $this->id) {
                return $primary->shortname;
            }
        }

        // If we are a merged course, we may have more than one module_code.
        $modulecode = $this->module_code;
        if ($this->is_in_moodle() && !$this->is_version_merged()) {
            $courses = $this->get_siblings();
            if (is_array($courses)) {
                $modulecode = array($modulecode);
                foreach ($courses as $course) {
                    $current = $course->module_code;
                    if (!in_array($current, $modulecode)) {
                        $modulecode[] = $current;
                    }
                }

                // Sort and implode.
                sort($modulecode);
                $modulecode = implode('/', $modulecode);
            }
        }

        $ext = trim($this->shortname_ext);
        if (!empty($ext)) {
            return trim($modulecode) . " " . $ext;
        }

        return trim($modulecode);
    }

    /**
     * Returns the fullname
     */
    public function _get_fullname() {
        if ($this->is_version_merged()) {
            $primary = $this->get_primary_version();
            if ($primary->id !== $this->id) {
                return $primary->fullname;
            }
        }

        return $this->append_date($this->module_title);
    }

    /**
     * Returns the addition to the shortname (e.g. (2013/2014))
     */
    public function _get_bracket_period() {
        $lastyear = date('Y', strtotime('1-1-' . $this->session_code . ' -1 year'));
        return "({$lastyear}/{$this->session_code})";
    }

    /**
     * Returns the duration of this course in the format: "i-i"
     */
    public function _get_duration() {
        return $this->module_week_beginning . '-' . ($this->module_week_beginning + $this->module_length);
    }

    /**
     * Get the name of the campus.
     */
    public function _get_campus_name() {
        // If we are a merged course, we may have more than one campus.
        $campus = $this->campus->name;
        if ($this->is_in_moodle()) {
            $courses = $this->get_siblings();
            if (is_array($courses)) {
                $campus = array($campus);
                foreach ($courses as $course) {
                    $current = $course->campus->name;
                    if (!in_array($current, $campus)) {
                        $campus[] = $current;
                    }
                }

                // Sort and implode.
                sort($campus);
                $campus = implode('/', $campus);
            }
        }

        return $campus;
    }

    /**
     * Week beginning date
     */
    public function _get_week_beginning_date() {
        $data = $this->get_data();
        return strtotime($data->week_beginning_date);
    }

    /**
     * Week ending date
     */
    public function _get_week_ending_date() {
        return strtotime('+' . $this->module_length . ' weeks', $this->week_beginning_date);
    }

    /**
     * Get enrollments for this Course
     */
    public function _get_enrolments() {
        return enrolment::get_by("courseid", $this->id, true);
    }

    /**
     * Get group enrollments for this Course
     */
    public function _get_group_enrolments() {
        return group_enrolment::get_for_course($this);
    }

    /**
     * Get groups for this Course
     */
    public function _get_groups() {
        return group::get_by("courseid", $this->id, true);
    }

    /**
     * Get the summary, based on the synopsis
     */
    public function _get_summary() {
        if ($this->is_version_merged()) {
            $primary = $this->get_primary_version();
            if ($primary->id !== $this->id) {
                return $primary->summary;
            }
        }

        $code = $this->module_code;
        if (strpos($code, " ") !== false) {
            $code = substr($code, 0, strpos($code, " "));
        }

        $more = "<a href='http://www.kent.ac.uk/courses/modulecatalogue/modules/{$code}'>more</a>";
        $text = shorten_text(strip_tags($this->synopsis), 250 + strlen($more), true, '...' . $more);

        // If we are a merged course, we may have more than one campus.
        $campus = $this->campus_name;

        $text = '<div class="synopsistext">' . $text . '</div>';
        $text .= "&nbsp;<p style='margin-top:10px' class='module_summary_extra_info'>";
        $text .= $campus . ", ";
        $text .= "week " . $this->duration;
        $text .= "</p>";

        return $text;
    }

    /**
     * Returns a Moodle course object.
     */
    public function _get_course() {
        global $DB;

        if (!$this->is_in_moodle()) {
            return null;
        }

        return $DB->get_record('course', array(
            'id' => $this->mid
        ), '*', \MUST_EXIST);
    }

    /**
     * Returns a basic synopsis.
     */
    public function _get_synopsis() {
        $handbook = $this->get_handbook();
        return $handbook->synopsis;
    }

    /**
     * Returns the SDS module code, even if this is a SITS module.
     * @return boolean
     */
    public function _get_sds_module_code() {
        return !empty($this->module_code_sds) ? $this->module_code_sds : $this->module_code;
    }

    /**
     * Returns the handbook.
     */
    public function get_handbook() {
        if (!isset(static::$internalcache["handbook_{$this->module_code}"])) {
            static::$internalcache["handbook_{$this->module_code}"] = course_handbook::get_by('module_code', $this->module_code);
        }
        return static::$internalcache["handbook_{$this->module_code}"];
    }

    /**
     * Is this course (probably) postgraduate?
     * @return boolean
     */
    public function is_postgrad() {
        return $this->credit_level == 'M' || $this->credit_level == 'D';
    }

    /**
     * Is this course (probably) undergraduate?
     * @return boolean
     */
    public function is_undergrad() {
        return !$this->is_postgrad();
    }

    /**
     * Is this course unique?
     * @return boolean
     */
    public function is_unique() {
        global $DB;
        return $DB->count_records('connect_course', array('module_code' => $this->module_code, 'deleted' => '0')) === 1;
    }

    /**
     * Is this course part of a merged set?
     * @return boolean
     */
    public function is_merged() {
        global $DB;
        return $DB->count_records('connect_course', array('mid' => $this->mid)) > 1;
    }

    /**
     * Is this course locked?
     * If it is still locked, it means we can update it at will.
     */
    public function is_locked() {
        global $DB;

        if (empty($this->mid)) {
            return true;
        }

        $locked = $DB->get_field('connect_course_locks', 'locked', array(
            "mid" => $this->mid
        ));

        return $locked === false || $locked !== '0';
    }

    /**
     * Returns the Moodle URL for this object.
     */
    public function get_moodle_url() {
        if (!$this->is_in_moodle()) {
            return "";
        }

        $url = new \moodle_url("/course/view.php", array("id" => $this->mid));
        return $url->out(false);
    }

    /**
     * Has this course been created in Moodle?
     * @return boolean
     */
    public function is_in_moodle() {
        return !empty($this->mid);
    }

    /**
     * Does this course have a unique shortname?
     * @param $shortname
     * @param bool $strict
     * @return bool
     */
    public function is_unique_shortname($shortname, $strict = false) {
        global $DB;

        // If in strict mode, we check against connect as well.
        if ($strict) {
            $records = $DB->get_records_sql('
                SELECT id
                FROM {connect_course}
                WHERE module_code=:module_code AND deleted=0
                GROUP BY campusid, module_week_beginning, module_length
            ', array(
                'module_code' => $shortname
            ));

            if (count($records) > 1) {
                return false;
            }
        }

        $expected = $this->is_in_moodle() ? 1 : 0;
        return $expected >= $DB->count_records('course', array(
            'shortname' => $shortname
        ));
    }

    /**
     * Does this course have a unique shortname?
     * @param bool $strict
     * @return bool
     */
    public function has_unique_shortname($strict = false) {
        return $this->is_unique_shortname($this->shortname, $strict);
    }

    /**
     * Has this course changed at all?
     * @return boolean
     */
    public function has_changed() {
        global $DB;

        // Cant do this if the course doesnt exist.
        if (!$this->is_in_moodle()) {
            return false;
        }

        // Basically we just need to check: shortname, fullname and summary.
        $course = $DB->get_record('course', array(
            'id' => $this->mid
        ), 'id, shortname, fullname, category, summary');

        return  (
            $course->shortname !== $this->shortname ||
            $course->fullname !== $this->fullname ||
            $course->category !== $this->category ||
            $course->summary !== $this->summary
        );
    }

    /**
     * Returns a valid instance of the connect enrolment plugin for this course.
     */
    public function get_enrol_instance() {
        global $DB;

        // We need a course object for all this.
        $course = $this->course;
        if (!$course) {
            return null;
        }

        // Try to grab the enol instance.
        $instance = $DB->get_record('enrol', array(
            'enrol' => 'connect',
            'courseid' => $this->mid,
            'customint1' => $this->id
        ));

        if ($instance) {
            return $instance;
        }

        // It doesnt exist? Create it.
        $enrol = enrol_get_plugin('connect');
        $id = $enrol->add_instance($course, array(
            'customint1' => $this->id
        ));

        if (!$id) {
            return null;
        }

        // All went well, grab the object.
        return $DB->get_record('enrol', array(
            'id' => $id
        ));
    }

    /**
     * Create this course in Moodle
     * @param bool $fast
     * @return bool
     * @throws \coding_exception
     * @throws \moodle_exception
     * @internal param string $shortnameext (optional)
     */
    public function create_in_moodle($fast = false) {
        global $DB, $USER;

        // Check we aren't already in Moodle.
        if ($this->is_in_moodle()) {
            debugging("Course already exists in Moodle!");
            return false;
        }

        // Check we have a category.
        if (empty($this->category)) {
            $this->map_category();
        }

        // Grab shortname.
        if (empty($this->_get_shortname_ext())) {
            $this->generate_shortname_ext();
        }

        $shortname = $this->shortname;

        // Ensure the shortname is unique.
        if (!$this->is_unique_shortname($shortname)) {
            throw new \moodle_exception("'{$USER->username}' just tried to push course '{$this->id}' to Moodle but the shortname was not unique.");
        }

        // Create the course.
        try {
            $obj = new \stdClass();
            $obj->category = $this->category;
            $obj->shortname = $shortname;
            $obj->fullname = $this->fullname;
            $obj->format = 'standardweeks';
            $obj->summary = \core_text::convert($this->summary, 'utf-8', 'utf-8');
            $obj->visible = 0;

            $course = create_course($obj);
            if (!$course) {
                throw new \moodle_exception("Unknown");
            }

            // Update our reference.
            $this->mid = $course->id;
        } catch (\moodle_exception $e) {
            $msg = $e->getMessage();
            $err = "'{$USER->username}' just tried to push course '{$this->id}' to Moodle but '{$msg}'.";
            \local_connect\util\helpers::error($err);
            return false;
        }

        // Save our new mid.
        $this->save();

        // Add in sections.
        $DB->set_field('course_sections', 'name', "{$shortname}: {$this->module_title}", array (
            'course' => $this->mid,
            'section' => 0
        ));

        // Fire the event.
        $event = \local_connect\event\course_created::create(array(
            'objectid' => $this->id,
            'courseid' => $this->mid,
            'context' => \context_course::instance($this->mid)
        ));
        $event->trigger();

        if ($fast) {
            // Schedule an adhoc task to sync this course.
            $task = new \local_connect\task\course_fast_sync();
            $task->set_custom_data(array(
                'courseid' => $this->id
            ));
            \core\task\manager::queue_adhoc_task($task);
        } else {
            // Sync our enrolments.
            $this->sync_enrolments();

            // Sync our groups.
            $this->sync_groups();
        }

        return true;
    }

    /**
     * Update this course in Moodle
     */
    public function update_moodle() {
        global $DB;

        if (!$this->is_locked()) {
            return false;
        }

        // Ensure the shortname is unique.
        if (!$this->has_unique_shortname()) {
            if (empty($this->_get_shortname_ext())) {
                $this->generate_shortname_ext();
            } else {
                throw new \moodle_exception("Could not update_moodle as new shortname is not unique.");
            }
        }

        // Updates!
        $course = $this->course;
        $course->shortname = $this->shortname;
        $course->fullname = $this->fullname;
        $course->category = $this->category;
        $course->summary = \core_text::convert($this->summary, 'utf-8', 'utf-8');

        // Update this course in Moodle.
        update_course($course);
        $this->_get_course(true);

        return true;
    }

    /**
     * Map this course to a category.
     */
    public function map_category() {
        global $DB;

        $possibilities = array();

        $map = category::get_map_table();
        foreach ($map as $entry) {
            if ($entry['department'] != $this->department) {
                continue;
            }

            $weight = 0;
            if (isset($entry['rule'])) {
                // Do we match the rule?
                if (strpos($this->module_code, $entry['rule']) !== 0) {
                    continue;
                }

                $weight = 1;
            }

            // Find the category.
            $category = $DB->get_field('course_categories', 'id', array(
                'idnumber' => $entry['idnumber']
            ));
            if ($category) {
                $possibilities[$weight] = $category;
            }
        }

        if (empty($possibilities)) {
            $this->category = 1;
            return;
        }

        $this->category = isset($possibilities[1]) ? $possibilities[1] : $possibilities[0];
    }

    /**
     * Link this to a course
     *
     * @param $courseid
     * @param bool $fast
     * @return unknown
     * @throws \moodle_exception
     * @internal param unknown $target
     */
    public function link($courseid, $fast = false) {
        // Add a link.
        $this->mid = $courseid;
        $this->save();

        // Update in Moodle.
        $this->update_moodle();

        if ($fast) {
            // Schedule an adhoc task to sync this course.
            $task = new \local_connect\task\course_fast_sync();
            $task->set_custom_data(array(
                'courseid' => $this->id
            ));
            \core\task\manager::queue_adhoc_task($task);
        } else {
            // Sync our enrolments.
            $this->sync_enrolments();

            // Sync our groups.
            $this->sync_groups();
        }

        return true;
    }

    /**
     * Link a course to this course
     *
     * @param unknown $target
     * @param bool $fast
     * @return unknown
     */
    public function add_child($target, $fast = false) {
        // Reset required.
        $this->_siblings = null;
        $target->_siblings = null;

        // Add a link.
        $target->link($this->mid, $fast);

        return true;
    }

    /**
     * Delete this course.
     *
     * @deprecated 2015082600 Just delete the course instead.
     * @return boolean
     */
    public function delete() {
        debugging("local_connect\\course->delete() is deprecated. Use delete_course instead!");

        delete_course($this->mid, false);

        return true;
    }

    /**
     * Process a course unlink
     */
    public function unlink() {
        $this->delete_enrolments();

        $siblings = $this->get_siblings();

        $this->mid = 0;
        $this->save();

        // We were not alone?
        foreach ($siblings as $sibling) {
            if ($sibling->id != $this->id) {
                $sibling->update_moodle();
                break;
            }
        }

        return true;
    }

    /**
     * Delete this course's enrolments.
     */
    public function delete_enrolments() {
        // Remove our enrolment plugin.
        $instance = $this->get_enrol_instance();
        if ($instance && $instance->status == ENROL_INSTANCE_ENABLED) {
            $enrol = enrol_get_plugin('connect');
            $enrol->delete_instance($instance);
        }
    }

    /**
     * Syncs enrolments for this Course
     */
    public function sync_enrolments() {
        if (!$this->is_in_moodle()) {
            return;
        }

        $courses = self::get_by('mid', $this->mid, true);
        foreach ($courses as $course) {
            $course->get_enrol_instance();
        }

        $enrol = enrol_get_plugin('connect');
        $enrol->sync($this->mid);
    }

    /**
     * Syncs group enrollments for this Course
     * @todo Updates/Deletions
     */
    public function sync_group_enrolments() {
        if (!$this->is_in_moodle()) {
            return;
        }

        foreach ($this->group_enrolments as $enrolment) {
            if (!$enrolment->is_in_moodle()) {
                $enrolment->create_in_moodle();
            }
        }
    }

    /**
     * Syncs groups for this Course
     * @todo Updates/Deletions
     */
    public function sync_groups() {
        if (!$this->is_in_moodle()) {
            return;
        }

        foreach ($this->groups as $group) {
            if (!$group->is_in_moodle()) {
                $group->create_in_moodle();
            }
        }
    }

    /**
     * Returns the number of people enrolled in this course.
     */
    public function count_all() {
        global $DB;

        return $DB->count_records('connect_enrolments', array(
            'courseid' => $this->id
        ));
    }

    /**
     * Returns the number of students enrolled in this course.
     */
    public function count_students() {
        global $DB;

        $role = $DB->get_field('connect_role', 'id', array('name' => 'sds_student'));
        return $DB->count_records('connect_enrolments', array(
            'courseid' => $this->id,
            'roleid' => $role
        ));
    }

    /**
     * Returns the number of staff enrolled in this course.
     */
    public function count_staff() {
        return $this->count_all() - $this->count_students();
    }

    /**
     * To String override
     * @return unknown
     */
    public function __toString() {
        return !empty($this->module_title) ? $this->shortname : $this->id;
    }


    /**
     * Get a Connect Course by Devliery Key and Session Code
     * @param unknown $moduledeliverykey
     * @param $sessioncode
     * @return unknown
     * @internal param unknown $session_code
     */
    public static function get_by_uid($moduledeliverykey, $sessioncode) {
        global $DB;

        $data = $DB->get_record('connect_course', array(
            'module_delivery_key' => $moduledeliverykey,
            'session_code' => $sessioncode
        ), "*");

        if (!$data) {
            return false;
        }

        $course = new course();
        $course->set_data($data);
        return $course;
    }

    /**
     * Returns an array of all courses in Connect.
     * This is a little complicated and is due to be simplified using magic methods and
     * other such things.
     *
     * @param array categories A list of categories we dont want
     * @param boolean raw Should all objects be stdClass?
     * @return array
     */
    public static function get_by_category($categories, $raw = false) {
        global $DB;

        list($sql, $params) = $DB->get_in_or_equal($categories);
        $result = $DB->get_records_sql('SELECT * FROM {connect_course} WHERE category ' . $sql, $params);

        // Decode various elements.
        if (!$raw) {
            foreach ($result as &$datum) {
                $obj = new course();
                $obj->set_data($datum);
                $datum = $obj;
            }
        }

        return $result;
    }

    /**
     * Schedule a group of courses.
     * This is a hangover from the old UI.
     *
     * @deprecated - Use webservice methods instead.
     * @param $courses
     * @return unknown
     * @throws \moodle_exception
     * @internal param unknown $data
     */
    public static function schedule_all($courses) {
        global $DB;

        $response = array();

        foreach ($courses as $course) {
            // Try to find the Connect version of the course.
            $obj = self::get($course->id);
            if (!$obj) {
                $response = array(
                    'error_code' => 'does_not_exist',
                    'id' => $course->id
                );
                continue;
            }

            // Did we specify a shortname extension?
            if (!empty($course->shortnameext)) {
                $obj->set_shortname_ext($course->shortnameext);
            }

            // Make sure we are unique.
            if (!$obj->has_unique_shortname()) {
                $response = array(
                    'error_code' => 'duplicate',
                    'id' => $course->id
                );
                continue;
            }

            // Attempt to create in Moodle.
            if (!$obj->create_in_moodle()) {
                $response = array(
                    'error_code' => 'error',
                    'id' => $course->id
                );
                continue;
            }

            // Update course info.
            $obj = $DB->get_record('course', array(
                'id' => $obj->mid
            ));

            $update = false;

            if (!empty($course->fullname)) {
                $obj->fullname = $course->fullname;
                $update = true;
            }

            if (!empty($course->shortname)) {
                $obj->shortname = $course->shortname;
                $update = true;
            }

            if (!empty($course->synopsis)) {
                $obj->summary = $course->synopsis;
                $update = true;
            }

            if (!empty($course->category)) {
                $obj->category = $course->category;
                $update = true;
            }

            if ($update) {
                update_course($obj);
            }
        }

        return $response;
    }

    /**
     * Merge two courses.
     * Hangover from old UI.
     *
     * @deprecated - Use webservice methods instead.
     * @param $courses
     * @return unknown
     * @internal param unknown $input
     */
    public static function process_merge($courses, $shortnameext = '') {
        $courses = array_map(function($course) {
            return self::get($course);
        }, $courses);

        $primary = $courses[0];
        foreach ($courses as $course) {
            if ($course->is_in_moodle()) {
                $primary = $course;
            }
        }

        // Create the linked course if it doesnt exist.
        if (!$primary->is_in_moodle()) {
            if (!empty($shortnameext)) {
                $primary->set_shortname_ext($shortnameext);
            }

            if (!$primary->create_in_moodle()) {
                \local_connect\util\helpers::error("Could not create linked course: $primary");
            }
        } else {
            $primary->update_moodle();
        }

        // Add children.
        foreach ($courses as $child) {
            if (!$primary->add_child($child)) {
                \local_connect\util\helpers::error("Could not add child '$child' to course: $primary");
            }
        }

        return array();
    }

    /**
     * Get the term from dates.
     *
     * @return string
     */
    public function get_term() {
        if ($this->module_length == 12) {
            if ($this->module_week_beginning >= 24) {
                return "SUM";
            }

            if ($this->module_week_beginning >= 12) {
                return "SPR";
            }

            if ($this->module_week_beginning >= 1) {
                return "AUT";
            }
        }

        if ($this->module_length == 24) {
            if ($this->module_week_beginning >= 24) {
                return "SUM/AUT";
            }

            if ($this->module_week_beginning >= 12) {
                return "SPR/SUM";
            }

            if ($this->module_week_beginning >= 1) {
                return "AUT/SPR";
            }
        }

        if ($this->module_length == 1) {
            return "(week {$this->module_week_beginning})";
        }

        // This is a bit.. special, just give the weeks.
        $start = $this->module_week_beginning;
        $end = (int)$start + (int)$this->module_length;
        return "(week {$start}-{$end})";
    }

    /**
     * Build a shortnameext.
     *
     * @todo Make this private. Move shortname generation local.
     * @param string $campus
     * @return string
     */
    public function generate_shortname_ext() {
        $campus = trim($this->campus->get_shortname());
        if (!empty($campus)) {
            $campus .= ' ';
        }

        // Are we a WSHOP?
        if (strpos($this->module_code, "WSHOP") === 0) {
            return "{$campus}(week {$this->module_week_beginning})";
        }

        $this->set_shortname_ext($campus .  $this->get_term());
    }

    /**
     * Can we auto-provision?
     */
    public function can_auto_provision() {
        return !$this->is_in_moodle() && $this->campus_name != 'Kent, Sussex and Surrey Deanery' && $this->campus_name != 'Canterbury College';
    }
}

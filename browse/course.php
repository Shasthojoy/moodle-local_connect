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
 * Browse data for a course.
 *
 * @package    local_connect
 * @copyright  2014 Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require (dirname(__FILE__) . '/../../../config.php');
require_once($CFG->libdir . '/tablelib.php');

/**
 * Page setup.
 */
$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/connect/browse/course.php');
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('connectbrowse', 'local_connect'));
$PAGE->navbar->add("Connect Browser", new moodle_url('/local/connect/browse/'));
$PAGE->navbar->add("Course View");

/**
 * Check capabilities.
 */
if (!has_capability('moodle/site:config', context_system::instance())) {
    print_error('accessdenied', 'admin');
}

/**
 * Check course.
 */
$id = required_param("id", PARAM_INT);
$course = \local_connect\course::get($id);
if ($course === null) {
	print_error('Invalid Course!');
}

/**
 * And, the actual page.
 */
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('connectbrowse_course', 'local_connect') . $course->module_code);

// The Course Information Table
{
	echo $OUTPUT->heading("Course Information", 2);

	$table = new flexible_table('course-info');
	$table->define_columns(array('variable', 'value'));
	$table->define_headers(array("Variable", "Value"));
	$table->define_baseurl($CFG->wwwroot.'/local/connect/browse/course.php');
	$table->setup();

	$mid = "-";
	if (!empty($course->mid)) {
		$mid = \html_writer::link($course->get_moodle_url(), $course->mid);
	}

	$table->add_data(array("id", $course->id));
	$table->add_data(array("mid", $mid));
	$table->add_data(array("module_delivery_key", $course->module_delivery_key));
	$table->add_data(array("session_code", $course->session_code));
	$table->add_data(array("module_version", $course->module_version));
	$table->add_data(array("campus", $course->campus));
	$table->add_data(array("module_week_beginning", $course->module_week_beginning));
	$table->add_data(array("module_length", $course->module_length));
	$table->add_data(array("week_beginning_date", $course->week_beginning_date));
	$table->add_data(array("module_title", $course->module_title));
	$table->add_data(array("module_code", $course->module_code));
	$table->add_data(array("synopsis", $course->synopsis));
	$table->add_data(array("category", $course->category));

	// Enrolment counts.
	$table->add_data(array("staff_enrolled", $course->count_staff()));
	$table->add_data(array("students_enrolled", $course->count_students()));

	$table->print_html();
}

// The Enrolments Table
{
	echo $OUTPUT->heading("Enrolments", 2);

	$table = new flexible_table('course-enrolments');
	$table->define_columns(array('username', 'role', 'in_moodle'));
	$table->define_headers(array("Username", "Role", "In Moodle?"));
	$table->define_baseurl($CFG->wwwroot.'/local/connect/browse/course.php');
	$table->setup();

	foreach ($course->enrolments as $enrolment) {
		$table->add_data(array($enrolment->user->login, $enrolment->role->name, $enrolment->is_in_moodle() ? "Yes" : "No"));
	}

	$table->print_html();
}

// The Groups Table
{
	echo $OUTPUT->heading("Groups", 2);

	$table = new flexible_table('course-groups');
	$table->define_columns(array('name', 'members', 'in_moodle'));
	$table->define_headers(array("Name", "Number of users", "In Moodle?"));
	$table->define_baseurl($CFG->wwwroot.'/local/connect/browse/course.php');
	$table->setup();

	foreach ($course->groups as $group) {
		$url = new \moodle_url("/local/connect/browse/group.php", array("id" => $group->id));
		$name = \html_writer::link($url->out(true), $group->name);
		$table->add_data(array($name, $group->count_all(), $group->is_in_moodle() ? "Yes" : "No"));
	}

	$table->print_html();
}

echo $OUTPUT->footer();
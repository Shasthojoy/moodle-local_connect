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
 * This synchronises Connect Courses with Moodle Courses
 *
 * @package    local_connect
 * @copyright  2014 Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(dirname(__FILE__) . '/../../../config.php');

$courses = \local_connect\course::get_courses(array(), true);
foreach ($courses as $course) {
	try {

		if (!$course->is_created() && $course->has_unique_shortname()) {
			print "Creating $course...\n";
			$course->create_moodle();
			continue;
		}

		/*if ($course->has_changed()) {
			print "Updating $course...\n";
			$course->update_moodle();
			continue;
		}*/

	} catch (Excepton $e) {
		$msg = $e->getMessage();
		print "Error: $msg\n";
	}
}
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

defined('MOODLE_INTERNAL') || die();

/**
 * Tests new Kent group code
 */
class kent_group_tests extends local_connect\util\connect_testcase
{
	/**
	 * Make sure we can grab a valid list of groups.
	 */
	public function test_groups_list() {
		global $CFG, $DB, $CONNECTDB;

		$this->resetAfterTest();
		$this->connect_cleanup();

		// Create course.
		$course = \local_connect\course::get($this->generate_course());
		$course->create_in_moodle();

		// Test the global count.
		$groups = \local_connect\group::get_all();
		$this->assertEquals(0, count($groups));

		// Create a group.
		$this->generate_group($course->id);

		// Test the global count.
		$groups = \local_connect\group::get_all();
		$this->assertEquals(1, count($groups));

		// Create a group.
		$this->generate_groups(20, $course->id);

		// Test the global count.
		$groups = \local_connect\group::get_all();
		$this->assertEquals(21, count($groups));

		// Create another course.
		$course2 = \local_connect\course::get($this->generate_course());
		$course2->create_in_moodle();

		// Create a group.
		$this->generate_groups(20, $course2->id);

		// Test the course count.
		$groups = \local_connect\group::get_for_course($course);
		$this->assertEquals(21, count($groups));

		// Test the course count.
		$groups = \local_connect\group::get_for_course($course2);
		$this->assertEquals(20, count($groups));

		$this->connect_cleanup();
	}

	/**
	 * Make sure we can create groups in Moodle.
	 */
	public function test_groups_create() {
		global $CFG, $DB, $CONNECTDB;

		$this->resetAfterTest();
		$this->connect_cleanup();

		// First, create a course.
		$course = \local_connect\course::get($this->generate_course());
		$course->create_in_moodle();

		// Create a group.
		$groupid = $this->generate_group($course->id);

		// Get the group.
		$group = \local_connect\group::get($groupid);
		$this->assertEquals($groupid, $group->id);

		// Create it in Moodle.
		$this->assertFalse($group->is_in_moodle());
		$this->assertTrue($group->create_in_moodle());
		$this->assertTrue($group->is_in_moodle());

		$this->connect_cleanup();
	}

	/**
	 * Test user counting for groups.
	 */
	public function test_groups_counts() {
		global $CFG, $DB, $CONNECTDB;

		$this->resetAfterTest();
		$this->connect_cleanup();

		// First, create a course.
		$course = \local_connect\course::get($this->generate_course());
		$course->create_in_moodle();

		// Create a group.
		$group = $this->generate_group($course->id);

		// Set some enrolments.
		$this->generate_group_enrolments(30, $group, 'student');
		$this->generate_group_enrolments(2, $group, 'teacher');

		// Get the group.
		$obj = \local_connect\group::get($group['group_id']);

		$this->assertEquals(30, $obj->count_students());
		$this->assertEquals(2, $obj->count_staff());

		$this->generate_group_enrolments(2, $group, 'teacher');

		$this->assertEquals(30, $obj->count_students());
		$this->assertEquals(4, $obj->count_staff());

		$this->connect_cleanup();
	}

	/**
	 * Make sure we can sync groups in Moodle.
	 */
	public function test_groups_sync() {
		global $CFG, $DB, $CONNECTDB;

		$this->resetAfterTest();
		$this->connect_cleanup();

		// First, create a course.
		$course = \local_connect\course::get($this->generate_course());
		$course->create_in_moodle();

		// Create a group.
		$groupid = $this->generate_group($course->id);

		// Get the group.
		$group = \local_connect\group::get($groupid);
		$this->assertEquals($groupid, $group->id);

		// Sync it.
		$this->assertFalse($group->is_in_moodle());
		$this->assertEquals("Creating group: $group->id", $group->sync());
		$this->assertTrue($group->is_in_moodle());
		$this->assertEquals(null, $group->sync());

		// Check the Moodle name.
		$mgroup = $DB->get_record('groups', array(
            "id" => $group->mid
        ));
        $this->assertEquals($mgroup->name, $group->name);

        // Try changing the group name and synching it.
		$group->name = "TEST CHANGE";
		$mgroup = $DB->get_record('groups', array(
            "id" => $group->mid
        ));
        $this->assertNotEquals($mgroup->name, $group->name);
		$this->assertEquals("Updating group: $group->id", $group->sync());

		// Check the Moodle name again.
		$mgroup = $DB->get_record('groups', array(
            "id" => $group->mid
        ));
        $this->assertEquals($mgroup->name, $group->name);

		$this->connect_cleanup();
	}
}















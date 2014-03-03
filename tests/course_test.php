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
 * Tests new Kent course code
 */
class kent_course_tests extends local_connect\util\connect_testcase
{
    /**
     * Test we can create a course.
     */
    public function test_course_generator() {
        $this->resetAfterTest();
        $this->connect_cleanup();

        $this->generate_courses(20);

        $this->assertEquals(20, count(\local_connect\course::get_courses()));

        $this->connect_cleanup();
    }

    /**
     * Test we can create a linked course.
     */
    public function test_linked_course() {
        $this->resetAfterTest();
        $this->connect_cleanup();

        // Create two courses.
        $this->generate_courses(2);

        $courses = \local_connect\course::get_courses(array(), true);
        $this->assertEquals(2, count($courses));

        $link_course = array(
            'module_code' => "TST",
            'module_title' => "TEST MERGE",
            'primary_child' => reset($courses),
            'synopsis' => "This is a test",
            'category_id' => 1,
            'state' => \local_connect\course::$states['scheduled'],
            'moodle_id' => null
        );

        $this->assertEquals(array(), \local_connect\course::merge($link_course, $courses));

        $courses = \local_connect\course::get_courses();
        $this->assertEquals(3, count($courses));

        $this->connect_cleanup();
    }

    /**
     * Test we can create a linked course and then unlink it.
     */
    public function test_unlink_course() {
        $this->resetAfterTest();
        $this->connect_cleanup();

        // Create two courses.
        $this->generate_courses(2);

        $courses = \local_connect\course::get_courses(array(), true);
        $this->assertEquals(2, count($courses));

        $link_course = array(
            'module_code' => "TST",
            'module_title' => "TEST MERGE",
            'primary_child' => reset($courses),
            'synopsis' => "This is a test",
            'category_id' => 1,
            'state' => \local_connect\course::$states['scheduled'],
            'moodle_id' => null
        );

        $this->assertEquals(array(), \local_connect\course::merge($link_course, $courses));

        $courses = \local_connect\course::get_courses(array(), true);
        $this->assertEquals(3, count($courses));

        // Unlink!
        $course = reset($courses);
        $course = \local_connect\course::get_course_by_chksum($course->chksum);
        $this->assertEquals(\local_connect\course::$states['created_in_moodle'], $course->state);
        $course->unlink();
        $course = \local_connect\course::get_course_by_chksum($course->chksum);
        $this->assertEquals(\local_connect\course::$states['unprocessed'], $course->state);

        $courses = \local_connect\course::get_courses();
        $this->assertEquals(3, count($courses));

        // TODO - test more stuff, enrolments etc

        $this->connect_cleanup();
    }

    /**
     * Test we can sync a course.
     */
    public function test_course_sync() {
        global $DB;

        $this->resetAfterTest();
        $this->connect_cleanup();

        $data = $this->generate_course();
        $course = \local_connect\course::get_course_by_chksum($data['chksum']);

        // Creates.
        $this->assertFalse($course->is_in_moodle());
        $this->assertEquals("Creating Course: " . $course->chksum, $course->sync());
        $this->assertTrue($course->is_in_moodle());

        // Updates.
        $course->fullname = "TESTING NAME CHANGE";
        $this->assertEquals("Updating Course: " . $course->chksum, $course->sync());
        $this->assertTrue($course->is_in_moodle());
        $mcourse = $DB->get_record('course', array(
            "id" => $course->moodle_id
        ), 'id,fullname');
        $this->assertEquals($course->fullname, $mcourse->fullname);

        // Deletes.
        $course->sink_deleted = true;
        $this->assertEquals("Deleting Course: " . $course->chksum, $course->sync());
        $this->assertFalse($course->is_in_moodle());

        $this->connect_cleanup();
    }

    /**
     * Test shortnames are always unique.
     */
    public function test_course_shortname_check() {
        $this->resetAfterTest();
        $this->connect_cleanup();

        $data = $this->generate_course();
        $course = \local_connect\course::get_course_by_chksum($data['chksum']);
        $course->create_in_moodle();

        $data = $this->generate_course();
        $course2 = \local_connect\course::get_course_by_chksum($data['chksum']);

        $this->assertTrue($course2->has_unique_shortname());
        $course2->shortname = $course->shortname;
        $this->assertFalse($course2->has_unique_shortname());

        $this->connect_cleanup();
    }
}
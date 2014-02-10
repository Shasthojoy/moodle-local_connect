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

namespace local_connect\tests;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper methods for Connect Tests
 */
class connect_testcase extends \advanced_testcase
{
	/**
	 * Insert a record into the Connect DB
	 */
	private function insertDB($table, $data) {
		global $CONNECTDB;

		$fields = implode(',', array_keys($data));
		$qms    = array_fill(0, count($data), '?');
		$qms    = implode(',', $qms);

		$CONNECTDB->execute("INSERT INTO {$table} ($fields) VALUES($qms)", $data);
	}

	/**
	 * Returns a valid enrolment for testing.
	 */
	protected function generate_enrolment($module_delivery_key, $role = 'student') {
		global $CFG;

		static $eid = 10000000;

		$generator = \advanced_testcase::getDataGenerator();
		$user = $generator->create_user();

		$data = array(
			"ukc" => $eid,
			"login" => $user->username,
			"title" => "Mx",
			"initials" => $user->firstname,
			"family_name" => $user->lastname,
			"session_code" => $CFG->connect->session_code,
			"module_delivery_key" => $module_delivery_key,
			"role" => $role,
			"chksum" => uniqid($eid),
			"id_chksum" => uniqid($eid),
			"state" => 1
		);

		$this->insertDB('enrollments', $data);

		return $eid++;
	}

	/**
	 * Creates a bunch of enrolments.
	 */
	protected function generate_enrolments($count, $module_delivery_key, $role = 'student') {
		for ($i = 0; $i < $count; $i++) {
			$this->generate_enrolment($module_delivery_key, $role);
		}
	}

	/**
	 * Generates a random module code.
	 */
	private function generate_module_name() {
		static $prefix = array("Introduction to", "Advanced", "");
		static $subjects = array("Computing", "Science", "Arts", "Physics", "Film", "Theatre", "Engineering", "Electronics", "Media", "Philosophy");
		shuffle($prefix);
		shuffle($subjects);
		return $prefix[0] . " " . $subjects[1];
	}

	/**
	 * Generates a random module code.
	 */
	private function generate_module_code() {
		static $alphabet = array("A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P","Q","R","S","T","U","V","W","X","Y","Z");
		static $numbers = array("0","1","2","3","4","5","6","7","8","9");
		shuffle($alphabet);
		shuffle($numbers);
		return $alphabet[0] . $alphabet[1] . $numbers[0] . $numbers[1] . $numbers[2];
	}

	/**
	 * Returns a valid course module key for testing against.
	 */
	protected function generate_course() {
		global $CFG;

		static $delivery_key = 10000;

		$data = array(
			"module_delivery_key" => $delivery_key,
			"session_code" => $CFG->connect->session_code,
			"delivery_department" => '01',
			"campus" => 1,
			"module_version" => 1,
			"campus_desc" => 'Canterbury',
			"module_week_beginning" => 1,
			"module_length" => 12,
			"module_title" => $this->generate_module_name(),
			"module_code" => $this->generate_module_code(),
			"synopsis" => 'A test course',
			"chksum" => uniqid($delivery_key),
			"id_chksum" => uniqid($delivery_key),
			"category_id" => 1,
			"state" => 1
		);

		$this->insertDB('courses', $data);

		return $delivery_key++;
	}
}

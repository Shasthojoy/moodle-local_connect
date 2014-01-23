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
 * Connect group enrolment container
 */
class group_enrolment {

    /** Our chksum */
    public $chksum;

    /** Our user's login */
    public $login;

    /** Our Connect group ID */
    public $group_id;

    /** Our Connect group - Dont rely on this being set! Use get_group() */
    private $group;

    /** Our Moodle user id - Dont rely on this being set! Use get_moodle_user_id() */
    private $moodle_user_id;

    /**
     * Grab our Connect Group
     */
    private function get_group() {
    	if (!isset($this->group)) {
    		$this->group = group::get($this->group_id);
    	}

    	return $this->group;
    }

    /**
     * Grab our Moodle User's ID
     */
    private function get_moodle_user_id() {
        $user = new user($this->login);
        $this->moodle_user_id = $user->get_moodle_id();
        return $this->moodle_user_id;
    }

    /**
     * Can this be added to Moodle yet?
     */
    public function is_valid() {
    	$group = $this->get_group();
        $groupid = $group->get_moodle_id();
    	if (empty($groupid)) {
    		return false;
    	}

    	$userid = $this->get_moodle_user_id();
    	if (empty($userid)) {
    		return false;
    	}

    	return true;
    }

    /**
     * Check to see if this exists in Moodle
     */
    public function is_in_moodle() {
    	global $DB;

    	if (!$this->is_valid()) {
    		return false;
    	}

    	$group = $this->get_group();
    	$userid = $this->get_moodle_user_id();

        return groups_is_member($group->get_moodle_id(), $userid);
    }

    /**
     * Create this group enrolment in Moodle
     */
    public function create_in_moodle() {
        global $CFG;
        require_once($CFG->dirroot.'/group/lib.php');

    	if (!$this->is_valid()) {
    		return false;
    	}

    	$group = $this->get_group();
    	$userid = $this->get_moodle_user_id();

		return groups_add_member($group->get_moodle_id(), $userid);
    }

    /**
     * Returns all known group enrollments for a given group.
     */
    public static function get_for_group($group) {
        global $CONNECTDB;

        // Select all our groups.
        $data = $CONNECTDB->get_records("group_enrollments", array(
            "group_id" => $group->id
        ), '', 'chksum, login');

        // Map to objects.
        foreach ($data as &$group_enrolment) {
        	$obj = new group_enrolment();

            $obj->chksum = $group_enrolment->chksum;
        	$obj->login = $group_enrolment->login;
        	$obj->group_id = $group->id;

        	$group_enrolment = $obj;
        }

        return $data;
    }

    /**
     * Returns all known group enrolments for a given session code.
     */
    public static function get_all($session_code) {
        global $CONNECTDB;

        // Select all our groups.
        $data = $CONNECTDB->get_records("group_enrollments", array(
            "sessioncode" => $session_code
        ));

        // Map to objects.
        foreach ($data as &$group_enrolment) {
            $obj = new group_enrolment();
            $obj->chksum = $group_enrolment->chksum;
            $obj->login = $group_enrolment->login;
            $obj->group_id = $group_enrolment->group_id;

            $group_enrolment = $obj;
        }

        return $data;
    }
}
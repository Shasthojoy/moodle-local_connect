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

namespace local_connect\provisioner\actions;

defined('MOODLE_INTERNAL') || die();

/**
 * Moodle provisioning toolkit.
 * Notify course action.
 *
 * @since Moodle 2015
 */
class course_notify extends base
{
    private $_data;

    /**
     * Constructor.
     * @param $data
     */
    public function __construct($data) {
        parent::__construct();

        $this->_data = $data;
    }

    /**
     * Get task name.
     */
    public function get_task_name() {
        return 'course_notify';
    }

    /**
     * Execute this action.
     */
    public function run() {
        // TODO.
        parent::run();
    }

    /**
     * toString override.
     */
    public function __toString() {
        return "Notified course " . $this->_data['id'] . ": " . $this->_data['message'] . parent::__toString();
    }
}
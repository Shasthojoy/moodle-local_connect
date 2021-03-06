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

namespace local_connect\nagios;

/**
 * Checks cache.
 */
class lastsync_check extends \local_nagios\base_check
{
    public function execute() {
        $lastrun = get_config('local_connect', 'lastsync');
        if (!$lastrun) {
            return $this->warning("Connect has not yet run.");
        }

        $delta = time() - $lastrun;
        if ($delta > 90000) {
            $this->warning("Connect has not run for more than 25 hours.");
        } else if ($delta > 172800) {
            $this->error("Connect has not run for more than 48 hours.");
        }
    }
}

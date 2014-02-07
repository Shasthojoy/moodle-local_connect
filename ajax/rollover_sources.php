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
 * Grabs rollover sources for this installation
 *
 * @package    local_connect
 * @copyright  2014 Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(dirname(__FILE__) . '/../../../config.php');

global $PAGE, $OUTPUT, $USER;

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/connect/ajax/rollover_sources.php');

if (!isloggedin()) {
	throw new moodleexception("You must be logged in.");
}

if (!\local_connect\utils::is_enabled() || !\local_connect\utils::enable_new_features()) {
	throw new moodleexception("This feature has not been enabled.");
}

$targets = \local_connect\rollover::get_target_list();
$sources = \local_connect\rollover::get_source_list();

echo json_encode(array(
	"targets" => $targets,
	"sources" => $sources
));
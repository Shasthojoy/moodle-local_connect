<?php
/**
 * Grabs rollover sources for this installation
 */

define('AJAX_SCRIPT', true);

require(dirname(__FILE__) . '/../../../config.php');

global $PAGE, $OUTPUT, $USER;

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/util/ajax/rollover_sources.php');

if (!isloggedin()) {
	throw new moodleexception("You must be logged in.");
}

$targets = \local_connect\rollover::get_target_list();
$sources = \local_connect\rollover::get_source_list();

echo json_encode(array(
	"targets" => $targets,
	"sources" => $sources
));
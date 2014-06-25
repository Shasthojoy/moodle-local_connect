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

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $ADMIN->add('reports', new admin_externalpage('reportconnectreport', get_string('connectreport', 'local_connect'),
        "$CFG->wwwroot/local/connect/index.php", 'local/connect:manage'));

    $settings = new admin_settingpage('local_connect', get_string('pluginname', 'local_connect'));
    $ADMIN->add('localplugins', $settings);

    $rules = new admin_externalpage('connectrules', "Category Rules", "$CFG->wwwroot/local/connect/rules.php",
        'moodle/site:config');
    $ADMIN->add('localplugins', $rules);

    $meta = new admin_externalpage('reportconnectmeta', "Connect Meta Manager", "$CFG->wwwroot/local/connect/meta/index.php",
        'moodle/site:config');
    $ADMIN->add('localplugins', $meta);

    $cdb = new admin_externalpage('connectdatabrowse', "Connect Data Browser", "$CFG->wwwroot/local/connect/browse/index.php",
        'local/connect:helpdesk');
    $ADMIN->add('localplugins', $cdb);

    $settings->add(new admin_setting_configcheckbox(
        'local_connect_enable',
        get_string('enable', 'local_connect'),
        '',
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_connect_enable_new_features',
        get_string('new_feature_toggle', 'local_connect'),
        get_string('new_feature_toggle_desc', 'local_connect'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_connect_enable_cron',
        get_string('cron_toggle', 'local_connect'),
        get_string('cron_toggle_desc', 'local_connect'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_connect/enable_course_sync',
        'Enable course syncing',
        'Allows modules to update their description from SDS (you do not want to enable this for anything prior to 2014).',
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_connect/strict_sync',
        'Enable stricter SDS sync',
        'Forces modules to update to SDS data, rather than letting convenors modify them Moodle-side.',
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_connect/enable_hipchat',
        'Enable hipchat notifications',
        'Note: Spams the developers when things go wrong.',
        0
    ));
}

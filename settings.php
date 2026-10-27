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
 * FredKI The Agent: protected central gateway only. No service password in Moodle.
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configcheckbox(
        'block_fredkitheagent/managed_api_url_lock',
        get_string('settingapilock', 'block_fredkitheagent'),
        get_string('settingapilock_desc', 'block_fredkitheagent'),
        1
    ));
    $settings->add(new admin_setting_configtext(
        'block_fredkitheagent/managed_api_url',
        get_string('settingapiurl', 'block_fredkitheagent'),
        get_string('settingapiurl_desc', 'block_fredkitheagent'),
        'https://fredki-theagent.lernsteine.de/fredki-hub/api/v1/index.php',
        PARAM_URL
    ));
    $settings->hide_if('block_fredkitheagent/managed_api_url', 'block_fredkitheagent/managed_api_url_lock', 'checked');
    $settings->add(new admin_setting_configtext(
        'block_fredkitheagent/sync_time_budget',
        get_string('settingsyncbudget', 'block_fredkitheagent'),
        get_string('settingsyncbudget_desc', 'block_fredkitheagent'),
        30,
        PARAM_INT
    ));
    $settings->add(new admin_setting_heading(
        'block_fredkitheagent/managedactivation',
        get_string('settingactivationheading', 'block_fredkitheagent'),
        html_writer::link(
            new moodle_url('/blocks/fredkitheagent/activation.php'),
            get_string('settingactivationlink', 'block_fredkitheagent')
        )
    ));
    $settings->add(new admin_setting_heading(
        'block_fredkitheagent/chatappearance',
        get_string('settingappearanceheading', 'block_fredkitheagent'),
        get_string('settingappearanceheading_desc', 'block_fredkitheagent')
    ));
    $settings->add(new admin_setting_configcolourpicker(
        'block_fredkitheagent/chatheadercolor',
        get_string('settingheadercolor', 'block_fredkitheagent'),
        '',
        '#e7f3fb'
    ));
    $settings->add(new admin_setting_configcolourpicker(
        'block_fredkitheagent/chataccentcolor',
        get_string('settingaccentcolor', 'block_fredkitheagent'),
        '',
        '#007f91'
    ));
    $settings->add(new admin_setting_configcolourpicker(
        'block_fredkitheagent/chatbackgroundcolor',
        get_string('settingbgcolor', 'block_fredkitheagent'),
        '',
        '#f5f7fa'
    ));
    $settings->add(new admin_setting_configtext(
        'block_fredkitheagent/chatwelcometext',
        get_string('settingwelcome', 'block_fredkitheagent'),
        '',
        get_string('settingwelcomedefault', 'block_fredkitheagent'),
        PARAM_TEXT
    ));
    $settings->add(new admin_setting_configtext(
        'block_fredkitheagent/chatfootertext',
        get_string('settingfooter', 'block_fredkitheagent'),
        '',
        get_string('settingfooterdefault', 'block_fredkitheagent'),
        PARAM_TEXT
    ));
    $settings->add(new admin_setting_configstoredfile(
        'block_fredkitheagent/chatlogo',
        get_string('settinglogo', 'block_fredkitheagent'),
        get_string('settinglogo_desc', 'block_fredkitheagent'),
        'chatlogo'
    ));
}

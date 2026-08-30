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
 * Admin settings for the AI Tutor plugin.
 *
 * @package    local_ai_tutor
 * @copyright  2026 Highskills and more <info@highskills.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_ai_tutor',
        get_string('pluginname', 'local_ai_tutor')
    );
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configtext(
        'local_ai_tutor/foundry_url',
        get_string('foundryurl', 'local_ai_tutor'),
        get_string('foundryurl_desc', 'local_ai_tutor'),
        '',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_ai_tutor/api_key',
        get_string('apikey', 'local_ai_tutor'),
        get_string('apikey_desc', 'local_ai_tutor'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_ai_tutor/stream_timeout',
        get_string('streamtimeout', 'local_ai_tutor'),
        get_string('streamtimeout_desc', 'local_ai_tutor'),
        '300',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configselect(
        'local_ai_tutor/widget_position',
        get_string('widgetposition', 'local_ai_tutor'),
        get_string('widgetposition_desc', 'local_ai_tutor'),
        'bottomright',
        [
            'topright'    => get_string('position_topright', 'local_ai_tutor'),
            'topleft'     => get_string('position_topleft', 'local_ai_tutor'),
            'bottomright' => get_string('position_bottomright', 'local_ai_tutor'),
            'bottomleft'  => get_string('position_bottomleft', 'local_ai_tutor'),
        ]
    ));
}

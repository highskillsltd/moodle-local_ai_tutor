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
 * Post-install hook for local_ai_tutor.
 *
 * Creates the per-course "Enable AI Tutor" checkbox as a course custom
 * field, since this Moodle version has no generic course_edit_form plugin
 * callback — the Course Custom Fields API is the only supported way to add
 * a field to course/edit.php from a local plugin.
 *
 * @package   local_ai_tutor
 * @copyright 2026 Highskills and more <info@highskills.co.il>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Called by Moodle after the plugin tables are created on install.
 */
function xmldb_local_ai_tutor_install() {
    local_ai_tutor_create_course_checkbox();
}

/**
 * Creates the "Enable AI Tutor" course custom field if it doesn't already exist.
 *
 * Visible/editable by teachers only (VISIBLETOTEACHERS), unchecked by default.
 */
function local_ai_tutor_create_course_checkbox() {
    global $DB;

    if ($DB->record_exists('customfield_field', ['shortname' => 'aitutor_enabled'])) {
        return;
    }

    $handler = \core_course\customfield\course_handler::create();

    $category = \core_customfield\category_controller::create(0, (object) [
        'name' => get_string('customfieldcategory', 'local_ai_tutor'),
    ], $handler);
    $category->save();

    $field = \core_customfield\field_controller::create(0, (object) [
        'shortname'  => 'aitutor_enabled',
        'name'       => get_string('enableforcourse', 'local_ai_tutor'),
        'type'       => 'checkbox',
        'categoryid' => $category->get('id'),
        'configdata' => json_encode([
            'checkbydefault' => 0,
            'visibility'     => \core_course\customfield\course_handler::VISIBLETOTEACHERS,
            'locked'         => 0,
            'required'       => 0,
            'uniquevalues'   => 0,
        ]),
    ]);
    $field->save();
}

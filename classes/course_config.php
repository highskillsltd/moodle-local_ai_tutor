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

namespace local_ai_tutor;

/**
 * Reads the per-course "Enable AI Tutor" opt-in checkbox.
 *
 * Backed by the customfield_checkbox field created in db/install.php, since
 * there is no dedicated plugin table for this — one field, one source of truth.
 *
 * @package   local_ai_tutor
 * @copyright 2026 Highskills and more <info@highskills.co.il>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_config {
    /**
     * Whether the AI Tutor is enabled for the given course.
     *
     * @param int $courseid Course ID.
     * @return bool True if the "Enable AI Tutor" checkbox is checked.
     */
    public static function is_enabled_for_course(int $courseid): bool {
        $handler = \core_course\customfield\course_handler::create();
        foreach ($handler->get_instance_data($courseid, true) as $data) {
            if ($data->get_field()->get('shortname') === 'aitutor_enabled') {
                return (bool) $data->get_value();
            }
        }
        return false;
    }

    /**
     * Course IDs for every course where the AI Tutor is enabled.
     *
     * Used by the content-cache rebuild task to avoid harvesting courses
     * that opted out.
     *
     * @return int[] Course IDs.
     */
    public static function get_enabled_course_ids(): array {
        global $DB;

        $field = $DB->get_record('customfield_field', ['shortname' => 'aitutor_enabled'], 'id', IGNORE_MISSING);
        if (!$field) {
            return [];
        }

        $courseids = $DB->get_fieldset_select(
            'customfield_data',
            'DISTINCT instanceid',
            'fieldid = :fieldid AND intvalue = 1',
            ['fieldid' => $field->id]
        );

        return array_map('intval', $courseids);
    }
}

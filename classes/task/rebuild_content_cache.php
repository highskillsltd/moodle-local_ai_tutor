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

namespace local_ai_tutor\task;

use local_ai_tutor\content_harvester;
use local_ai_tutor\course_config;

/**
 * Periodically rebuilds the pre-harvested content cache for every course
 * that has opted into the AI Tutor.
 *
 * This is the Moodle-side "TTL package" described in CLAUDE.md: harvesting
 * and text-extraction happen here, ahead of time, so a student's first chat
 * message in a session can attach `content` immediately instead of doing
 * live harvesting/PDF-extraction in the request's critical path.
 *
 * @package   local_ai_tutor
 * @copyright 2026 Highskills and more <info@highskills.co.il>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rebuild_content_cache extends \core\task\scheduled_task {
    /**
     * Task name shown in Site Administration → Server → Scheduled tasks.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_rebuildcontentcache', 'local_ai_tutor');
    }

    /**
     * Rebuild the content cache for every enabled course, one course at a time
     * so a failure harvesting one course doesn't block the rest.
     */
    public function execute() {
        global $DB;

        foreach (course_config::get_enabled_course_ids() as $courseid) {
            try {
                $items = content_harvester::harvest_course($courseid);
            } catch (\Throwable $e) {
                mtrace("local_ai_tutor: failed to harvest course {$courseid}: " . $e->getMessage());
                continue;
            }

            $DB->delete_records('local_ai_tutor_content_cache', ['courseid' => $courseid]);

            $now = time();
            $records = array_map(function ($item) use ($courseid, $now) {
                return (object) [
                    'courseid'     => $courseid,
                    'source_type'  => $item->source_type,
                    'source_cmid'  => $item->source_cmid,
                    'title'        => $item->title,
                    'content_text' => $item->text,
                    'url'          => $item->url,
                    'timemodified' => $now,
                ];
            }, $items);

            if ($records !== []) {
                $DB->insert_records('local_ai_tutor_content_cache', $records);
            }

            mtrace("local_ai_tutor: rebuilt content cache for course {$courseid} (" . count($records) . ' items)');
        }
    }
}

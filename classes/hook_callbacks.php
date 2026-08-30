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

use core\hook\output\before_footer_html_generation;

/**
 * Hook callbacks for local_ai_tutor.
 *
 * @package   local_ai_tutor
 * @copyright 2026 Highskills and more <info@highskills.co.il>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Injects the floating chat widget on course pages, gated to courses where
     * the "Enable AI Tutor" checkbox is checked.
     *
     * Replaces the legacy before_footer callback (see the #[replaces_callbacks]
     * attribute on before_footer_html_generation) — this hook fires on every
     * page render globally, so the course-context and opt-in checks below are
     * what actually scope it to the right pages.
     *
     * @param before_footer_html_generation $hook
     */
    public static function before_footer_html_generation(before_footer_html_generation $hook): void {
        global $PAGE;

        if (!isloggedin() || isguestuser()) {
            return;
        }

        $context = $PAGE->context;
        if (!$context || $context->contextlevel !== CONTEXT_COURSE) {
            return;
        }

        $courseid = $context->instanceid;
        if ($courseid == SITEID) {
            return;
        }

        if (!has_capability('local/ai_tutor:use', $context)) {
            return;
        }

        if (!course_config::is_enabled_for_course($courseid)) {
            return;
        }

        $client = new api_client();
        if (!$client->is_configured()) {
            return;
        }

        $renderer = $PAGE->get_renderer('local_ai_tutor');
        $hook->add_html($renderer->render_chat_widget($courseid));
    }
}

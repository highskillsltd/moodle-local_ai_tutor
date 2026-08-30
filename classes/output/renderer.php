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

namespace local_ai_tutor\output;

/**
 * Renderer for local_ai_tutor.
 *
 * @package   local_ai_tutor
 * @copyright 2026 Highskills and more <info@highskills.co.il>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {
    /**
     * Render the floating chat widget shell and wire up its AMD module.
     *
     * The widget starts open; all state (session_id, message history) lives
     * client-side in amd/src/chatbox.js, keyed to this course. Docking corner
     * is a site-wide admin setting (local_ai_tutor/widget_position).
     *
     * @param int $courseid Course ID the widget is scoped to.
     * @return string HTML for the widget, ready to echo into the page footer.
     */
    public function render_chat_widget(int $courseid): string {
        global $USER;

        $this->page->requires->strings_for_js([
            'chatplaceholder',
            'send',
            'thinking',
            'connectsthedots',
            'practiceproblems',
            'unknownerror',
        ], 'local_ai_tutor');

        $this->page->requires->js_call_amd('local_ai_tutor/chatbox', 'init', [[
            'courseId'  => $courseid,
            'sesskey'   => sesskey(),
            'chatUrl'   => (new \moodle_url('/local/ai_tutor/chat.php'))->out(false),
        ]]);

        $position = get_config('local_ai_tutor', 'widget_position') ?: 'bottomright';

        return $this->render_from_template('local_ai_tutor/chat_panel', [
            'title'    => get_string('pluginname', 'local_ai_tutor'),
            'posclass' => 'local-ai-tutor-pos-' . $position,
            'greeting' => get_string('greeting', 'local_ai_tutor', $USER->firstname),
        ]);
    }
}

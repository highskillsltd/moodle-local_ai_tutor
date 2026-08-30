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
 * Library functions for local_ai_tutor.
 *
 * @package   local_ai_tutor
 * @copyright 2026 Highskills and more <info@highskills.co.il>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// The floating chat widget is injected via the before_footer_html_generation
// hook (classes/hook_callbacks.php, registered in db/hooks.php) rather than
// a legacy before_footer() callback here — see that class's docblock.

/**
 * Adds the "AI Tutor Insights" link to the course administration menu,
 * visible only to users who can see the struggle-pattern/content-gap views.
 *
 * @param navigation_node $navigation The course navigation node.
 * @param stdClass        $course     The course object.
 * @param context_course  $context    The course context.
 */
function local_ai_tutor_extend_navigation_course($navigation, $course, $context) {
    if (!has_capability('local/ai_tutor:viewinsights', $context)) {
        return;
    }

    $url = new moodle_url('/local/ai_tutor/insights.php', ['courseid' => $course->id]);
    $navigation->add(
        get_string('insights', 'local_ai_tutor'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'local_ai_tutor_insights'
    );
}

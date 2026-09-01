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
 * English language strings for the AI Tutor plugin.
 *
 * @package    local_ai_tutor
 * @copyright  2026 Highskills and more <info@highskills.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['ai_tutor:use'] = 'Use the AI Tutor chat widget';
$string['ai_tutor:viewinsights'] = 'View AI Tutor struggle patterns and content gaps';
$string['api_not_configured'] = 'The AI Tutor is not configured. Please contact your site administrator.';
$string['apierror'] = 'The AI Tutor backend returned an error: {$a}';
$string['apikey'] = 'API key (Bearer token)';
$string['apikey_desc'] = 'The tenant API key shown in the Foundry admin panel after creating or regenerating the tenant.';
$string['chatplaceholder'] = 'Ask a question about this course…';
$string['closebutton'] = 'Close';
$string['col_count'] = 'Times asked';
$string['col_question'] = 'Question';
$string['col_studentcount'] = 'Students stuck';
$string['col_students'] = 'Students';
$string['col_topic'] = 'Topic';
$string['connectsthedots'] = 'Connects the dots';
$string['contentgaps'] = 'Content gaps';
$string['curlerror'] = 'Could not reach the AI Tutor backend: {$a}';
$string['customfieldcategory'] = 'AI Tutor';
$string['enableforcourse'] = 'Enable AI Tutor';
$string['foundryurl'] = 'Foundry endpoint URL';
$string['foundryurl_desc'] = 'Full URL up to and including the tenant and task code, e.g. https://your-host/api/v1/{tenant-uuid}/private-tutor — this plugin appends /chat itself.';
$string['greeting'] = 'Hi {$a}, ask me about anything in this course.';
$string['httpstatuslabel'] = 'HTTP';
$string['insights'] = 'AI Tutor Insights';
$string['nogapsyet'] = 'No content gaps recorded yet.';
$string['nostrugglesyet'] = 'No struggle patterns recorded yet.';
$string['pluginname'] = 'AI Tutor';
$string['position_bottomleft'] = 'Bottom left';
$string['position_bottomright'] = 'Bottom right';
$string['position_topleft'] = 'Top left';
$string['position_topright'] = 'Top right';
$string['practiceproblems'] = 'Practice problems';
$string['privacy:metadata:foundry'] = 'To answer a question, the student\'s question and this course\'s content are sent to an external AI backend (Foundry) configured by the site administrator.';
$string['privacy:metadata:foundry:content'] = 'Plain-text course content (pages, forum posts, file text, etc.) sent once per chat session — never includes student submissions, grades, or attempts.';
$string['privacy:metadata:foundry:course_lang'] = 'The course language, sent so the answer is generated in the right language.';
$string['privacy:metadata:foundry:question'] = 'The question typed by the student.';
$string['privacy:metadata:foundry:recent_questions'] = 'The student\'s last few questions in this session, sent so the backend can detect when the student is stuck.';
$string['privacy:metadata:foundry:session_id'] = 'A random identifier for the chat session, generated in the browser.';
$string['privacy:metadata:local_ai_tutor_turns'] = 'Information about each answer the AI Tutor gave a student, used to build the teacher struggle-pattern and content-gap views.';
$string['privacy:metadata:local_ai_tutor_turns:in_scope'] = 'Whether the question was answerable from this course\'s content.';
$string['privacy:metadata:local_ai_tutor_turns:primary_citation_id'] = 'The highest-confidence citation for the answer, if any.';
$string['privacy:metadata:local_ai_tutor_turns:question'] = 'The question the student asked.';
$string['privacy:metadata:local_ai_tutor_turns:sessionid'] = 'The chat session this question was asked in.';
$string['privacy:metadata:local_ai_tutor_turns:stuck'] = 'Whether the student appeared to be stuck on this question.';
$string['privacy:metadata:local_ai_tutor_turns:timecreated'] = 'The time the question was asked.';
$string['privacy:metadata:local_ai_tutor_turns:userid'] = 'The ID of the student who asked the question.';
$string['send'] = 'Send';
$string['streamtimeout'] = 'Stream timeout (seconds)';
$string['streamtimeout_desc'] = 'Maximum time to wait for the backend to finish streaming an answer before giving up. Minimum 30 seconds.';
$string['strugglepatterns'] = 'Struggle patterns';
$string['task_rebuildcontentcache'] = 'Rebuild AI Tutor course content cache';
$string['thinking'] = 'Thinking…';
$string['tutornotenabled'] = 'The AI Tutor is not enabled for this course.';
$string['unknownerror'] = 'Unknown error';
$string['widgetposition'] = 'Widget position';
$string['widgetposition_desc'] = 'Which corner of the course page the chat widget docks to.';

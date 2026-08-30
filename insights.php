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
 * Teacher-only insights page: struggle patterns and content gaps.
 *
 * Built entirely from local_ai_tutor_turns — no Foundry calls needed,
 * per CLAUDE.md §"What this repo owns and must store itself".
 *
 * @package   local_ai_tutor
 * @copyright 2026 Highskills and more <info@highskills.co.il>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$context = context_course::instance($courseid);
$course = get_course($courseid);

require_login($course);
require_capability('local/ai_tutor:viewinsights', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ai_tutor/insights.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('insights', 'local_ai_tutor'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('report');

// Struggle patterns: named students, grouped by the topic they got stuck on,
// over a rolling 30-day window.
$since = time() - (30 * DAYSECS);
$sql = "SELECT t.primary_citation_id, t.citation_title, t.citation_url,
               COUNT(DISTINCT t.userid) AS studentcount
          FROM {local_ai_tutor_turns} t
         WHERE t.courseid = :courseid
           AND t.stuck = 1
           AND t.timecreated >= :since
           AND t.primary_citation_id IS NOT NULL
      GROUP BY t.primary_citation_id, t.citation_title, t.citation_url
      ORDER BY studentcount DESC";
$struggles = $DB->get_records_sql($sql, ['courseid' => $courseid, 'since' => $since]);

$strugglerows = [];
foreach ($struggles as $row) {
    // Named students for this topic — small per-topic lookup, acceptable at this scale.
    $namesql = "SELECT DISTINCT u.id, u.firstname, u.lastname
                  FROM {local_ai_tutor_turns} t
                  JOIN {user} u ON u.id = t.userid
                 WHERE t.courseid = :courseid
                   AND t.stuck = 1
                   AND t.primary_citation_id = :citationid
                   AND t.timecreated >= :since";
    $students = $DB->get_records_sql($namesql, [
        'courseid'   => $courseid,
        'citationid' => $row->primary_citation_id,
        'since'      => $since,
    ]);
    $names = array_map(fn($u) => fullname($u), $students);

    $strugglerows[] = [
        'title'        => $row->citation_title ?: $row->primary_citation_id,
        'url'          => $row->citation_url,
        'studentcount' => $row->studentcount,
        'students'     => implode(', ', $names),
    ];
}

// Content gaps: out-of-scope questions, grouped with simple case/whitespace
// normalization (v1 — see CLAUDE.md's noted limitation on under-grouping).
$gapsql = "SELECT question, timecreated
             FROM {local_ai_tutor_turns}
            WHERE courseid = :courseid
              AND in_scope = 0
              AND timecreated >= :since
         ORDER BY timecreated DESC";
$gaps = $DB->get_records_sql($gapsql, ['courseid' => $courseid, 'since' => $since]);

$grouped = [];
foreach ($gaps as $gap) {
    $key = preg_replace('/\s+/', ' ', trim(core_text::strtolower($gap->question)));
    if (!isset($grouped[$key])) {
        $grouped[$key] = ['question' => $gap->question, 'count' => 0];
    }
    $grouped[$key]['count']++;
}
usort($grouped, fn($a, $b) => $b['count'] <=> $a['count']);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('insights', 'local_ai_tutor'));

echo $OUTPUT->render_from_template('local_ai_tutor/insights_struggle', [
    'hasrows' => !empty($strugglerows),
    'rows'    => $strugglerows,
]);

echo $OUTPUT->render_from_template('local_ai_tutor/insights_gaps', [
    'hasrows' => !empty($grouped),
    'rows'    => array_values($grouped),
]);

echo $OUTPUT->footer();

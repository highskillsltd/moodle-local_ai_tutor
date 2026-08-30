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
 * SSE proxy for the Foundry private-tutor /chat endpoint.
 *
 * The JS widget (amd/src/chatbox.js) tracks, for the life of the open
 * widget, whether it has already sent `content` for the current session_id —
 * see CLAUDE.md's "session-once" flow. It signals that here via
 * `needs_content`; this script does the actual content-cache lookup and
 * attaches `content` to the outbound Foundry request only when asked to.
 *
 * @package   local_ai_tutor
 * @copyright 2026 Highskills and more <info@highskills.co.il>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing -- login/capability checked below.

require_once(__DIR__ . '/../../config.php');

use local_ai_tutor\api_client;
use local_ai_tutor\content_harvester;
use local_ai_tutor\course_config;

$courseid = required_param('course_id', PARAM_INT);
$course = get_course($courseid);
$context = context_course::instance($courseid);

require_login($course);
require_sesskey();
require_capability('local/ai_tutor:use', $context);

if (!course_config::is_enabled_for_course($courseid)) {
    throw new moodle_exception('tutornotenabled', 'local_ai_tutor');
}

$sessionid    = required_param('session_id', PARAM_ALPHANUMEXT);
$question     = required_param('question', PARAM_TEXT);
$needscontent = optional_param('needs_content', 0, PARAM_BOOL);
$recentraw    = optional_param('recent_questions', '[]', PARAM_RAW);

$recentquestions = json_decode($recentraw, true);
if (!is_array($recentquestions)) {
    $recentquestions = [];
}
$recentquestions = array_slice(array_map('clean_param', $recentquestions, array_fill(0, count($recentquestions), PARAM_TEXT)), -5);

$payload = [
    'session_id'       => $sessionid,
    'course_id'        => $courseid,
    'question'         => $question,
    'recent_questions' => $recentquestions,
];

if ($needscontent) {
    $rows = $DB->get_records('local_ai_tutor_content_cache', ['courseid' => $courseid]);

    // Course just enabled and the scheduled task hasn't run yet — harvest synchronously
    // this once so the widget is usable immediately, rather than waiting up to 30 minutes.
    if (empty($rows)) {
        $harvested = content_harvester::harvest_course($courseid);
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
        }, $harvested);
        if ($records !== []) {
            $DB->insert_records('local_ai_tutor_content_cache', $records);
        }
        $rows = $records;
    }

    if (!empty($rows)) {
        $lang = substr(current_language(), 0, 2);
        $payload['course_lang'] = $lang;
        $payload['content'] = [
            'items' => array_map(function ($row) {
                return [
                    'source_type' => $row->source_type,
                    'source_cmid' => $row->source_cmid,
                    'title'       => $row->title,
                    'text'        => $row->content_text,
                    'url'         => $row->url,
                ];
            }, array_values((array) $rows)),
        ];
    }
}

$client = new api_client();

// Release the session write lock before the long-running stream so other
// browser tabs/requests are not blocked for the full pipeline duration.
\core\session\manager::write_close();

while (ob_get_level() > 0) {
    ob_end_clean();
}
ob_implicit_flush(true);

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
header('Content-Encoding: identity');

if (!$client->is_configured()) {
    echo 'data: ' . json_encode([
        'event'   => 'error',
        'message' => get_string('api_not_configured', 'local_ai_tutor'),
    ]) . "\n\n";
    flush();
    exit;
}

$parsebuffer = '';
$resultdata  = null;

$streamcallback = function (string $chunk) use (&$parsebuffer, &$resultdata): void {
    echo $chunk;
    flush();

    if ($resultdata !== null) {
        return;
    }

    $parsebuffer .= $chunk;
    $blocks      = explode("\n\n", $parsebuffer);
    $parsebuffer = array_pop($blocks);

    foreach ($blocks as $block) {
        $dataline = null;
        foreach (explode("\n", $block) as $line) {
            if (strpos($line, 'data: ') === 0) {
                $dataline = substr($line, 6);
                break;
            }
        }
        if ($dataline === null) {
            continue;
        }

        $decoded = json_decode($dataline, true);
        if (!is_array($decoded) || ($decoded['event'] ?? '') !== 'result') {
            continue;
        }

        $resultdata = $decoded;
    }
};

try {
    $client->stream_chat($payload, $streamcallback);
} catch (\Throwable $e) {
    echo 'data: ' . json_encode(['event' => 'error', 'message' => $e->getMessage()]) . "\n\n";
    flush();
    exit;
}

// Persist the turn for the teacher insight views — skip content_required
// bounces (no real answer was given) and anything we failed to parse.
if ($resultdata !== null && empty($resultdata['content_required'])) {
    $citations = $resultdata['citations'] ?? [];
    $primary = null;
    foreach ($citations as $citation) {
        if (($citation['id'] ?? null) === ($resultdata['primary_citation_id'] ?? null)) {
            $primary = $citation;
            break;
        }
    }

    $record = (object) [
        'userid'              => $USER->id,
        'courseid'            => $courseid,
        'sessionid'           => $sessionid,
        'question'            => $question,
        'in_scope'            => !empty($resultdata['in_scope']) ? 1 : 0,
        'stuck'               => !empty($resultdata['stuck']) ? 1 : 0,
        'primary_citation_id' => $resultdata['primary_citation_id'] ?? null,
        'citation_title'      => $primary['title'] ?? null,
        'citation_url'        => $primary['url'] ?? null,
        'timecreated'         => time(),
    ];

    $DB->insert_record('local_ai_tutor_turns', $record);
}

exit;

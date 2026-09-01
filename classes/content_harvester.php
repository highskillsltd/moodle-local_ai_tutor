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
 * Walks a course's content and produces plain-text ContentItem rows for the
 * Foundry /chat `content.items[]` payload — see CLAUDE.md for the exact field
 * names this must match (source_type, source_cmid, source_instance_id, title,
 * text, url).
 *
 * v1 simplifications, documented rather than hidden:
 * - SCORM and H5P activities contribute only their `intro` description —
 *   parsing the packages themselves (SCORM manifest / .h5p content.json) is
 *   out of scope for v1.
 * - Quiz questions that resolve via a random/set question reference (rather
 *   than a fixed question_reference) are skipped, since there's no single
 *   fixed question text to extract for those slots.
 * - Student personal data (submissions, grades, individual attempts) is never
 *   read — only definitional/description content.
 *
 * @package   local_ai_tutor
 * @copyright 2026 Highskills and more <info@highskills.co.il>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content_harvester {
    /**
     * Harvest all supported content for one course.
     *
     * @param int $courseid Course ID.
     * @return \stdClass[] Rows shaped {source_type, source_cmid, source_instance_id, title, text, url}.
     */
    public static function harvest_course(int $courseid): array {
        global $DB;

        $items = [];
        $modinfo = get_fast_modinfo($courseid);

        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible || $cm->deletioninprogress) {
                continue;
            }

            switch ($cm->modname) {
                case 'page':
                    self::add_page($items, $cm);
                    break;
                case 'label':
                    self::add_label($items, $cm);
                    break;
                case 'book':
                    self::add_book($items, $cm);
                    break;
                case 'wiki':
                    self::add_wiki($items, $cm);
                    break;
                case 'resource':
                    self::add_files($items, $cm, 'mod_resource', 'content');
                    break;
                case 'folder':
                    self::add_files($items, $cm, 'mod_folder', 'content');
                    break;
                case 'forum':
                    self::add_forum($items, $cm);
                    break;
                case 'assign':
                    self::add_assign($items, $cm);
                    break;
                case 'quiz':
                    self::add_quiz($items, $cm);
                    break;
                case 'glossary':
                    self::add_glossary($items, $cm);
                    break;
                case 'scorm':
                    self::add_intro_only($items, $cm, 'scorm');
                    break;
                case 'data':
                    self::add_data($items, $cm);
                    break;
                case 'h5pactivity':
                    self::add_intro_only($items, $cm, 'h5pactivity');
                    break;
                case 'lesson':
                    self::add_lesson($items, $cm);
                    break;
            }
        }

        return $items;
    }

    /**
     * Build one content-item row and append it to $items.
     *
     * @param \stdClass[]  $items    Accumulator, passed by reference.
     * @param string       $type     source_type value (e.g. 'page', 'forum_post').
     * @param \cm_info|null $cm      Course module this item belongs to, or null (e.g. a single forum post).
     * @param int          $instanceid Module/activity instance ID.
     * @param string       $title    Item title.
     * @param string       $text     Extracted plain text. Skipped entirely if blank.
     * @param \moodle_url  $url      Link back to the source in Moodle.
     */
    private static function add_item(
        array &$items,
        string $type,
        ?\cm_info $cm,
        int $instanceid,
        string $title,
        string $text,
        \moodle_url $url
    ): void {
        $text = trim($text);
        if ($text === '') {
            return;
        }
        $items[] = (object) [
            'source_type'       => $type,
            'source_cmid'       => $cm ? $cm->id : null,
            'source_instance_id' => $instanceid,
            'title'             => $title,
            'text'              => $text,
            'url'               => $url->out(false),
        ];
    }

    /**
     * Strip HTML down to plain text.
     *
     * @param string|null $html Raw HTML content.
     * @return string Plain text.
     */
    private static function plain(?string $html): string {
        return trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * mod_page — main content field.
     *
     * @param \stdClass[] $items Accumulator array, passed by reference.
     * @param \cm_info    $cm    The course module being harvested.
     */
    private static function add_page(array &$items, \cm_info $cm): void {
        global $DB;
        $page = $DB->get_record('page', ['id' => $cm->instance], 'id, content', IGNORE_MISSING);
        if (!$page) {
            return;
        }
        self::add_item($items, 'page', $cm, $cm->instance, $cm->get_formatted_name(), self::plain($page->content), $cm->url);
    }

    /**
     * mod_label — body lives in the module's own "intro" field.
     *
     * @param \stdClass[] $items Accumulator array, passed by reference.
     * @param \cm_info    $cm    The course module being harvested.
     */
    private static function add_label(array &$items, \cm_info $cm): void {
        global $DB;
        $label = $DB->get_record('label', ['id' => $cm->instance], 'id, intro', IGNORE_MISSING);
        if (!$label) {
            return;
        }
        self::add_item($items, 'label', $cm, $cm->instance, $cm->get_formatted_name(), self::plain($label->intro), $cm->url);
    }

    /**
     * mod_book — one item per chapter.
     *
     * @param \stdClass[] $items Accumulator array, passed by reference.
     * @param \cm_info    $cm    The course module being harvested.
     */
    private static function add_book(array &$items, \cm_info $cm): void {
        global $DB;
        $chapters = $DB->get_records('book_chapters', ['bookid' => $cm->instance], 'pagenum', 'id, title, content');
        foreach ($chapters as $chapter) {
            $url = new \moodle_url('/mod/book/view.php', ['id' => $cm->id, 'chapterid' => $chapter->id]);
            $title = $chapter->title !== '' ? $chapter->title : $cm->get_formatted_name();
            self::add_item($items, 'book', $cm, $cm->instance, $title, self::plain($chapter->content), $url);
        }
    }

    /**
     * mod_wiki — one item per page across all of the module's subwikis, using the cached
     * rendered content rather than re-rendering wiki markup ourselves.
     *
     * @param \stdClass[] $items Accumulator array, passed by reference.
     * @param \cm_info    $cm    The course module being harvested.
     */
    private static function add_wiki(array &$items, \cm_info $cm): void {
        global $DB;
        $sql = "SELECT p.id, p.title, p.cachedcontent
                  FROM {wiki_pages} p
                  JOIN {wiki_subwikis} s ON s.id = p.subwikiid
                 WHERE s.wikiid = :wikiid";
        $pages = $DB->get_records_sql($sql, ['wikiid' => $cm->instance]);
        foreach ($pages as $page) {
            $url = new \moodle_url('/mod/wiki/view.php', ['pageid' => $page->id]);
            self::add_item($items, 'wiki', $cm, $cm->instance, $page->title, self::plain($page->cachedcontent), $url);
        }
    }

    /**
     * File-based modules (mod_resource, mod_folder) — extract every stored file via file_extractor.
     *
     * @param \stdClass[] $items     Accumulator array, passed by reference.
     * @param \cm_info    $cm        The course module being harvested.
     * @param string      $component File storage component (e.g. 'mod_resource').
     * @param string      $filearea  File storage area name.
     */
    private static function add_files(array &$items, \cm_info $cm, string $component, string $filearea): void {
        $fs = get_file_storage();
        $files = $fs->get_area_files($cm->context->id, $component, $filearea, false, 'filepath, filename', false);

        foreach ($files as $file) {
            $ext = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
            $tmp = $file->copy_content_to_temp();
            $text = file_extractor::extract($tmp, $ext);
            @unlink($tmp);

            self::add_item($items, 'file', $cm, $cm->instance, $file->get_filename(), $text, $cm->url);
        }
    }

    /**
     * mod_forum — one item per post (subject + message), excluding nothing since forum
     * posts are public course discussion, not personal data.
     *
     * @param \stdClass[] $items Accumulator array, passed by reference.
     * @param \cm_info    $cm    The course module being harvested.
     */
    private static function add_forum(array &$items, \cm_info $cm): void {
        global $DB;
        $sql = "SELECT fp.id, fp.subject, fp.message, fd.id AS discussionid
                  FROM {forum_posts} fp
                  JOIN {forum_discussions} fd ON fd.id = fp.discussion
                 WHERE fd.forum = :forumid";
        $posts = $DB->get_records_sql($sql, ['forumid' => $cm->instance]);
        foreach ($posts as $post) {
            $url = new \moodle_url('/mod/forum/discuss.php', ['d' => $post->discussionid], 'p' . $post->id);
            $title = $post->subject !== '' ? $post->subject : $cm->get_formatted_name();
            self::add_item($items, 'forum_post', $cm, $cm->instance, $title, self::plain($post->message), $url);
        }
    }

    /**
     * mod_assign — description ("intro") only, never student submissions.
     *
     * @param \stdClass[] $items Accumulator array, passed by reference.
     * @param \cm_info    $cm    The course module being harvested.
     */
    private static function add_assign(array &$items, \cm_info $cm): void {
        global $DB;
        $assign = $DB->get_record('assign', ['id' => $cm->instance], 'id, intro', IGNORE_MISSING);
        if (!$assign) {
            return;
        }
        self::add_item($items, 'assign', $cm, $cm->instance, $cm->get_formatted_name(), self::plain($assign->intro), $cm->url);
    }

    /**
     * mod_quiz — question text + general feedback for each fixed (non-random) slot,
     * never student attempts/answers/grades.
     *
     * @param \stdClass[] $items Accumulator array, passed by reference.
     * @param \cm_info    $cm    The course module being harvested.
     */
    private static function add_quiz(array &$items, \cm_info $cm): void {
        global $DB;

        $sql = "SELECT slot.slot AS slotnumber, q.id AS questionid, q.name, q.questiontext, q.generalfeedback
                  FROM {quiz_slots} slot
                  JOIN {question_references} qr ON qr.usingcontextid = :quizcontextid
                                                AND qr.component = 'mod_quiz'
                                                AND qr.questionarea = 'slot'
                                                AND qr.itemid = slot.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                  JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                                              AND qv.version = (
                                                  SELECT MAX(v2.version)
                                                    FROM {question_versions} v2
                                                   WHERE v2.questionbankentryid = qbe.id
                                                     AND v2.status = 'ready'
                                              )
                  JOIN {question} q ON q.id = qv.questionid
                 WHERE slot.quizid = :quizid
              ORDER BY slot.slot";

        $questions = $DB->get_records_sql($sql, [
            'quizcontextid' => $cm->context->id,
            'quizid'        => $cm->instance,
        ]);

        foreach ($questions as $question) {
            $text = self::plain($question->questiontext) . "\n" . self::plain($question->generalfeedback);
            $url = new \moodle_url('/mod/quiz/view.php', ['id' => $cm->id]);
            self::add_item($items, 'quiz_question', $cm, $cm->instance, $question->name, $text, $url);
        }
    }

    /**
     * mod_glossary — one item per entry (concept + definition).
     *
     * @param \stdClass[] $items Accumulator array, passed by reference.
     * @param \cm_info    $cm    The course module being harvested.
     */
    private static function add_glossary(array &$items, \cm_info $cm): void {
        global $DB;
        $entries = $DB->get_records('glossary_entries', ['glossaryid' => $cm->instance], '', 'id, concept, definition');
        foreach ($entries as $entry) {
            $url = new \moodle_url('/mod/glossary/showentry.php', ['eid' => $entry->id]);
            self::add_item($items, 'glossary', $cm, $cm->instance, $entry->concept, self::plain($entry->definition), $url);
        }
    }

    /**
     * mod_data — one item per record, concatenating every field's stored content with its field label.
     *
     * @param \stdClass[] $items Accumulator array, passed by reference.
     * @param \cm_info    $cm    The course module being harvested.
     */
    private static function add_data(array &$items, \cm_info $cm): void {
        global $DB;
        $sql = "SELECT dc.id, dc.recordid, df.name AS fieldname, dc.content
                  FROM {data_content} dc
                  JOIN {data_fields} df ON df.id = dc.fieldid
                  JOIN {data_records} dr ON dr.id = dc.recordid
                 WHERE dr.dataid = :dataid
              ORDER BY dc.recordid";
        $rows = $DB->get_records_sql($sql, ['dataid' => $cm->instance]);

        $byrecord = [];
        foreach ($rows as $row) {
            $byrecord[$row->recordid][] = $row->fieldname . ': ' . self::plain($row->content);
        }

        foreach ($byrecord as $recordid => $lines) {
            $url = new \moodle_url('/mod/data/view.php', ['d' => $cm->instance, 'rid' => $recordid]);
            self::add_item($items, 'data', $cm, $cm->instance, $cm->get_formatted_name(), implode("\n", $lines), $url);
        }
    }

    /**
     * mod_lesson — one item per page.
     *
     * @param \stdClass[] $items Accumulator array, passed by reference.
     * @param \cm_info    $cm    The course module being harvested.
     */
    private static function add_lesson(array &$items, \cm_info $cm): void {
        global $DB;
        $pages = $DB->get_records('lesson_pages', ['lessonid' => $cm->instance], '', 'id, title, contents');
        foreach ($pages as $page) {
            $url = new \moodle_url('/mod/lesson/view.php', ['id' => $cm->id, 'pageid' => $page->id]);
            self::add_item($items, 'lesson', $cm, $cm->instance, $page->title, self::plain($page->contents), $url);
        }
    }

    /**
     * Modules where only the intro/description is extracted (SCORM, H5P) — see class docblock.
     *
     * @param \stdClass[] $items Accumulator array, passed by reference.
     * @param \cm_info    $cm    The course module being harvested.
     * @param string      $table The activity's main database table (e.g. 'scorm').
     */
    private static function add_intro_only(array &$items, \cm_info $cm, string $table): void {
        global $DB;
        $record = $DB->get_record($table, ['id' => $cm->instance], 'id, intro', IGNORE_MISSING);
        if (!$record) {
            return;
        }
        self::add_item($items, $table, $cm, $cm->instance, $cm->get_formatted_name(), self::plain($record->intro), $cm->url);
    }
}

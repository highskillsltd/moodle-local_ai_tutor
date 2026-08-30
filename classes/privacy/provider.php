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

namespace local_ai_tutor\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy API provider for the AI Tutor plugin.
 *
 * Turns are stored per-user-per-course (local_ai_tutor_turns), so data
 * lives in course contexts, not user contexts.
 *
 * @package    local_ai_tutor
 * @copyright  2026 Highskills and more <info@highskills.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the personal data stored and disclosed by this plugin.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_ai_tutor_turns',
            [
                'userid'              => 'privacy:metadata:local_ai_tutor_turns:userid',
                'sessionid'           => 'privacy:metadata:local_ai_tutor_turns:sessionid',
                'question'            => 'privacy:metadata:local_ai_tutor_turns:question',
                'in_scope'            => 'privacy:metadata:local_ai_tutor_turns:in_scope',
                'stuck'               => 'privacy:metadata:local_ai_tutor_turns:stuck',
                'primary_citation_id' => 'privacy:metadata:local_ai_tutor_turns:primary_citation_id',
                'timecreated'         => 'privacy:metadata:local_ai_tutor_turns:timecreated',
            ],
            'privacy:metadata:local_ai_tutor_turns'
        );

        $collection->add_external_location_link(
            'foundry',
            [
                'session_id'       => 'privacy:metadata:foundry:session_id',
                'course_lang'      => 'privacy:metadata:foundry:course_lang',
                'question'         => 'privacy:metadata:foundry:question',
                'recent_questions' => 'privacy:metadata:foundry:recent_questions',
                'content'          => 'privacy:metadata:foundry:content',
            ],
            'privacy:metadata:foundry'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain personal data for the given user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the relevant course contexts.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id
                  FROM {local_ai_tutor_turns} t
                  JOIN {context} ctx ON ctx.instanceid = t.courseid AND ctx.contextlevel = :contextcourse
                 WHERE t.userid = :userid";

        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, ['contextcourse' => CONTEXT_COURSE, 'userid' => $userid]);
        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }

        $sql = "SELECT userid FROM {local_ai_tutor_turns} WHERE courseid = :courseid";
        $userlist->add_from_sql('userid', $sql, ['courseid' => $context->instanceid]);
    }

    /**
     * Export all user data for the approved contextlist.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_COURSE) {
                continue;
            }

            $turns = $DB->get_records('local_ai_tutor_turns', [
                'courseid' => $context->instanceid,
                'userid'   => $user->id,
            ]);
            if (empty($turns)) {
                continue;
            }

            $data = [];
            foreach ($turns as $turn) {
                $data[] = (object) [
                    'question'            => $turn->question,
                    'in_scope'            => (bool) $turn->in_scope,
                    'stuck'               => (bool) $turn->stuck,
                    'primary_citation_id' => $turn->primary_citation_id,
                    'timecreated'         => transform::datetime($turn->timecreated),
                ];
            }

            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_ai_tutor')],
                (object) ['turns' => $data]
            );
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }

        $DB->delete_records('local_ai_tutor_turns', ['courseid' => $context->instanceid]);
    }

    /**
     * Delete all user data for the approved contextlist.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_COURSE) {
                continue;
            }

            $DB->delete_records('local_ai_tutor_turns', [
                'courseid' => $context->instanceid,
                'userid'   => $user->id,
            ]);
        }
    }

    /**
     * Delete multiple users' data within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }

        foreach ($userlist->get_userids() as $userid) {
            $DB->delete_records('local_ai_tutor_turns', [
                'courseid' => $context->instanceid,
                'userid'   => $userid,
            ]);
        }
    }
}

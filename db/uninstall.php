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
 * Uninstall hook for local_ai_tutor.
 *
 * Moodle's generic uninstall only drops install.xml tables, capabilities,
 * and config — it doesn't know about the course custom field category/field
 * this plugin creates programmatically in db/install.php, so we clean that
 * up explicitly here.
 *
 * @package   local_ai_tutor
 * @copyright 2026 Highskills and more <info@highskills.co.il>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Called by Moodle before the plugin's own tables are dropped on uninstall.
 */
function xmldb_local_ai_tutor_uninstall() {
    global $DB;

    $categories = $DB->get_records('customfield_category', ['component' => 'local_ai_tutor']);
    foreach ($categories as $category) {
        $controller = \core_customfield\category_controller::create($category->id);
        \core_customfield\api::delete_category($controller);
    }

    return true;
}

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
 * FredKI The Agent: provider.php
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_fredkitheagent\privacy;

use core_privacy\local\metadata\collection;

/**
 * Privacy provider for block_fredkitheagent.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Get metadata.
     *
     * @param collection $collection The privacy metadata collection.
     * @return collection The result.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('fredkitheagent', [
            'course' => 'privacy:metadata:fredkitheagent:course',
            'knowledgeid' => 'privacy:metadata:fredkitheagent:knowledgeid',
            'agentid' => 'privacy:metadata:fredkitheagent:agentid',
            'fredkichaturl' => 'privacy:metadata:fredkitheagent:chaturl',
            'lasterror' => 'privacy:metadata:fredkitheagent:lasterror',
        ], 'privacy:metadata:fredkitheagent');
        $collection->add_database_table('fredkitheagent_map', [
            'filename' => 'privacy:metadata:map:filename',
            'fredki_document_id' => 'privacy:metadata:map:documentid',
            'lasterror' => 'privacy:metadata:map:lasterror',
        ], 'privacy:metadata:map');
        $collection->add_database_table('fredkitheagent_design', [
            'courseid' => 'privacy:metadata:design:course',
            'agentid' => 'privacy:metadata:design:agent',
            'welcometext' => 'privacy:metadata:design:text',
            'footertext' => 'privacy:metadata:design:text',
        ], 'privacy:metadata:design');
        $collection->add_external_location_link('fredki', [
            'coursecontent' => 'privacy:metadata:fredki:coursecontent',
            'filename' => 'privacy:metadata:fredki:filename',
            'siteurl' => 'privacy:metadata:fredki:siteurl',
            'contactemail' => 'privacy:metadata:fredki:contactemail',
            'installationid' => 'privacy:metadata:fredki:installationid',
            'chatprompt' => 'privacy:metadata:fredki:chatprompt',
            'chathistory' => 'privacy:metadata:fredki:chathistory',
            'chatresponse' => 'privacy:metadata:fredki:chatresponse',
            'courseid' => 'privacy:metadata:fredki:courseid',
        ], 'privacy:metadata:fredki');
        return $collection;
    }

    /**
     * Get contexts for userid.
     *
     * @param int $userid The Moodle user id.
     * @return \core_privacy\local\request\contextlist The result.
     */
    public static function get_contexts_for_userid(int $userid): \core_privacy\local\request\contextlist {
        return new \core_privacy\local\request\contextlist();
    }

    /**
     * Get users in context.
     *
     * @param \core_privacy\local\request\userlist $userlist The user list.
     */
    public static function get_users_in_context(\core_privacy\local\request\userlist $userlist): void {
        // Course-level records are not linked to individual Moodle users.
    }

    /**
     * Export user data.
     *
     * @param \core_privacy\local\request\approved_contextlist $contextlist The approved context list.
     */
    public static function export_user_data(\core_privacy\local\request\approved_contextlist $contextlist): void {
        // No records are linked to an individual Moodle user.
    }

    /**
     * Delete data for all users in context.
     *
     * @param \context $context The context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        // Course-level data is managed when the block/course integration is removed.
    }

    /**
     * Delete data for user.
     *
     * @param \core_privacy\local\request\approved_contextlist $contextlist The approved context list.
     */
    public static function delete_data_for_user(\core_privacy\local\request\approved_contextlist $contextlist): void {
        // No records are linked to an individual Moodle user.
    }

    /**
     * Delete data for users.
     *
     * @param \core_privacy\local\request\approved_userlist $userlist The user list.
     */
    public static function delete_data_for_users(\core_privacy\local\request\approved_userlist $userlist): void {
        // No records are linked to an individual Moodle user.
    }
}

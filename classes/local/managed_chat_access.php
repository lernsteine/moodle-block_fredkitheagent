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
 * Protected Moodle-only chat access. No public agent link or token.
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_fredkitheagent\local;
/**
 * Managed chat access class.
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class managed_chat_access {
    /**
     * Course.
     *
     * @param int $courseid The course id.
     * @return \stdClass The result.
     */
    public static function course(int $courseid): \stdClass {
        global $DB;
        if ($courseid <= 1) {
            throw new \invalid_parameter_exception('Invalid course');
        }
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        // Note: require_login() already limits access to enrolled users (and users who may view the
        // course without participation). Do NOT demand moodle/course:view here: that capability
        // is "view courses without participation" and students never hold it.
        require_login($course);
        if (isguestuser()) {
            throw new \moodle_exception('nopermissions', 'error', '', get_string('pluginname', 'block_fredkitheagent'));
        }
        $context = \context_course::instance($courseid);
        if (
            !$DB->record_exists('block_instances', [
            'blockname' => 'fredkitheagent', 'parentcontextid' => $context->id,
            ])
        ) {
            throw new \moodle_exception('nopermissions', 'error', '', get_string('pluginname', 'block_fredkitheagent'));
        }
        $rec = course_agent_manager::get_record($courseid);
        if (!import_completion::chat_ready($rec)) {
            throw new \moodle_exception('nopermissions', 'error');
        }
        // The teacher's checkbox controls learner access on the server, not only the UI.
        if (empty($rec->agentcoursevisible) && !has_capability('moodle/course:update', $context)) {
            throw new \moodle_exception('nopermissions', 'error');
        }
        managed_gateway::require_active();
        return $course;
    }
}

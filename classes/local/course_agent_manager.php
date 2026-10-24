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
 * FredKI The Agent: course_agent_manager.php
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_fredkitheagent\local;

/**
 * Course agent manager class.
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_agent_manager {
    /**
     * Get record.
     *
     * @param int $courseid The course id.
     * @return ?\stdClass The result.
     */
    public static function get_record(int $courseid): ?\stdClass {
        global $DB;
        if ($courseid <= 0 || !$DB->get_manager()->table_exists('fredkitheagent')) {
            return null;
        }
        return $DB->get_record('fredkitheagent', ['course' => $courseid], '*', IGNORE_MISSING) ?: null;
    }


    /**
     * Clear error.
     *
     * @param int $courseid The course id.
     */
    public static function clear_error(int $courseid): void {
        global $DB;
        if ($courseid <= 0 || !$DB->get_manager()->table_exists('fredkitheagent')) {
            return;
        }
        $rec = self::get_record($courseid);
        if (!$rec || empty($rec->lasterror)) {
            return;
        }
        $rec->lasterror = null;
        if ($rec->status === 'error') {
            $rec->status = (!empty($rec->agentid)) ? 'ready' : (!empty($rec->knowledgeid) ? 'knowledge_created' : 'new');
        }
        $rec->timemodified = time();
        $DB->update_record('fredkitheagent', $rec);
    }

    /**
     * Ensure record.
     *
     * @param \stdClass $course The course record.
     * @return \stdClass The result.
     */
    public static function ensure_record(\stdClass $course): \stdClass {
        global $DB;
        $now = time();
        $rec = self::get_record((int)$course->id);
        if ($rec) {
            return $rec;
        }
        $rec = (object)[
            'course' => (int)$course->id,
            'name' => 'FredKI The Agent',
            'knowledgeid' => '',
            'knowledgebasename' => '',
            'workspace' => get_config('block_fredkitheagent', 'workspace') ?: 'default',
            'agentid' => '',
            'fredkiembedcode' => '',
            'fredkichaturl' => '',
            'agentcoursevisible' => 0,
            'status' => 'new',
            'documentcount' => 0,
            'tagcount' => 0,
            'lasterror' => null,
            'timecreated' => $now,
            'timemodified' => $now,
            'timesynced' => null,
            'managedtargetid' => null,
            'verifiedat' => null,
        ];
        $rec->id = $DB->insert_record('fredkitheagent', $rec);
        return $rec;
    }
}

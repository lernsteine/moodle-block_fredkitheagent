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
 * FredKI The Agent: course_binding_guard.php
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_fredkitheagent\local;

/**
 * Protects one-to-one Moodle course -> FredKI knowledge/agent bindings.
 *
 * This is a second line of defence. The central gateway MUST independently
 * enforce (installation_id, course_id) uniqueness and clone an agent per course.
 */
final class course_binding_guard {
    /**
     * Stable identity for the central server. Never use hostname alone.
     *
     * @param string $installationid The installationid.
     * @param int $courseid The courseid.
     * @return string The result.
     */
    public static function course_key(string $installationid, int $courseid): string {
        if ($courseid < 2 || $installationid === '') {
            throw new \invalid_parameter_exception('Invalid course identity.');
        }
        return hash('sha256', $installationid . ':' . $courseid);
    }

    /**
     * Pure validation, useful for tests without a Moodle database.
     *
     * @param int $courseid The courseid.
     * @param string $knowledgeid The knowledgeid.
     * @param string $agentid The agentid.
     * @param array $others The others.
     */
    public static function assert_distinct(int $courseid, string $knowledgeid, string $agentid, array $others): void {
        if ($courseid < 2 || trim($knowledgeid) === '' || trim($agentid) === '') {
            throw new \UnexpectedValueException('Missing course knowledge or agent binding.');
        }
        foreach ($others as $binding) {
            $othercourse = (int)($binding['course'] ?? 0);
            if ($othercourse === $courseid) {
                continue;
            }
            if (($binding['knowledgeid'] ?? '') === $knowledgeid || ($binding['agentid'] ?? '') === $agentid) {
                throw new managed_api_diagnostic_exception(
                    'DIAG-COURSE-ISOLATION: FredKI assigned a knowledge base or agent to another Moodle course. ' .
                    'Provisioning stopped; please check the central course mapping.'
                );
            }
        }
    }

    /**
     * Verify a knowledge base is not assigned to any other course BEFORE uploading any material.
     *
     * @param int $courseid The courseid.
     * @param string $knowledgeid The knowledgeid.
     */
    public static function assert_knowledge_available(int $courseid, string $knowledgeid): void {
        global $DB;
        if ($courseid < 2 || trim($knowledgeid) === '') {
            throw new \UnexpectedValueException('Missing course knowledge ID.');
        }
        if (!$DB->get_manager()->table_exists(new \xmldb_table('block_fredkitheagent'))) {
            return;
        }
        if (
            $DB->record_exists_select(
                'block_fredkitheagent',
                'course <> :course AND knowledgeid = :knowledgeid',
                ['course' => $courseid, 'knowledgeid' => $knowledgeid]
            )
        ) {
            throw new managed_api_diagnostic_exception(
                'DIAG-COURSE-KNOWLEDGE-ISOLATION: The course knowledge base already belongs to another Moodle course. ' .
                'No course files were uploaded.'
            );
        }
    }

    /**
     * Check existing assignments before accepting a remote binding.
     * Only local Moodle records are visible here; the gateway must also check
     * associations across all installations and customers on the server.
     *
     * @param int $courseid The courseid.
     * @param string $knowledgeid The knowledgeid.
     * @param string $agentid The agentid.
     */
    public static function assert_available(int $courseid, string $knowledgeid, string $agentid): void {
        global $DB;
        if (!$DB->get_manager()->table_exists(new \xmldb_table('block_fredkitheagent'))) {
            return;
        }
        $others = $DB->get_records_select(
            'block_fredkitheagent',
            'course <> :course AND (knowledgeid = :knowledgeid OR agentid = :agentid)',
            ['course' => $courseid, 'knowledgeid' => $knowledgeid, 'agentid' => $agentid],
            '',
            'course, knowledgeid, agentid'
        );
        $values = array_map(static function ($row): array {
            return [
                'course' => (int)$row->course,
                'knowledgeid' => (string)$row->knowledgeid,
                'agentid' => (string)$row->agentid,
            ];
        }, array_values($others));
        self::assert_distinct($courseid, $knowledgeid, $agentid, $values);
    }

    /**
     * Ensure an existing assigned ID is never silently changed by an API reply.
     *
     * @param \stdClass|null $current The current.
     * @param string $knowledgeid The knowledgeid.
     * @param string $agentid The agentid.
     */
    public static function assert_stable(?\stdClass $current, string $knowledgeid, string $agentid): void {
        if (!$current) {
            return;
        }
        $prioragent = trim((string)($current->agentid ?? ''));
        $priorknowledge = trim((string)($current->knowledgeid ?? ''));
        if (
            ($prioragent !== '' && $prioragent !== $agentid) ||
                ($priorknowledge !== '' && $priorknowledge !== $knowledgeid)
        ) {
            throw new managed_api_diagnostic_exception(
                'DIAG-COURSE-BINDING-CHANGED: The previous course mapping changed unexpectedly on the licence server.'
            );
        }
    }
}

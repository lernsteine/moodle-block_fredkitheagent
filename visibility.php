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
 * Toggles the visibility of the FredKI course chat for learners (one setting per course).
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false]);
        exit;
    }
    require_sesskey();
    $courseid = required_param('courseid', PARAM_INT);
    $visible = required_param('visible', PARAM_BOOL) ? 1 : 0;
    $course = get_course($courseid);
    require_login($course);
    $context = context_course::instance($courseid);
    require_capability('moodle/course:update', $context);

    if (!\block_fredkitheagent\local\managed_gateway::is_managed()) {
        throw new \moodle_exception('nopermissions', 'error');
    }
    $record = \block_fredkitheagent\local\course_agent_manager::get_record($courseid);
    if (!$record || !\block_fredkitheagent\local\import_completion::chat_ready($record)) {
        throw new \moodle_exception('nopermissions', 'error');
    }

    // Update a single existing course mapping; never create or modify the agent itself.
    $DB->set_field('block_fredkitheagent', 'agentcoursevisible', $visible, ['id' => $record->id, 'course' => $courseid]);
    echo json_encode(['ok' => true, 'visible' => (bool)$visible]);
} catch (\Throwable $e) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
}

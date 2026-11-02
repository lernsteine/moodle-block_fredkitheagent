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
 * FredKI The Agent: change the agent role of one course (Berater, Sparringspartner, ...).
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
$courseid = required_param('courseid', PARAM_INT);
$role = required_param('agentrole', PARAM_ALPHANUMEXT);
require_sesskey();
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:update', $context);
$pn = 'block_fredkitheagent';
$back = new moodle_url('/course/view.php', ['id' => $courseid]);
if ($role === '') {
    redirect($back);
}
$rec = \block_fredkitheagent\local\course_agent_manager::get_record($courseid);
if (!$rec) {
    throw new moodle_exception('nopermissions', 'error');
}
if ((string)($rec->agentrole ?? '') === $role) {
    redirect($back);
}
$roles = \block_fredkitheagent\local\managed_gateway::status()['agent_roles'] ?? [];
$name = '';
foreach ($roles as $candidate) {
    if ($candidate['id'] === $role) {
        $name = $candidate['name'];
    }
}
if ($name === '') {
    redirect($back, get_string('roleunknown', $pn), null, \core\output\notification::NOTIFY_ERROR);
}
\core_php_time_limit::raise(180);
try {
    $result = \block_fredkitheagent\local\managed_gateway::set_course_role($courseid, $role);
} catch (\Throwable $e) {
    redirect($back, \block_fredkitheagent\local\managed_gateway::safe_error($e), null, \core\output\notification::NOTIFY_ERROR);
}
// The licence server replaced the course agent; the knowledge base and the transferred material stay.
$rec->agentrole = $role;
if (!empty($result['agent_id'])) {
    $rec->agentid = (string)$result['agent_id'];
}
$rec->timemodified = time();
$DB->update_record('block_fredkitheagent', $rec);
redirect($back, get_string('rolechanged', $pn, $name), null, \core\output\notification::NOTIFY_SUCCESS);

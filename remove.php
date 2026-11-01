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
 * FredKI The Agent: remove the course agent of one course (agent and knowledge base in FredKI are deleted).
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/blocklib.php');
$courseid = required_param('courseid', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:update', $context);
$pn = 'block_fredkitheagent';
$url = new moodle_url('/blocks/fredkitheagent/remove.php', ['courseid' => $courseid]);
$back = new moodle_url('/course/view.php', ['id' => $courseid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('removeagent', $pn));
$PAGE->set_heading(format_string($course->fullname));

if ($confirm && confirm_sesskey()) {
    try {
        \block_fredkitheagent\local\managed_gateway::remove_course($courseid);
    } catch (\Throwable $e) {
        redirect($back, \block_fredkitheagent\local\managed_gateway::safe_error($e), null, \core\output\notification::NOTIFY_ERROR);
    }
    // FredKI resources are gone: forget them here, hide the chat and take the block out of the course.
    \block_fredkitheagent\local\course_agent_manager::reset_binding($courseid);
    $DB->set_field('block_fredkitheagent', 'agentcoursevisible', 0, ['course' => $courseid]);
    $instances = $DB->get_records('block_instances', ['blockname' => 'fredkitheagent', 'parentcontextid' => $context->id]);
    foreach ($instances as $instance) {
        blocks_delete_instance($instance);
    }
    redirect($back, get_string('removedone', $pn), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->confirm(
    get_string('removeconfirm', $pn, format_string($course->fullname)),
    new moodle_url($url, ['confirm' => 1, 'sesskey' => sesskey()]),
    $back
);
echo $OUTPUT->footer();

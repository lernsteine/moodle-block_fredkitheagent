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
 * Secure Moodle pluginfile delivery for administrator-uploaded FredKI chat logos.
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Block fredkitheagent pluginfile.
 *
 * @param mixed $course The course record.
 * @param mixed $cm The course module.
 * @param mixed $context The context.
 * @param mixed $filearea The file area.
 * @param mixed $args The file arguments.
 * @param mixed $forcedownload Whether to force a download.
 * @param array $options Additional options.
 */
function block_fredkitheagent_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($filearea === 'courselogo' && $context->contextlevel === CONTEXT_COURSE) {
        $courseid = (int)$context->instanceid;
        $course = get_course($courseid);
        require_login($course);
        if (isguestuser()) {
            return false;
        }
        $mapping = \block_fredkitheagent\local\course_agent_manager::get_record($courseid);
        if (!$mapping || !\block_fredkitheagent\local\course_design::get($courseid)) {
            return false;
        }
        if (empty($mapping->agentcoursevisible) && !has_capability('moodle/course:update', $context)) {
            return false;
        }
        $itemid = (int)array_shift($args);
        if ($itemid !== 0 || empty($args)) {
            return false;
        }
        $filename = array_pop($args);
        $filepath = '/' . ($args ? implode('/', $args) . '/' : '');
        $file = get_file_storage()->get_file(
            $context->id,
            'block_fredkitheagent',
            'courselogo',
            0,
            $filepath,
            $filename
        );
        if (
            !$file || $file->is_directory() || !in_array(
                $file->get_mimetype(),
                ['image/png', 'image/jpeg', 'image/webp'],
                true
            )
        ) {
            return false;
        }
        return send_stored_file($file, 0, 0, false, $options);
    }
    if ($filearea !== 'chatlogo' || $context->contextlevel !== CONTEXT_SYSTEM) {
        return false;
    }
    require_login();
    $itemid = (int)array_shift($args);
    if ($itemid !== 0 || empty($args)) {
        return false;
    }
    $filename = array_pop($args);
    $filepath = '/' . ($args ? implode('/', $args) . '/' : '');
    $file = get_file_storage()->get_file(
        $context->id,
        'block_fredkitheagent',
        'chatlogo',
        $itemid,
        $filepath,
        $filename
    );
    if (!$file || $file->is_directory()) {
        return false;
    }
    return send_stored_file($file, 0, 0, false, $options);
}

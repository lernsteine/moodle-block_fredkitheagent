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
 * Tests for disconnecting and resetting a course binding.
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_fredkitheagent;

use block_fredkitheagent\local\course_agent_manager;
use block_fredkitheagent\local\import_completion;

/**
 * Course agent manager test class.
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \block_fredkitheagent\local\course_agent_manager
 */
final class course_agent_manager_test extends \advanced_testcase {
    /**
     * Create a course with a ready binding, two mapped documents and a design.
     *
     * @return array{0:\stdClass,1:\stdClass} Course and record.
     */
    private function ready_course(): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $rec = course_agent_manager::ensure_record($course);
        $rec->knowledgeid = 'knowledge-old-1';
        $rec->agentid = 'agent-old-1';
        $rec->managedtargetid = 'knowledge-old-1';
        $rec->status = 'ready';
        $rec->documentcount = 2;
        $rec->timesynced = time();
        $rec->verifiedat = time();
        $rec->agentcoursevisible = 1;
        $DB->update_record('block_fredkitheagent', $rec);
        foreach (['a', 'b'] as $suffix) {
            $DB->insert_record('block_fredkitheagent_map', (object)[
                'cmid' => 0, 'activityid' => $rec->id, 'pathnamehash' => sha1($suffix),
                'filename' => $suffix . '.pdf', 'contenthash' => sha1($suffix . 'c'), 'filesize' => 1,
                'metahash' => sha1($suffix . 'm'), 'fredki_document_id' => 'doc-' . $suffix,
                'status' => 'synced', 'timecreated' => time(), 'timemodified' => time(),
            ]);
        }
        $DB->insert_record('block_fredkitheagent_design', (object)[
            'courseid' => $course->id, 'agentid' => 'agent-old-1', 'headercolor' => '#112233',
            'timemodified' => time(),
        ]);
        return [$course, course_agent_manager::get_record((int)$course->id)];
    }

    /**
     * A detached course is no longer chat-ready but keeps its IDs.
     */
    public function test_mark_detached_disables_chat_and_keeps_ids(): void {
        $this->resetAfterTest();
        [$course, $rec] = $this->ready_course();
        $this->assertTrue(import_completion::chat_ready($rec));

        course_agent_manager::mark_detached((int)$course->id);

        $after = course_agent_manager::get_record((int)$course->id);
        $this->assertSame('detached', $after->status);
        $this->assertSame('agent-old-1', $after->agentid);
        $this->assertSame('knowledge-old-1', $after->knowledgeid);
        $this->assertFalse(import_completion::chat_ready($after));
    }

    /**
     * Resetting removes IDs and document mapping, but keeps design and visibility.
     */
    public function test_reset_binding_clears_ids_and_mapping(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $rec] = $this->ready_course();
        course_agent_manager::mark_detached((int)$course->id);

        $this->assertTrue(course_agent_manager::reset_binding((int)$course->id));

        $after = course_agent_manager::get_record((int)$course->id);
        $this->assertSame('new', $after->status);
        $this->assertSame('', (string)$after->agentid);
        $this->assertSame('', (string)$after->knowledgeid);
        $this->assertNull($after->managedtargetid);
        $this->assertSame(0, (int)$after->documentcount);
        $this->assertSame(1, (int)$after->agentcoursevisible);
        $this->assertSame(0, $DB->count_records('block_fredkitheagent_map', ['activityid' => $rec->id]));
        $this->assertSame(1, $DB->count_records('block_fredkitheagent_design', ['courseid' => $course->id]));
    }

    /**
     * Resetting other courses never touches this course.
     */
    public function test_reset_binding_is_limited_to_one_course(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $rec] = $this->ready_course();
        [$other] = $this->ready_course();

        course_agent_manager::reset_binding((int)$other->id);

        $this->assertSame('agent-old-1', course_agent_manager::get_record((int)$course->id)->agentid);
        $this->assertSame(2, $DB->count_records('block_fredkitheagent_map', ['activityid' => $rec->id]));
        $this->assertFalse(course_agent_manager::reset_binding(999999));
    }
}

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
 * Isolated contract tests; run under Moodle PHPUnit after installation.
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_fredkitheagent;

/**
 * Course binding guard test class.
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_binding_guard_test extends \advanced_testcase {
    /**
     * Test course identity changes with course id.
     */
    public function test_course_identity_changes_with_course_id(): void {
        $this->assertNotEquals(
            \block_fredkitheagent\local\course_binding_guard::course_key('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 3),
            \block_fredkitheagent\local\course_binding_guard::course_key('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 4)
        );
    }

    /**
     * Test two courses cannot share agent.
     */
    public function test_two_courses_cannot_share_agent(): void {
        // The diagnostic exception is currently defined in managed_gateway.php.
        \block_fredkitheagent\local\managed_gateway::is_managed();
        $this->expectException(\block_fredkitheagent\local\managed_api_diagnostic_exception::class);
        \block_fredkitheagent\local\course_binding_guard::assert_distinct(8, 'knowledge-eight', 'agent-shared', [
            ['course' => 7, 'knowledgeid' => 'knowledge-seven', 'agentid' => 'agent-shared'],
        ]);
    }

    /**
     * Test two courses cannot share knowledge base.
     */
    public function test_two_courses_cannot_share_knowledge_base(): void {
        \block_fredkitheagent\local\managed_gateway::is_managed();
        $this->expectException(\block_fredkitheagent\local\managed_api_diagnostic_exception::class);
        \block_fredkitheagent\local\course_binding_guard::assert_distinct(8, 'knowledge-shared', 'agent-eight', [
            ['course' => 7, 'knowledgeid' => 'knowledge-shared', 'agentid' => 'agent-seven'],
        ]);
    }

    /**
     * Test same course binding is idempotent.
     */
    public function test_same_course_binding_is_idempotent(): void {
        \block_fredkitheagent\local\course_binding_guard::assert_distinct(8, 'knowledge-eight', 'agent-eight', [
            ['course' => 8, 'knowledgeid' => 'knowledge-eight', 'agentid' => 'agent-eight'],
        ]);
        $this->assertTrue(true);
    }

    /**
     * Test existing agent cannot change silently.
     */
    public function test_existing_agent_cannot_change_silently(): void {
        \block_fredkitheagent\local\managed_gateway::is_managed();
        $this->expectException(\block_fredkitheagent\local\managed_api_diagnostic_exception::class);
        $record = (object)['knowledgeid' => 'knowledge-eight', 'agentid' => 'agent-eight'];
        \block_fredkitheagent\local\course_binding_guard::assert_stable($record, 'knowledge-eight', 'agent-nine');
    }
}

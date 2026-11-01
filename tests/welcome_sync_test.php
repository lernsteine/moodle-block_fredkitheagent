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
 * FredKI The Agent: welcome_sync_test.php
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_fredkitheagent;

use block_fredkitheagent\local\welcome_sync;

/**
 * The global welcome text follows the FredKI template agent only until an administrator changes it.
 *
 * @covers \block_fredkitheagent\local\welcome_sync
 */
final class welcome_sync_test extends \basic_testcase {
    /**
     * Empty, default and previously taken-over texts follow the template; own texts do not.
     *
     * @covers \block_fredkitheagent\local\welcome_sync::follows_template
     */
    public function test_follows_template(): void {
        $default = 'I will help you find the right information.';
        $this->assertTrue(welcome_sync::follows_template('', '', $default));
        $this->assertTrue(welcome_sync::follows_template($default, '', $default));
        $this->assertTrue(welcome_sync::follows_template('Hi from the template', 'Hi from the template', $default));
        $this->assertFalse(welcome_sync::follows_template('Our own greeting', 'Hi from the template', $default));
        $this->assertFalse(welcome_sync::follows_template('Our own greeting', '', $default));
    }
}

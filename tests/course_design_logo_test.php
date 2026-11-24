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
 * FredKI The Agent: course_design_logo_test.php
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_fredkitheagent;

use block_fredkitheagent\local\course_design;

/**
 * Shape and size of the chat logo fall back to safe defaults.
 *
 * @covers \block_fredkitheagent\local\course_design
 */
final class course_design_logo_test extends \advanced_testcase {
    /**
     * Without a setting the logo is round and 72 px.
     */
    public function test_defaults(): void {
        $this->resetAfterTest();
        $this->assertSame(['shape' => 'round', 'size' => 72], course_design::logo_appearance());
    }

    /**
     * Allowed values are used as they are.
     */
    public function test_valid_values(): void {
        $this->resetAfterTest();
        set_config('chatlogoshape', 'square', 'block_fredkitheagent');
        set_config('chatlogosize', 120, 'block_fredkitheagent');
        $this->assertSame(['shape' => 'square', 'size' => 120], course_design::logo_appearance());
    }

    /**
     * Unknown values never reach the page.
     */
    public function test_invalid_values_fall_back(): void {
        $this->resetAfterTest();
        set_config('chatlogoshape', 'x" onmouseover="y', 'block_fredkitheagent');
        set_config('chatlogosize', 9999, 'block_fredkitheagent');
        $this->assertSame(['shape' => 'round', 'size' => 72], course_design::logo_appearance());
    }

    /**
     * Without any uploaded logo there is no system logo URL.
     */
    public function test_no_system_logo(): void {
        $this->resetAfterTest();
        $this->assertNull(course_design::system_logo_url());
    }
}

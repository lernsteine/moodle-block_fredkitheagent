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
     * Without any setting the chat keeps its familiar look: round avatar, rounded square button of 72 px.
     */
    public function test_defaults(): void {
        $this->resetAfterTest();
        $a = course_design::logo_appearance();
        $this->assertSame('round', $a['logoshape']);
        $this->assertSame('rounded', $a['buttonshape']);
        $this->assertSame(72, $a['width']);
        $this->assertSame(72, $a['height']);
        $this->assertTrue($a['frame']);
        $this->assertTrue($a['keepratio']);
        $this->assertSame(20, $a['radius']);
    }

    /**
     * Width and height are independent, the shape decides the corner radius.
     */
    public function test_valid_values(): void {
        $this->resetAfterTest();
        set_config('chatlogoshape', 'sharp', 'block_fredkitheagent');
        set_config('chatbuttonshape', 'round', 'block_fredkitheagent');
        set_config('chatbuttonwidth', 144, 'block_fredkitheagent');
        set_config('chatbuttonheight', 96, 'block_fredkitheagent');
        set_config('chatlogoframe', 0, 'block_fredkitheagent');
        set_config('chatbuttonkeepratio', 0, 'block_fredkitheagent');
        $a = course_design::logo_appearance();
        $this->assertSame('sharp', $a['logoshape']);
        $this->assertSame('round', $a['buttonshape']);
        $this->assertSame(144, $a['width']);
        $this->assertSame(96, $a['height']);
        $this->assertFalse($a['frame']);
        $this->assertFalse($a['keepratio']);
        $this->assertSame(48, $a['radius']);
        $this->assertSame(0, $a['padding']);
    }

    /**
     * Sizes are kept within the allowed range.
     */
    public function test_sizes_are_clamped(): void {
        $this->resetAfterTest();
        set_config('chatbuttonwidth', 9999, 'block_fredkitheagent');
        set_config('chatbuttonheight', 5, 'block_fredkitheagent');
        $a = course_design::logo_appearance();
        $this->assertSame(course_design::BUTTON_MAX, $a['width']);
        $this->assertSame(course_design::BUTTON_MIN, $a['height']);
    }

    /**
     * Unknown values never reach the page.
     */
    public function test_invalid_values_fall_back(): void {
        $this->resetAfterTest();
        set_config('chatlogoshape', 'x" onmouseover="y', 'block_fredkitheagent');
        set_config('chatbuttonshape', '<b>', 'block_fredkitheagent');
        set_config('chatbuttonwidth', 'abc', 'block_fredkitheagent');
        $a = course_design::logo_appearance();
        $this->assertSame('round', $a['logoshape']);
        $this->assertSame('rounded', $a['buttonshape']);
        $this->assertSame(72, $a['width']);
    }

    /**
     * Settings saved by version 1.9.2 still work.
     */
    public function test_settings_of_version_192_are_still_read(): void {
        $this->resetAfterTest();
        set_config('chatlogoshape', 'square', 'block_fredkitheagent');
        set_config('chatlogosize', 96, 'block_fredkitheagent');
        $a = course_design::logo_appearance();
        $this->assertSame('rounded', $a['logoshape']);
        $this->assertSame('rounded', $a['buttonshape']);
        $this->assertSame(96, $a['width']);
        $this->assertSame(96, $a['height']);
    }

    /**
     * Without any uploaded logo there is no system logo URL.
     */
    public function test_no_system_logo(): void {
        $this->resetAfterTest();
        $this->assertNull(course_design::system_logo_url());
    }
}

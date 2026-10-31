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
 * FredKI The Agent: version_scheme_test.php
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_fredkitheagent;

use block_fredkitheagent\local\version_scheme;

/**
 * Former 12-digit versions map to the renumbered upgrade steps without skipping or repeating one.
 *
 * @covers \block_fredkitheagent\local\version_scheme
 */
final class version_scheme_test extends \advanced_testcase {
    /**
     * Installed versions map to the last upgrade step they have already run.
     *
     * @covers \block_fredkitheagent\local\version_scheme::map
     */
    public function test_map(): void {
        $this->assertSame(2026060700, version_scheme::map(202601010000));
        $this->assertSame(2026060800, version_scheme::map(202606081487));
        $this->assertSame(2026102201, version_scheme::map(202610222300));
        $this->assertSame(2026102400, version_scheme::map(202610260000));
        $this->assertSame(2026102701, version_scheme::map(202610311800));
    }

    /**
     * Only a 12-digit stored version is converted, and only once.
     *
     * @covers \block_fredkitheagent\local\version_scheme::repair
     */
    public function test_repair(): void {
        $this->resetAfterTest();
        set_config('version', 202610311800, 'block_fredkitheagent');
        $this->assertTrue(version_scheme::repair());
        $this->assertSame('2026102701', (string)get_config('block_fredkitheagent', 'version'));
        $this->assertFalse(version_scheme::repair());
    }

    /**
     * The plugin version has the format YYYYMMDDXX required by the Moodle Plugins directory.
     *
     * @covers \block_fredkitheagent\local\version_scheme
     */
    public function test_version_format(): void {
        global $CFG;
        $plugin = new \stdClass();
        require($CFG->dirroot . '/blocks/fredkitheagent/version.php');
        $this->assertMatchesRegularExpression('/^20\d{8}$/', (string)$plugin->version);
    }
}

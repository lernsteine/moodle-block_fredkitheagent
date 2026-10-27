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
 * FredKI The Agent: activation_state_test.php
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_fredkitheagent;

use block_fredkitheagent\local\activation_state;

/**
 * Package fields from the licence server are parsed strictly.
 *
 * @covers \block_fredkitheagent\local\activation_state
 */
final class activation_state_test extends \basic_testcase {
    /**
     * Valid package data and links to the licence server are accepted.
     *
     * @covers \block_fredkitheagent\local\activation_state::billing_from_server
     */
    public function test_valid_billing_fields(): void {
        $token = str_repeat('ab', 24);
        $id = str_repeat('c', 32);
        $result = activation_state::billing_from_server([
            'plan' => 'Bis 10 Kurse',
            'course_limit' => 10,
            'courses_used' => 3,
            'checkout_url' => 'https://bildungbleibt.de/fredki-hub/buchen.php?i=' . $id . '&t=' . $token,
            'manage_url' => 'https://bildungbleibt.de/fredki-hub/abo.php?i=' . $id . '&t=' . $token,
        ], 'bildungbleibt.de');
        $this->assertSame('Bis 10 Kurse', $result['plan']);
        $this->assertSame(10, $result['course_limit']);
        $this->assertSame(3, $result['courses_used']);
        $this->assertStringStartsWith('https://bildungbleibt.de/fredki-hub/buchen.php?', $result['checkout_url']);
        $this->assertStringStartsWith('https://bildungbleibt.de/fredki-hub/abo.php?', $result['manage_url']);
    }

    /**
     * Links to other hosts, plain http or with script content are dropped.
     *
     * @covers \block_fredkitheagent\local\activation_state::billing_from_server
     */
    public function test_foreign_or_unsafe_links_are_rejected(): void {
        $unsafe = [
            'https://evil.example/fredki-hub/buchen.php?i=1&t=2',
            'http://bildungbleibt.de/fredki-hub/buchen.php?i=1&t=2',
            'https://bildungbleibt.de:8443/fredki-hub/buchen.php?i=1&t=2',
            'https://user@bildungbleibt.de/fredki-hub/buchen.php?i=1&t=2',
            'javascript:alert(1)',
            'https://bildungbleibt.de/fredki-hub/buchen.php?i=1&t="><script>',
            42,
        ];
        foreach ($unsafe as $url) {
            $result = activation_state::billing_from_server(['checkout_url' => $url], 'bildungbleibt.de');
            $this->assertNull($result['checkout_url']);
        }
    }

    /**
     * Older servers without package fields and invalid values yield nulls.
     *
     * @covers \block_fredkitheagent\local\activation_state::billing_from_server
     */
    public function test_missing_or_invalid_values(): void {
        $this->assertSame([
            'plan' => null, 'course_limit' => null, 'courses_used' => null, 'checkout_url' => null, 'manage_url' => null,
        ], activation_state::billing_from_server([], 'bildungbleibt.de'));
        $result = activation_state::billing_from_server(
            ['plan' => '<b>x</b>', 'course_limit' => '10', 'courses_used' => -1],
            'bildungbleibt.de'
        );
        $this->assertNull($result['plan']);
        $this->assertNull($result['course_limit']);
        $this->assertNull($result['courses_used']);
    }

    /**
     * A licensed subscription is usable; a revoked one is not.
     *
     * @covers \block_fredkitheagent\local\activation_state::can_use
     */
    public function test_licensed_and_revoked(): void {
        $now = 1000000;
        $this->assertTrue(activation_state::can_use(['state' => 'licensed', 'lease_until' => $now + 60], $now));
        $this->assertFalse(activation_state::can_use(['state' => 'revoked', 'lease_until' => $now + 60], $now));
    }
}

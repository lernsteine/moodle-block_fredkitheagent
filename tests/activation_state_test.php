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
     * Valid package data is accepted; booking links of older servers are ignored.
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
            'checkout_url' => 'https://fredki-theagent.lernsteine.de/buchen.php?i=' . $id . '&t=' . $token,
            'manage_url' => 'https://fredki-theagent.lernsteine.de/abo.php?i=' . $id . '&t=' . $token,
        ], 'fredki-theagent.lernsteine.de');
        $this->assertSame('Bis 10 Kurse', $result['plan']);
        $this->assertSame(10, $result['course_limit']);
        $this->assertSame(3, $result['courses_used']);
        $this->assertArrayNotHasKey('checkout_url', $result);
        $this->assertArrayNotHasKey('manage_url', $result);
    }

    /**
     * The monthly messages of the free package are read strictly.
     *
     * @covers \block_fredkitheagent\local\activation_state::billing_from_server
     */
    public function test_free_quota(): void {
        $result = activation_state::billing_from_server([
            'plan' => 'Free',
            'course_limit' => 1,
            'free_quota' => ['limit' => 200, 'used' => 12, 'resets_at' => 1796079600],
        ], 'fredki-theagent.lernsteine.de');
        $this->assertSame('Free', $result['plan']);
        $this->assertSame(['limit' => 200, 'used' => 12, 'resets_at' => 1796079600], $result['free_quota']);
        $invalid = activation_state::billing_from_server(['free_quota' => ['limit' => '200', 'used' => -1]], 'x.example');
        $this->assertNull($invalid['free_quota']);
        $none = activation_state::billing_from_server([], 'x.example');
        $this->assertNull($none['free_quota']);
    }

    /**
     * Links to the legal texts are only accepted as plain https links, all three or none.
     *
     * @covers \block_fredkitheagent\local\activation_state::billing_from_server
     */
    public function test_legal_links(): void {
        $legal = [
            'terms_url' => 'https://lernsteine.com/agb',
            'privacy_url' => 'https://lernsteine.com/datenschutz',
            'avv_url' => 'https://fredki-theagent.lernsteine.de/avv',
        ];
        $result = activation_state::billing_from_server(['legal' => $legal], 'fredki-theagent.lernsteine.de');
        $this->assertSame($legal, $result['legal']);
        $legal['avv_url'] = 'javascript:alert(1)';
        $this->assertNull(activation_state::billing_from_server(['legal' => $legal], 'x.example')['legal']);
        $legal['avv_url'] = 'http://lernsteine.com/avv';
        $this->assertNull(activation_state::billing_from_server(['legal' => $legal], 'x.example')['legal']);
    }

    /**
     * Only the last characters of a licence key are accepted, never anything else.
     *
     * @covers \block_fredkitheagent\local\activation_state::billing_from_server
     */
    public function test_licence_hint(): void {
        $host = 'fredki-theagent.lernsteine.de';
        $result = activation_state::billing_from_server(['license' => ['hint' => 'A1B2C', 'ends_at' => 1796079600]], $host);
        $this->assertSame(['hint' => '…A1B2C', 'ends_at' => 1796079600], $result['licence']);
        foreach ([['hint' => '<b>'], ['hint' => 'FK2-AAAAA-BBBBB'], 'A1B2C', ['ends_at' => 5]] as $invalid) {
            $this->assertNull(activation_state::billing_from_server(['license' => $invalid], $host)['licence']);
        }
    }

    /**
     * Older servers without package fields and invalid values yield nulls.
     *
     * @covers \block_fredkitheagent\local\activation_state::billing_from_server
     */
    public function test_missing_or_invalid_values(): void {
        $this->assertSame([
            'message_quota' => null,
            'legal' => null,
            'legal_required' => false,
            'free_quota' => null,
            'plan' => null, 'course_limit' => null, 'courses_used' => null, 'licence' => null,
        ], activation_state::billing_from_server([], 'fredki-theagent.lernsteine.de'));
        $result = activation_state::billing_from_server(
            ['plan' => '<b>x</b>', 'course_limit' => '10', 'courses_used' => -1],
            'fredki-theagent.lernsteine.de'
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

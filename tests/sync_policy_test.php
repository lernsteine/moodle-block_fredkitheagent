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
 * FredKI The Agent: sync_policy_test.php
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_fredkitheagent;
/**
 * Full Moodle scan; delta transfer unless an explicit complete server recheck is requested.
 *
 * @covers \block_fredkitheagent\local\sync_policy
 */
final class sync_policy_test extends \advanced_testcase {
    /**
     * A run without visible material is reported as waiting for material, not as a connection failure.
     *
     * @covers \block_fredkitheagent\local\import_completion::waiting_for_material
     */
    public function test_waiting_for_material_is_detected(): void {
        $prefix = \block_fredkitheagent\local\import_completion::NOMATERIAL_PREFIX;
        $this->assertTrue(\block_fredkitheagent\local\import_completion::waiting_for_material(
            (object)['lasterror' => $prefix . 'No importable material']
        ));
        $this->assertFalse(\block_fredkitheagent\local\import_completion::waiting_for_material(
            (object)['lasterror' => 'DIAG-DOCUMENT-DELETE-200-queued']
        ));
        $this->assertFalse(\block_fredkitheagent\local\import_completion::waiting_for_material(null));
    }
    /**
     * Acknowledged.
     *
     * @return \stdClass The result.
     */
    private function acknowledged(): \stdClass {
        return (object)[
            'status' => 'synced',
            'fredki_document_id' => 'remote-document-123',
            'timesynced' => 2000000000,
            'contenthash' => str_repeat('a', 40),
            'pathnamehash' => str_repeat('b', 40),
            'lastresult' => 'synced',
            'lasterror' => null,
        ];
    }
    /**
     * Test unchanged remotely confirmed mapping is reused after full local scan.
     */
    public function test_unchanged_remotely_confirmed_mapping_is_reused_after_full_local_scan(): void {
        $this->assertTrue(\block_fredkitheagent\local\sync_policy::can_reuse(
            $this->acknowledged(),
            true,
            false,
            2000003600
        ));
    }
    /**
     * Test changed or failed documents are transferred again.
     */
    public function test_changed_or_failed_documents_are_transferred_again(): void {
        $map = $this->acknowledged();
        $map->status = 'pending';
        $this->assertFalse(\block_fredkitheagent\local\sync_policy::can_reuse($map, true, false, 2000003600));
        $map->status = 'synced';
        $map->fredki_document_id = null;
        $this->assertFalse(\block_fredkitheagent\local\sync_policy::can_reuse($map, true, false, 2000003600));
        $map->fredki_document_id = 'remote-document-123';
        $map->lasterror = 'previous error';
        $this->assertFalse(\block_fredkitheagent\local\sync_policy::can_reuse($map, true, false, 2000003600));
    }
    /**
     * Test changed target or manual full check never reuses mapping.
     */
    public function test_changed_target_or_manual_full_check_never_reuses_mapping(): void {
        $this->assertFalse(\block_fredkitheagent\local\sync_policy::can_reuse(
            $this->acknowledged(),
            false,
            false,
            2000003600
        ));
        $this->assertFalse(\block_fredkitheagent\local\sync_policy::can_reuse(
            $this->acknowledged(),
            true,
            true,
            2000003600
        ));
    }

    /**
     * Test only transient gateway problems are retried within one synchronisation.
     */
    public function test_only_transient_gateway_errors_are_retried(): void {
        $diag = \block_fredkitheagent\local\managed_api_diagnostic_exception::class;
        $this->assertTrue(\block_fredkitheagent\local\sync_policy::is_retryable(new $diag('DIAG-DOCUMENT-DELETE-200-queued')));
        $this->assertTrue(\block_fredkitheagent\local\sync_policy::is_retryable(new $diag('DIAG-DOCUMENT-HTTP-NETWORK')));
        $this->assertTrue(\block_fredkitheagent\local\sync_policy::is_retryable(new $diag('DIAG-FK-CREATE-HTTP-500')));
        $this->assertFalse(\block_fredkitheagent\local\sync_policy::is_retryable(new $diag('DIAG-CONFIG: invalid address')));
        $this->assertFalse(\block_fredkitheagent\local\sync_policy::is_retryable(new $diag('DIAG-403: denied')));
        $this->assertFalse(\block_fredkitheagent\local\sync_policy::is_retryable(new \RuntimeException('local failure')));
    }
}

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
 * FredKI The Agent. CourseImport-style local delta policy, based on remote acknowledgements.
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_fredkitheagent\local;
/**
 * Decide whether a remotely acknowledged document may be reused after a complete Moodle rescan.
 */
final class sync_policy {
    /**
     * Can reuse.
     *
     * @param \stdClass $map The map.
     * @param bool $trustedprior The trustedprior.
     * @param bool $fullcheck The fullcheck.
     * @param int $now The now.
     * @return bool True on success.
     */
    public static function can_reuse(\stdClass $map, bool $trustedprior, bool $fullcheck, int $now): bool {
        if (!$trustedprior || $fullcheck || (string)($map->status ?? '') !== 'synced') {
            return false;
        }
        if (!preg_match('/^[A-Za-z0-9_-]{8,100}$/D', (string)($map->fredki_document_id ?? ''))) {
            return false;
        }
        if ((int)($map->timesynced ?? 0) <= 0 || (int)($map->timesynced ?? 0) > $now) {
            return false;
        }
        if (trim((string)($map->lasterror ?? '')) !== '') {
            return false;
        }
        if (!in_array((string)($map->lastresult ?? ''), ['synced', 'unchanged'], true)) {
            return false;
        }
        // The importer already compared current Moodle content and metadata against
        // the map and marked all changed documents 'pending'. A unchanged map with
        // this server-owned document id can therefore be reused in the SAME target.
        return true;
    }
}

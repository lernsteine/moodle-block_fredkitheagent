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
 * FredKI The Agent: import_completion.php
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_fredkitheagent\local;

/**
 * Central decision for completion of an authorised course-material import.
 */
final class import_completion {
    /**
     * A completed import must have inspected a nonempty, accessible snapshot
     * and received remote acknowledgements for all current documents.
     *
     * @param array $counts The counts.
     * @param int $syncedrecords The syncedrecords.
     * @return bool The result.
     */
    public static function successful(array $counts, int $syncedrecords): bool {
        return empty($counts['emptycapture'])
            && (int)($counts['found'] ?? 0) > 0
            && (int)($counts['failed'] ?? 0) === 0
            && $syncedrecords > 0
            && $syncedrecords === (int)($counts['found'] ?? 0);
    }

    /**
     * Fail closed even for records written by older, permissive versions.
     *
     * @param \stdClass|null $record The record.
     * @return bool The result.
     */
    public static function chat_ready(?\stdClass $record): bool {
        return $record !== null
            && (string)($record->status ?? '') === 'ready'
            && (int)($record->timesynced ?? 0) > 0
            && (int)($record->verifiedat ?? 0) > 0
            && (int)($record->documentcount ?? 0) > 0
            && !empty($record->agentid)
            && !empty($record->knowledgeid)
            && (string)($record->managedtargetid ?? '') === (string)($record->knowledgeid ?? '')
            && trim((string)($record->lasterror ?? '')) === '';
    }
}

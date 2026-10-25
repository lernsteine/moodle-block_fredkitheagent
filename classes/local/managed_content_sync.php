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
 * Original Moodle course-material collection routed via authenticated central gateway.
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_fredkitheagent\local;
/**
 * Managed content sync class.
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class managed_content_sync {
    /**
     * Run a gateway call and repeat it once after a short pause when the failure looks transient.
     *
     * @param callable $operation The call to run.
     * @return array The result of the call.
     */
    private static function with_retry(callable $operation): array {
        try {
            return $operation();
        } catch (\Throwable $e) {
            if (!sync_policy::is_retryable($e)) {
                throw $e;
            }
            sleep(3);
            return $operation();
        }
    }

    /**
     * Run.
     *
     * @param \stdClass $course The course record.
     * @param \stdClass $record The record.
     * @param bool $trustedprior The trustedprior.
     * @param bool $fullcheck The fullcheck.
     * @param float $budget Seconds after which no further document is started (0 = unlimited).
     * @return array The result.
     */
    public static function run(
        \stdClass $course,
        \stdClass $record,
        bool $trustedprior = false,
        bool $fullcheck = false,
        float $budget = 0.0
    ): array {
        global $DB;
        $lockfactory = \core\lock\lock_config::get_lock_factory('block_fredkitheagent');
        $lock = $lockfactory->get_lock('course_sync_' . (int)$course->id, 15);
        if (!$lock) {
            throw new \moodle_exception('synclockbusy', 'block_fredkitheagent');
        }
        try {
            $started = microtime(true);
            $deadline = $budget > 0 ? $started + $budget : 0.0;
            // The original importer builds file/HTML representations of visible
            // resource, folder, page, label, glossary, book, lesson etc.
            $importer = new course_content_importer();
            $stats = $importer->import_course_content($course, $record, null, true);
            $counts = [
                'synced' => 0, 'unchanged' => 0, 'cached' => 0, 'deleted' => 0, 'failed' => 0, 'verified' => 0, 'deferred' => 0,
            ];
            // An old Moodle-to-FredKI mapping does NOT prove that its document
            // exists in a newly provisioned central knowledge base. On the
            // first run against this target, re-queue every extant source.
            $targetid = trim((string)($record->knowledgeid ?? ''));
            if ($targetid === '') {
                throw new \moodle_exception('managedtargetmissing', 'block_fredkitheagent');
            }
            $oldtarget = trim((string)($record->managedtargetid ?? ''));
            if ($oldtarget !== $targetid) {
                $existingmaps = $DB->get_records('block_fredkitheagent_map', ['activityid' => $record->id]);
                foreach ($existingmaps as $existingmap) {
                    // Do not resurrect deleted source documents.
                    if (in_array($existingmap->status, ['deleted', 'removed'], true)) {
                        continue;
                    }
                    $existingmap->status = 'pending';
                    $existingmap->lastresult = 'created';
                    $existingmap->fredki_document_id = null;
                    $existingmap->fredki_source_file_id = null;
                    $existingmap->lasterror = null;
                    $existingmap->timemodified = time();
                    $DB->update_record('block_fredkitheagent_map', $existingmap);
                }
                $record->managedtargetid = $targetid;
                $DB->update_record('block_fredkitheagent', $record);
            }
            $scanseconds = round(microtime(true) - $started, 2);
            $maps = $DB->get_records('block_fredkitheagent_map', ['activityid' => $record->id]);
            foreach ($maps as $map) {
                if ($map->status === 'deleted') {
                    if ($deadline > 0 && microtime(true) > $deadline) {
                        $counts['deferred']++; // Continued by the next click; nothing is lost.
                        continue;
                    }
                    try {
                        self::with_retry(static function () use ($course, $map): array {
                            return managed_gateway::delete_document((int)$course->id, (string)$map->pathnamehash);
                        });
                        $map->status = 'removed';
                        $map->lastresult = 'deleted';
                        $map->lasterror = null;
                        $map->timemodified = time();
                        $DB->update_record('block_fredkitheagent_map', $map);
                        $counts['deleted']++;
                    } catch (\Throwable $e) {
                        $deletecode = $e instanceof managed_api_diagnostic_exception ? $e->getMessage() : 'DIAG-SYNC-INTERNAL';
                        debugging('[FredKI The Agent] delete failed: ' . $deletecode, DEBUG_DEVELOPER);
                        $map->lasterror = get_string('syncdeletepending', 'block_fredkitheagent', $deletecode);
                        $map->timemodified = time();
                        $DB->update_record('block_fredkitheagent_map', $map);
                        $counts['failed']++;
                    }
                    continue;
                }
                // Reconcile even previously 'synced' rows with the CENTRAL server.
                // Earlier plugin versions could report 5 found / 0 sent / 0 failed
                // despite no central document mapping. The gateway is idempotent
                // by installation + course + file_key + content_hash.
                if (!in_array($map->status, ['pending', 'error', 'synced'], true)) {
                    continue;
                }
                // The importer has just examined EVERY visible Moodle source and marked
                // changed/new ones pending. Reuse ONLY a prior centrally acknowledged
                // document from the same remote knowledge base. No seven-day shortcut.
                if (sync_policy::can_reuse($map, $trustedprior, $fullcheck, time())) {
                    $counts['cached']++;
                    $counts['verified']++; // Confirmed remotely in an earlier successful run.
                    continue;
                }
                if ($deadline > 0 && microtime(true) > $deadline) {
                    $counts['deferred']++; // Stays pending; the next sync resumes here.
                    continue;
                }
                $file = get_file_storage()->get_file_by_hash((string)$map->pathnamehash);
                if (!$file || $file->is_directory()) {
                    $map->status = 'error';
                    $map->lasterror = 'Moodle-Datei fehlt';
                    $DB->update_record('block_fredkitheagent_map', $map);
                    $counts['failed']++;
                    continue;
                }
                $tmp = tempnam(make_request_directory(), 'fredki-');
                try {
                    if (!$tmp || !$file->copy_content_to($tmp)) {
                        throw new \RuntimeException('Moodle-Datei konnte nicht vorbereitet werden.');
                    }
                    $source = self::source_url($file, $course);
                    $out = self::with_retry(static function () use ($course, $map, $file, $tmp, $source): array {
                        return managed_gateway::upload_document(
                            (int)$course->id,
                            (string)$map->pathnamehash,
                            (string)$file->get_contenthash(),
                            $tmp,
                            (string)$file->get_filename(),
                            $source
                        );
                    });
                    // An HTTP 200 without a persistent remote document ID is not proof.
                    if (empty($out['document_id']) || !is_string($out['document_id'])) {
                        throw new \RuntimeException('Remote document acknowledgement missing.');
                    }
                    $map->status = 'synced';
                    $map->lastresult = (string)$out['state'];
                    $map->fredki_document_id = (string)($out['document_id'] ?? '');
                    $map->fredki_source_file_id = (string)($out['source_file_id'] ?? '');
                    $map->lasterror = null;
                    $map->timesynced = time();
                    $map->timemodified = time();
                    $DB->update_record('block_fredkitheagent_map', $map);
                    $counts[$out['state'] === 'unchanged' ? 'unchanged' : 'synced']++;
                    $counts['verified']++;
                } catch (\Throwable $e) {
                    $map->status = 'error';
                    $map->lastresult = 'error';
                    // Expose only predefined gateway diagnostic codes, never
                    // URLs, secret tokens or the raw central API response.
                    $code = $e instanceof managed_api_diagnostic_exception ? $e->getMessage() : 'DIAG-SYNC-INTERNAL';
                    $map->lasterror = get_string('syncuploadfailed', 'block_fredkitheagent', $code);
                    $map->timemodified = time();
                    $DB->update_record('block_fredkitheagent_map', $map);
                    $counts['failed']++;
                } finally {
                    if ($tmp && is_file($tmp)) {
                        @unlink($tmp);
                    }
                }
            }
            $counts['scan_seconds'] = $scanseconds;
            $counts['remoteconfirmed'] = $counts['synced'] + $counts['unchanged'];
            $counts['total_seconds'] = round(microtime(true) - $started, 2);
            $counts['found'] = (int)$DB->count_records_select(
                'block_fredkitheagent_map',
                'activityid = :aid AND status <> :removed AND status <> :deleted',
                ['aid' => $record->id, 'removed' => 'removed', 'deleted' => 'deleted']
            );
            $counts['generated'] = (int)($stats['files'] ?? 0) + (int)($stats['sectiontexts'] ?? 0)
                + (int)($stats['activitytexts'] ?? 0) + (int)($stats['urls'] ?? 0)
                + (int)($stats['glossaries'] ?? 0) + (int)($stats['pages'] ?? 0)
                + (int)($stats['labels'] ?? 0) + (int)($stats['books'] ?? 0)
                + (int)($stats['lessons'] ?? 0) + (int)($stats['h5p'] ?? 0)
                + (int)($stats['scorm'] ?? 0);
            if ($counts['found'] > 0 && $counts['verified'] === 0 && !$counts['failed']) {
                $counts['warning'] = get_string('syncwarningunconfirmed', 'block_fredkitheagent');
            }
            if ($counts['deferred'] > 0 && !$counts['failed']) {
                $counts['warning'] = import_completion::PARTIAL_PREFIX . get_string('syncpartial', 'block_fredkitheagent', (object)[
                    'done' => (int)$counts['verified'],
                    'total' => (int)$counts['found'],
                ]);
            }
            if ($counts['found'] === 0 || !empty($stats['emptycapture'])) {
                // Zero is a diagnostic result, not a successful import claim.
                $counts['warning'] = import_completion::NOMATERIAL_PREFIX . get_string(
                    'syncwarningnone',
                    'block_fredkitheagent',
                    (object)[
                        'scanned' => (int)($stats['scannedactivities'] ?? 0),
                        'eligible' => (int)($stats['eligibleactivities'] ?? 0),
                        'restricted' => (int)($stats['restrictedactivities'] ?? 0),
                        'resource' => (int)($stats['resourceactivities'] ?? 0),
                        'nofiles' => (int)($stats['resourceswithoutfiles'] ?? 0),
                    ]
                );
            }
            $record->documentcount = $DB->count_records('block_fredkitheagent_map', [
                'activityid' => $record->id, 'status' => 'synced',
            ]);
            $complete = import_completion::successful($counts + $stats, (int)$record->documentcount)
                && $counts['verified'] === $counts['found'];
            // A failed/empty import must never unlock protected course knowledge.
            // The button remains retryable; no new registration or agent is needed.
            $record->timesynced = $complete ? time() : null;
            $record->verifiedat = $complete ? time() : null;
            $record->timemodified = time();
            $record->status = 'sync_pending'; // Agent confirmation occurs after the document import.
            if ($counts['failed']) {
                $firsterror = $DB->get_field_select(
                    'block_fredkitheagent_map',
                    'lasterror',
                    'activityid = :aid AND status = :status',
                    ['aid' => $record->id, 'status' => 'error'],
                    IGNORE_MISSING
                );
                $record->lasterror = get_string('syncfileserrors', 'block_fredkitheagent', (object)[
                    'count' => $counts['failed'],
                    'first' => (string)($firsterror ?: get_string('syncunavailable', 'block_fredkitheagent')),
                ]);
            } else {
                $record->lasterror = $counts['warning'] ??
                    (!$complete ? get_string('syncnotcomplete', 'block_fredkitheagent') : null);
            }
            $DB->update_record('block_fredkitheagent', $record);
            return array_merge($stats, $counts);
        } finally {
            $lock->release();
        }
    }

    /**
     * Source url.
     *
     * @param \stored_file $file The file.
     * @param \stdClass $course The course record.
     * @return string The result.
     */
    private static function source_url(\stored_file $file, \stdClass $course): string {
        // Keep Moodle authentication on linked original source materials.
        $path = (string)$file->get_filepath();
        if (preg_match('~^/course-files/cm(\d+)(/.*)$~', $path, $m)) {
            $cm = get_coursemodule_from_id(null, (int)$m[1], 0, false, IGNORE_MISSING);
            if ($cm) {
                $context = \context_module::instance((int)$cm->id, IGNORE_MISSING);
                if ($context) {
                    return \moodle_url::make_pluginfile_url(
                        $context->id,
                        'mod_' . $cm->modname,
                        'content',
                        0,
                        $m[2],
                        $file->get_filename(),
                        false
                    )->out(false);
                }
            }
        }
        if (preg_match('~cm(\d+)~', (string)$file->get_filename(), $m)) {
            return (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
        }
        return (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
    }
}

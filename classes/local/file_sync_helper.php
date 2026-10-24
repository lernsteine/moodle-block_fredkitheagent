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
 * Helper for generated course files.
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_fredkitheagent\local;

/**
 * File sync helper class.
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_sync_helper {
    /**
     * Scan generated files.
     *
     * @param int $courseid The course id.
     * @param int $activityid The activity id.
     * @return array The result.
     */
    public static function scan_generated_files(int $courseid, int $activityid): array {
        global $DB;

        $context = \context_course::instance($courseid);
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'block_fredkitheagent', 'generated_files', 0, 'filepath, filename', false);
        $currenthashes = [];
        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'deleted' => 0,
        ];

        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }
            $pathnamehash = $file->get_pathnamehash();
            $currenthashes[] = $pathnamehash;
            $metatags = manual_sync_service::get_moodle_activity_profile_tags($file);
            $metahash = sha1($metatags);
            $existing = $DB->get_record('block_fredkitheagent_map', ['activityid' => $activityid, 'pathnamehash' => $pathnamehash]);

            if (!$existing) {
                $record = (object)[
                    'cmid' => $courseid,
                    'activityid' => $activityid,
                    'pathnamehash' => $pathnamehash,
                    'filename' => $file->get_filename(),
                    'contenthash' => $file->get_contenthash(),
                    'filesize' => $file->get_filesize(),
                    'metahash' => $metahash,
                    'status' => 'pending',
                    'lastresult' => 'created',
                    'timecreated' => time(),
                    'timemodified' => time(),
                ];
                try {
                    $DB->insert_record('block_fredkitheagent_map', $record);
                    $stats['created']++;
                } catch (\dml_exception $e) {
                    // Idempotent rescan: if the same generated file was already mapped by a
                    // previous/in-parallel run, update that existing row instead of failing the
                    // activity save with a duplicate key database error.
                    $existing = $DB->get_record('block_fredkitheagent_map', [
                        'activityid' => $activityid,
                        'pathnamehash' => $pathnamehash,
                    ], '*', IGNORE_MISSING);
                    if ($existing) {
                        $existing->cmid = $courseid;
                        $existing->filename = $file->get_filename();
                        $existing->contenthash = $file->get_contenthash();
                        $existing->filesize = $file->get_filesize();
                        if (property_exists($existing, 'metahash')) {
                            $existing->metahash = $metahash;
                        }
                        if (in_array($existing->status, ['deleted', 'removed'], true)) {
                            $existing->status = 'pending';
                            $existing->lastresult = 'created';
                        }
                        $existing->timemodified = time();
                        $DB->update_record('block_fredkitheagent_map', $existing);
                        $stats['updated']++;
                    } else {
                        throw $e;
                    }
                }
                continue;
            }

            $oldmetahash = property_exists($existing, 'metahash') ? (string)$existing->metahash : '';
            $tagchanged = ($oldmetahash !== $metahash) && ($oldmetahash !== '' || trim($metatags) !== '');
            $changed = $existing->contenthash !== $file->get_contenthash() ||
                       $existing->filename !== $file->get_filename() ||
                       (int)$existing->filesize !== (int)$file->get_filesize();

            if ($existing->status === 'removed' || $existing->status === 'deleted') {
                $existing->filename = $file->get_filename();
                $existing->contenthash = $file->get_contenthash();
                $existing->filesize = $file->get_filesize();
                if (property_exists($existing, 'metahash')) {
                    $existing->metahash = $metahash;
                }
                $existing->status = 'pending';
                $existing->lastresult = 'created';
                $existing->lasterror = null;
                $existing->timemodified = time();
                $DB->update_record('block_fredkitheagent_map', $existing);
                $stats['created']++;
            } else if ($changed) {
                $existing->filename = $file->get_filename();
                $existing->contenthash = $file->get_contenthash();
                $existing->filesize = $file->get_filesize();
                if (property_exists($existing, 'metahash')) {
                    $existing->metahash = $metahash;
                }
                $existing->status = 'pending';
                $existing->lastresult = 'updated';
                $existing->lasterror = null;
                $existing->timemodified = time();
                $DB->update_record('block_fredkitheagent_map', $existing);
                $stats['updated']++;
            } else if ($tagchanged) {
                if (property_exists($existing, 'metahash')) {
                    $existing->metahash = $metahash;
                }
                $existing->status = 'pending';
                $existing->lastresult = 'tagsupdated';
                $existing->lasterror = null;
                $existing->timemodified = time();
                $DB->update_record('block_fredkitheagent_map', $existing);
                $stats['updated']++;
            } else {
                if (empty($oldmetahash) && property_exists($existing, 'metahash')) {
                    $existing->metahash = $metahash;
                    $existing->timemodified = time();
                    $DB->update_record('block_fredkitheagent_map', $existing);
                }
                if (empty($existing->lastresult)) {
                    $existing->lastresult = 'skipped';
                    $existing->timemodified = time();
                    $DB->update_record('block_fredkitheagent_map', $existing);
                }
                $stats['skipped']++;
            }
        }

        $known = $DB->get_records('block_fredkitheagent_map', ['activityid' => $activityid]);
        foreach ($known as $record) {
            if (
                !in_array($record->pathnamehash, $currenthashes, true) &&
                !in_array($record->status, ['deleted', 'removed'], true)
            ) {
                $record->status = 'deleted';
                $record->lastresult = 'deleted';
                $record->timemodified = time();
                $DB->update_record('block_fredkitheagent_map', $record);
                $stats['deleted']++;
            }
        }

        return $stats;
    }
}

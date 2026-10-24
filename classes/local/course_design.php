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
 * FredKI The Agent: course_design.php
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_fredkitheagent\local;
/**
 * Display-only course-specific design; never stores or changes FredKI credentials.
 */
final class course_design {
    /** @var mixed Value for colors. */
    public const COLORS = ['headercolor', 'accentcolor', 'backgroundcolor', 'botbubblecolor', 'userbubblecolor'];

    /**
     * Get.
     *
     * @param int $courseid The course id.
     * @return ?\stdClass The result.
     */
    public static function get(int $courseid): ?\stdClass {
        global $DB;
        $mapping = course_agent_manager::get_record($courseid);
        if (!$mapping || empty($mapping->agentid)) {
            return null;
        }
        $record = $DB->get_record('block_fredkitheagent_design', ['courseid' => $courseid]);
        if (!$record || !hash_equals((string)$mapping->agentid, (string)$record->agentid)) {
            return null; // A changed agent never inherits another agent's appearance.
        }
        return $record;
    }

    /**
     * Color.
     *
     * @param ?\stdClass $design The design.
     * @param string $name The name.
     * @param string $default The default.
     * @return string The result.
     */
    public static function color(?\stdClass $design, string $name, string $default): string {
        $value = $design ? (string)($design->{$name} ?? '') : '';
        return preg_match('/^#[0-9a-fA-F]{6}$/D', $value) ? $value : $default;
    }

    /**
     * Logo url.
     *
     * @param int $courseid The course id.
     * @return ?string The result.
     */
    public static function logo_url(int $courseid): ?string {
        $design = self::get($courseid);
        if (!$design) {
            return null;
        }
        $context = \context_course::instance($courseid);
        $files = get_file_storage()->get_area_files(
            $context->id,
            'block_fredkitheagent',
            'courselogo',
            0,
            'filename',
            false
        );
        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }
            if (!in_array($file->get_mimetype(), ['image/png', 'image/jpeg', 'image/webp'], true)) {
                continue;
            }
            return \moodle_url::make_pluginfile_url(
                $context->id,
                'block_fredkitheagent',
                'courselogo',
                0,
                '/',
                $file->get_filename()
            )->out(false);
        }
        return null;
    }
}

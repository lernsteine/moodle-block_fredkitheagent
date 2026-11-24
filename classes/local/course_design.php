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

    /** @var string[] Selectable shapes of the chat logo and of the chat button. */
    public const LOGO_SHAPES = ['round', 'rounded', 'sharp'];

    /** @var int Smallest size of the chat button in pixels. */
    public const BUTTON_MIN = 40;

    /** @var int Largest size of the chat button in pixels. */
    public const BUTTON_MAX = 240;

    /** @var int Default size of the chat button in pixels. */
    public const BUTTON_DEFAULT = 72;

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

    /**
     * The chat logo uploaded by the administrator in the plugin settings.
     *
     * @return ?string Pluginfile URL or null.
     */
    public static function system_logo_url(): ?string {
        $context = \context_system::instance();
        $files = get_file_storage()->get_area_files($context->id, 'block_fredkitheagent', 'chatlogo', 0, 'filename', false);
        foreach ($files as $file) {
            if (!$file->is_directory()) {
                return \moodle_url::make_pluginfile_url(
                    $context->id,
                    'block_fredkitheagent',
                    'chatlogo',
                    0,
                    '/',
                    $file->get_filename()
                )->out(false);
            }
        }
        return null;
    }

    /**
     * The logo of the chat: the logo of the course if there is one, otherwise the one from the plugin settings.
     *
     * @param int $courseid The course id.
     * @return ?string The result.
     */
    public static function chat_logo_url(int $courseid): ?string {
        return self::logo_url($courseid) ?? self::system_logo_url();
    }

    /**
     * Shape of a logo from a stored setting value; unknown values fall back to the default.
     *
     * @param mixed $value The stored value.
     * @param string $default The default shape.
     * @return string The result.
     */
    private static function shape($value, string $default): string {
        $value = (string)$value;
        if ($value === 'square') {
            // Value of version 1.9.2: square with rounded corners.
            $value = 'rounded';
        }
        return in_array($value, self::LOGO_SHAPES, true) ? $value : $default;
    }

    /**
     * A size of the chat button in pixels, kept within the allowed range.
     *
     * @param mixed $value The stored value.
     * @param int $fallback The value to use if nothing valid is stored.
     * @return int The result.
     */
    private static function button_size($value, int $fallback): int {
        $value = (int)$value;
        if ($value <= 0) {
            return $fallback;
        }
        return max(self::BUTTON_MIN, min(self::BUTTON_MAX, $value));
    }

    /**
     * Appearance of the chat logo and of the chat button from the plugin settings.
     *
     * Unknown or missing values fall back to the defaults, so nothing from the settings can break the page.
     *
     * @return array Keys: logoshape, buttonshape (round, rounded or sharp), width, height (pixels),
     *               frame (white frame around the logo), keepratio (logo is not cropped),
     *               radius, padding, innerradius (pixels, derived).
     */
    public static function logo_appearance(): array {
        $cfg = get_config('block_fredkitheagent');
        $logoshape = self::shape($cfg->chatlogoshape ?? '', 'round');
        $buttonshape = self::shape($cfg->chatbuttonshape ?? ($cfg->chatlogoshape ?? ''), 'rounded');
        $width = self::button_size($cfg->chatbuttonwidth ?? 0, self::BUTTON_DEFAULT);
        $height = self::button_size($cfg->chatbuttonheight ?? 0, self::BUTTON_DEFAULT);
        $frame = isset($cfg->chatlogoframe) ? !empty($cfg->chatlogoframe) : true;
        $keepratio = isset($cfg->chatbuttonkeepratio) ? !empty($cfg->chatbuttonkeepratio) : true;

        $min = min($width, $height);
        $radius = ['round' => intdiv($min, 2), 'rounded' => (int)round($min * 0.28), 'sharp' => 0][$buttonshape];
        $padding = $frame ? (int)round($min * 0.12) : 0;
        return [
            'logoshape' => $logoshape,
            'buttonshape' => $buttonshape,
            'width' => $width,
            'height' => $height,
            'frame' => $frame,
            'keepratio' => $keepratio,
            'radius' => $radius,
            'padding' => $padding,
            'innerradius' => max(0, $radius - $padding),
        ];
    }
}

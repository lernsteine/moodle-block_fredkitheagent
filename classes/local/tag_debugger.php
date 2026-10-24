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
 * Debug helper for FredKI CourseImportPlus tag transfer.
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_fredkitheagent\local;

/**
 * Tag debugger class.
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tag_debugger {
    /**
     * Enabled.
     *
     * @return bool True on success.
     */
    public static function enabled(): bool {
        return (bool)get_config('block_fredkitheagent', 'debuglog');
    }

    /**
     * Log.
     *
     * @param string $message The message.
     * @param array $context The context.
     */
    public static function log(string $message, array $context = []): void {
        if (!self::enabled()) {
            return;
        }
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
        if (!empty($context)) {
            $line .= ' | ' . json_encode(self::clean($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $line .= PHP_EOL;
        try {
            $dir = make_temp_directory('fredkitheagent');
            @file_put_contents($dir . '/tag_debug.log', $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            debugging('[block_fredkitheagent][tag_debug] Unable to write debug log.', DEBUG_DEVELOPER);
        }
    }

    /**
     * Clean.
     *
     * @param array $context The context.
     * @return array The result.
     */
    protected static function clean(array $context): array {
        foreach ($context as $key => $value) {
            if (is_string($key) && preg_match('/password|token|secret/i', $key)) {
                $context[$key] = '***';
                continue;
            }
            if (is_string($value) && strlen($value) > 1200) {
                $context[$key] = substr($value, 0, 1200) . '...';
            }
        }
        return $context;
    }
}

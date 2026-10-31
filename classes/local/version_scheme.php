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
 * FredKI The Agent: version_scheme.php
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_fredkitheagent\local;

/**
 * Moves sites from the former 12-digit version numbers (YYYYMMDDHHMM) to the Moodle scheme YYYYMMDDXX.
 *
 * Every 10-digit number is smaller than a 12-digit one, so Moodle would treat the update to 1.5.1 as a
 * downgrade and stop. The upgrade steps were renumbered in the same order; an installed 12-digit version
 * is mapped to the new number of the last upgrade step it has already run, so no step is skipped or repeated.
 */
final class version_scheme {
    /** @var array<int, int> Former upgrade step => renumbered upgrade step, in ascending order. */
    private const STEPS = [
        202606081487 => 2026060800,
        202607290001 => 2026072900,
        202610120501 => 2026101200,
        202610120801 => 2026101201,
        202610222100 => 2026102200,
        202610222300 => 2026102201,
        202610222301 => 2026102202,
        202610222302 => 2026102203,
        202610240700 => 2026102400,
        202610271500 => 2026102700,
        202610271900 => 2026102701,
    ];

    /**
     * The 10-digit version that corresponds to a former 12-digit installed version.
     *
     * @param int $old Installed version in the former scheme.
     * @return int Version in the new scheme.
     */
    public static function map(int $old): int {
        $new = 2026060700;
        foreach (self::STEPS as $step => $renumbered) {
            if ($old >= $step) {
                $new = $renumbered;
            }
        }
        return $new;
    }

    /**
     * Converts the stored version once, so that the Moodle upgrade can run normally afterwards.
     *
     * @return bool True if the stored version was converted.
     */
    public static function repair(): bool {
        $installed = (int)get_config('block_fredkitheagent', 'version');
        if ($installed <= 9999999999) {
            return false;
        }
        set_config('version', self::map($installed), 'block_fredkitheagent');
        \core_plugin_manager::reset_caches();
        return true;
    }
}

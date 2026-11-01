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
 * FredKI The Agent: welcome_sync.php
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_fredkitheagent\local;

/**
 * Takes the opening statement of the FredKI template agent over into the global welcome text.
 *
 * The global setting is only overwritten while the administrator has not changed it: it is empty,
 * still the plugin default, or still the text taken over last time. Course texts are never touched.
 */
final class welcome_sync {
    /** @var string Component. */
    private const COMPONENT = 'block_fredkitheagent';
    /** @var int Seconds between two look-ups. */
    public const INTERVAL = 21600;

    /**
     * Whether the global welcome text may follow the template agent.
     *
     * @param string $current Current global text.
     * @param string $lastsynced Text taken over last time.
     * @param string $plugindefault Default of the setting.
     * @return bool
     */
    public static function follows_template(string $current, string $lastsynced, string $plugindefault): bool {
        $current = trim($current);
        return $current === '' || $current === trim($plugindefault) || ($lastsynced !== '' && $current === trim($lastsynced));
    }

    /**
     * Fetch (at most every six hours) and apply the template welcome text.
     *
     * @param bool $force Ignore the interval.
     * @return bool True if the global text was updated.
     */
    public static function sync(bool $force = false): bool {
        $last = (int)get_config(self::COMPONENT, 'templatewelcome_checked');
        if (!$force && $last > time() - self::INTERVAL) {
            return false;
        }
        set_config('templatewelcome_checked', time(), self::COMPONENT);
        try {
            $welcome = managed_gateway::agent_defaults()['welcome'];
        } catch (\Throwable $e) {
            debugging('[FredKI welcome sync] ' . get_class($e), DEBUG_DEVELOPER);
            return false;
        }
        if ($welcome === null) {
            return false;
        }
        $current = (string)get_config(self::COMPONENT, 'chatwelcometext');
        $synced = (string)get_config(self::COMPONENT, 'templatewelcome');
        if (!self::follows_template($current, $synced, get_string('settingwelcomedefault', self::COMPONENT))) {
            return false;
        }
        set_config('templatewelcome', $welcome, self::COMPONENT);
        if (trim($current) === $welcome) {
            return false;
        }
        set_config('chatwelcometext', $welcome, self::COMPONENT);
        return true;
    }
}

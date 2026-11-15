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

namespace block_fredkitheagent\local;

/**
 * Template data shared by the block and the activation page.
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class output_helper {
    /** @var string Frankenstyle component. */
    private const COMPONENT = 'block_fredkitheagent';

    /**
     * Data for the template block_fredkitheagent/quota (shown from 80 % of the monthly messages).
     *
     * @param \stdClass $a used, limit and reset (formatted date).
     * @param bool $siteadmin Whether the viewer is a site administrator.
     * @param bool $freetier Whether the site uses the free package.
     * @param bool $withlink Whether to link administrators to the activation page.
     * @return array Template context.
     */
    public static function quota(\stdClass $a, bool $siteadmin, bool $freetier, bool $withlink = false): array {
        $full = $a->used >= $a->limit;
        $suffix = $freetier ? '' : 'paid';
        $link = false;
        if ($withlink && $siteadmin) {
            $link = [
                'url' => (new \moodle_url('/blocks/fredkitheagent/activation.php'))->out(false),
                'label' => get_string('quotapackages', self::COMPONENT),
                'external' => false,
            ];
        }
        return [
            'title' => get_string(($full ? 'quotafulltitle' : 'quotanearlytitle') . $suffix, self::COMPONENT, $a),
            'used' => (int)$a->used,
            'limit' => (int)$a->limit,
            'barvalue' => min((int)$a->used, (int)$a->limit),
            'barlabel' => get_string('quotabarlabel', self::COMPONENT, $a),
            'text' => get_string(
                ($full ? 'quotafull' : 'quotanearly') . $suffix . ($siteadmin ? 'admin' : 'teacher'),
                self::COMPONENT,
                $a
            ),
            'full' => $full,
            'link' => $link,
        ];
    }
}

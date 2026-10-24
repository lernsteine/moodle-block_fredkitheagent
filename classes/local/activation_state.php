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
 * FredKI The Agent: activation_state.php
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_fredkitheagent\local;

/**
 * Strict presentation of licence-server-issued state.
 * Not sufficient for security: provisioning and chat access MUST also be checked server-side.
 */
final class activation_state {
    /** @var mixed Value for pending. */
    public const PENDING = 'pending';
    /** @var mixed Value for setup pending. */
    public const SETUP_PENDING = 'setup_pending';
    /** @var mixed Value for trial. */
    public const TRIAL = 'trial_active';
    /** @var mixed Value for licensed. */
    public const LICENSED = 'licensed';
    /** @var mixed Value for expired. */
    public const EXPIRED = 'trial_expired';
    /** @var mixed Value for suspended. */
    public const SUSPENDED = 'suspended';
    /** @var mixed Value for revoked. */
    public const REVOKED = 'revoked';
    /** @var mixed Value for unregistered. */
    public const UNREGISTERED = 'unregistered';

    /** @var mixed Value for allowed. */
    private const ALLOWED = [
        self::PENDING, self::SETUP_PENDING, self::TRIAL, self::LICENSED, self::EXPIRED,
        self::SUSPENDED, self::REVOKED, self::UNREGISTERED,
    ];

    /**
     * Parse only the response from the authenticated HTTPS activation service.
     *
     * @param array $data The data.
     * @return array The result.
     */
    public static function from_server(array $data): array {
        $state = (string)($data['state'] ?? '');
        $lease = $data['lease_until'] ?? null;
        if (!in_array($state, self::ALLOWED, true) || !is_int($lease) || $lease <= 0) {
            throw new \UnexpectedValueException('Invalid FredKI activation status response.');
        }
        $trialend = $data['trial_ends_at'] ?? null;
        if ($trialend !== null && (!is_int($trialend) || $trialend <= 0)) {
            throw new \UnexpectedValueException('Invalid FredKI trial expiry.');
        }
        return [
            'state' => $state,
            'lease_until' => $lease,
            'trial_ends_at' => $trialend,
            'tier' => preg_match('/^[a-z0-9_-]{0,40}$/D', (string)($data['tier'] ?? '')) ? (string)($data['tier'] ?? '') : '',
            'notification_status' => in_array(($data['notification_status'] ?? 'unknown'), ['sent', 'failed', 'unknown'], true)
                ? $data['notification_status'] : 'unknown',
        ];
    }

    /**
     * Can use.
     *
     * @param array $status The status.
     * @param int $now The now.
     * @return bool True on success.
     */
    public static function can_use(array $status, int $now): bool {
        return in_array(($status['state'] ?? ''), [self::SETUP_PENDING, self::TRIAL, self::LICENSED], true)
            && (int)($status['lease_until'] ?? 0) > $now
            && (($status['state'] ?? '') !== self::TRIAL
                || (int)($status['trial_ends_at'] ?? 0) > $now);
    }
}

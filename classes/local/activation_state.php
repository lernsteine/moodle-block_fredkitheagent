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
     * Parse the optional package fields of the licence server (server 13.18 and later).
     *
     * Links are only accepted as https links to the configured licence server host, so a
     * manipulated response can never send the administrator to a foreign page.
     *
     * @param array $data The status response.
     * @param string $apihost Host name of the configured licence server.
     * @return array plan, course_limit, courses_used, checkout_url, manage_url (null if absent or invalid).
     */
    public static function billing_from_server(array $data, string $apihost): array {
        $plan = $data['plan'] ?? null;
        $limit = $data['course_limit'] ?? null;
        $used = $data['courses_used'] ?? null;
        $quota = is_array($data['free_quota'] ?? null) ? $data['free_quota'] : [];
        $qlimit = $quota['limit'] ?? null;
        $qused = $quota['used'] ?? null;
        $qreset = $quota['resets_at'] ?? null;
        $freequota = is_int($qlimit) && $qlimit > 0 && $qlimit <= 1000000 && is_int($qused) && $qused >= 0
            ? ['limit' => $qlimit, 'used' => min($qused, $qlimit), 'resets_at' => is_int($qreset) && $qreset > 0 ? $qreset : null]
            : null;
        $legal = is_array($data['legal'] ?? null) ? $data['legal'] : [];
        $legallinks = [];
        foreach (['terms_url', 'privacy_url', 'avv_url'] as $key) {
            $legallinks[$key] = self::legal_link($legal[$key] ?? null);
        }
        return [
            'legal' => in_array(null, $legallinks, true) ? null : $legallinks,
            'free_quota' => $freequota,
            'plan' => is_string($plan) && preg_match('/^[\p{L}\p{N} .+\-]{1,60}$/uD', $plan) ? $plan : null,
            'course_limit' => is_int($limit) && $limit > 0 && $limit <= 100000 ? $limit : null,
            'courses_used' => is_int($used) && $used >= 0 && $used <= 100000 ? $used : null,
            'checkout_url' => self::server_link($data['checkout_url'] ?? null, $apihost),
            'manage_url' => self::server_link($data['manage_url'] ?? null, $apihost),
        ];
    }

    /**
     * Agent roles offered by the licence server (Berater, Sparringspartner, ...). Strictly validated.
     *
     * @param array $data Response of installations/status or agents/defaults.
     * @return array List of ['id', 'name', 'description', 'default', 'welcome'].
     */
    public static function roles_from_server(array $data): array {
        $out = [];
        foreach ((array)($data['agent_roles'] ?? []) as $role) {
            if (!is_array($role) || count($out) >= 12) {
                continue;
            }
            $id = $role['id'] ?? null;
            $name = $role['name'] ?? null;
            if (
                !is_string($id) || !preg_match('/^[a-z0-9_-]{1,40}$/D', $id)
                    || !is_string($name) || !preg_match('/^[\p{L}\p{N} .+\-]{1,40}$/uD', $name)
            ) {
                continue;
            }
            $description = is_string($role['description'] ?? null) ? \core_text::substr(trim($role['description']), 0, 200) : '';
            $welcome = is_string($role['welcome'] ?? null) ? \core_text::substr(trim($role['welcome']), 0, 1000) : '';
            $out[] = ['id' => $id, 'name' => $name, 'description' => $description,
                'default' => ($role['default'] ?? false) === true, 'welcome' => $welcome];
        }
        return $out;
    }

    /**
     * Accept only an https link to the licence server host.
     *
     * @param mixed $url The link from the server response.
     * @param string $apihost Host name of the configured licence server.
     * @return string|null The link or null.
     */
    private static function server_link($url, string $apihost): ?string {
        if (!is_string($url) || strlen($url) > 500 || $apihost === '') {
            return null;
        }
        $parts = parse_url($url);
        if (
            !is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass'])
                || isset($parts['port']) || strtolower((string)($parts['host'] ?? '')) !== strtolower($apihost)
                || !preg_match('~^https://[A-Za-z0-9.-]+/[A-Za-z0-9._/-]*\.php\?[A-Za-z0-9=&_-]+$~D', $url)
        ) {
            return null;
        }
        return $url;
    }

    /**
     * Accept only a plain https link to a legal text (terms, privacy notice, data processing agreement).
     *
     * @param mixed $url The link from the server response.
     * @return string|null The link or null.
     */
    private static function legal_link($url): ?string {
        if (!is_string($url) || strlen($url) > 500) {
            return null;
        }
        $parts = parse_url($url);
        if (
            !is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user'])
                || isset($parts['pass']) || isset($parts['port']) || clean_param($url, PARAM_URL) !== $url
        ) {
            return null;
        }
        return $url;
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

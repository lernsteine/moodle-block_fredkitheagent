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
 * FredKI The Agent - managed activation and resource registry.
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_fredkitheagent\local;

/**
 * Client for the FredKI licence and release server API (course registry, document sync, chat relay).
 *
 * Never accepts a FredKI administration password and never exposes agent tokens to the browser.
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class managed_gateway {
    /** @var string Frankenstyle component used for config values and language strings. */
    private const COMPONENT = 'block_fredkitheagent';

    /** @var string Default HTTPS endpoint of the FredKI licence server (disclosed in the privacy metadata). */
    private const DEFAULT_API_URL = 'https://fredki-theagent.lernsteine.de/fredki-hub/api/v1/index.php';

    /**
     * Valid only for one PHP request; prevents repeated license HTTP checks per uploaded document.
     *
     * @var array|null
     */
    private static ?array $requeststatus = null;

    /**
     * Is managed.
     *
     * @return bool True on success.
     */
    public static function is_managed(): bool {
        return true; // Public-marketplace architecture: legacy public-token mode is disabled.
    }

    /**
     * Get installation id.
     *
     * @return string The result.
     */
    public static function get_installation_id(): string {
        return (string)get_config(self::COMPONENT, 'managed_installation_id');
    }

    /**
     * Is registered.
     *
     * @return bool True on success.
     */
    public static function is_registered(): bool {
        return (bool)preg_match('/^[0-9a-f]{32}$/D', self::get_installation_id())
            && (bool)preg_match('/^[0-9a-f]{64}$/D', (string)get_config(self::COMPONENT, 'managed_installation_secret'));
    }

    /**
     * The licence server API address. The default is always used unless an administrator has explicitly
     * unlocked the address field in the plugin settings.
     *
     * @return string The address.
     */
    private static function api_address(): string {
        $custom = '';
        // Locked unless an administrator has explicitly cleared the lock. Installations that only
        // know the former "unlock" option keep their previous choice; otherwise the lock applies.
        $lock = get_config(self::COMPONENT, 'managed_api_url_lock');
        $locked = $lock === false
            ? empty(get_config(self::COMPONENT, 'managed_api_url_unlock'))
            : !empty($lock);
        if (!$locked) {
            $custom = trim((string)get_config(self::COMPONENT, 'managed_api_url'));
        }
        return $custom !== '' ? $custom : self::DEFAULT_API_URL;
    }

    /**
     * One-time registration: store both returned credentials server-side, never render them.
     *
     * @param string $email The email.
     * @return array The result.
     */
    public static function register(string $email): array {
        global $CFG;
        if (self::is_registered()) {
            throw new \moodle_exception('alreadyregistered', self::COMPONENT);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
            throw new \invalid_parameter_exception('Invalid registration e-mail.');
        }
        $result = self::request('installations/register', [
            'site_url' => (string)$CFG->wwwroot,
            'contact_email' => $email,
        ], false);
        $id = (string)($result['installation_id'] ?? '');
        $secret = (string)($result['installation_secret'] ?? '');
        if (
            !preg_match('/^[0-9a-f]{32}$/D', $id) || !preg_match('/^[0-9a-f]{64}$/D', $secret)
                || ($result['state'] ?? '') !== 'pending'
        ) {
            throw new \UnexpectedValueException('Invalid registration response from licence server.');
        }
        set_config('managed_installation_id', $id, self::COMPONENT);
        set_config('managed_installation_secret', $secret, self::COMPONENT);
        self::$requeststatus = null;
        // Do not persist a personally identifiable e-mail address in the plugin.
        return ['state' => 'pending', 'notification_status' =>
            in_array(($result['notification_status'] ?? ''), ['sent', 'failed'], true)
                ? $result['notification_status'] : 'unknown'];
    }

    /**
     * Server status: no local date arithmetic, fail closed if unreachable.
     */
    public static function status(): array {
        if (self::$requeststatus !== null) {
            return self::$requeststatus;
        }
        if (!self::is_registered()) {
            return ['state' => 'unregistered', 'active' => false, 'trial_ends_at' => null];
        }
        $response = self::request('installations/status', ['installation_id' => self::get_installation_id()]);
        $parsed = activation_state::from_server($response);
        $parsed += activation_state::billing_from_server(
            $response,
            (string)parse_url(self::api_address(), PHP_URL_HOST)
        );
        $parsed['active'] = activation_state::can_use($parsed, time());
        // Never cache failures: an unreachable/invalid license must fail closed.
        self::$requeststatus = $parsed;
        return $parsed;
    }

    /**
     * Activate license.
     *
     * @param string $key The key.
     * @return array The result.
     */
    public static function activate_license(string $key): array {
        if (!self::is_registered()) {
            throw new \moodle_exception('notregistered', self::COMPONENT);
        }
        $key = strtoupper(trim($key));
        if (!preg_match('/^FK2-(?:[A-F0-9]{5}-){7}[A-F0-9]{5}$/D', $key)) {
            throw new \invalid_parameter_exception('Invalid licence key format.');
        }
        $response = self::request('installations/activate-license', [
            'installation_id' => self::get_installation_id(), 'license_key' => $key,
        ]);
        self::$requeststatus = null;
        return activation_state::from_server($response);
    }

    /**
     * Require active.
     */
    public static function require_active(): void {
        $status = self::status();
        if (empty($status['active'])) {
            throw new \moodle_exception('managedinactive', self::COMPONENT);
        }
    }

    /**
     * Only metadata registered. No content transmission or MaxKB provisioning at this stage.
     *
     * @param \stdClass $course The course.
     * @return array The result.
     */
    public static function register_course(\stdClass $course): array {
        self::require_active();
        if ((int)$course->id <= 1) {
            throw new \invalid_parameter_exception('Invalid course ID.');
        }
        $response = self::request('courses/register', [
            'installation_id' => self::get_installation_id(),
            'course_id' => (int)$course->id,
            'course_name' => \core_text::substr(trim((string)$course->fullname), 0, 250),
        ]);
        foreach (['course_key', 'knowledge_name', 'domain_folder', 'provision_state'] as $key) {
            if (!isset($response[$key]) || !is_string($response[$key])) {
                throw new \UnexpectedValueException('Invalid course registry response.');
            }
        }
        $expected = course_binding_guard::course_key(self::get_installation_id(), (int)$course->id);
        if (!hash_equals($expected, $response['course_key'])) {
            throw new managed_api_diagnostic_exception(
                'DIAG-COURSE-KEY: The licence server registered a mismatching course identity.'
            );
        }
        return $response;
    }

    /**
     * Ensure the server-side course registry exists without re-registering an
     * existing course on every synchronisation. A 404 means the course is not
     * registered; other HTTP errors MUST NOT trigger a fresh registration.
     *
     * The response is bound to the installation ID and course ID using the
     * same deterministic course key as the central licence server.
     *
     * @param \stdClass $course The course.
     * @return array The result.
     */
    public static function ensure_course_registered(\stdClass $course): array {
        self::require_active();
        $courseid = (int)$course->id;
        if ($courseid <= 1) {
            throw new \invalid_parameter_exception('Invalid course ID.');
        }
        try {
            $response = self::request('courses/status', [
                'installation_id' => self::get_installation_id(),
                'course_id' => $courseid,
            ]);
        } catch (managed_api_diagnostic_exception $e) {
            // Only a genuine missing course permits creating a new registry entry.
            if ($e->getCode() !== 404) {
                throw $e;
            }
            try {
                return self::apply_binding_reset($courseid, self::register_course($course));
            } catch (managed_api_diagnostic_exception $registererror) {
                if ($registererror->getCode() !== 409) {
                    throw $registererror;
                }
                // Older servers may not implement courses/status but already
                // have this course registered. Reconcile ONLY an existing local
                // course mapping against an idempotent, verified server binding.
                $prior = course_agent_manager::get_record($courseid);
                if (!$prior || empty($prior->knowledgeid) || empty($prior->agentid)) {
                    throw $registererror;
                }
                $verified = self::provision_course($course);
                course_binding_guard::assert_stable(
                    $prior,
                    (string)$verified['knowledge_id'],
                    (string)$verified['agent_id']
                );
                return [
                    'course_key' => course_binding_guard::course_key(self::get_installation_id(), $courseid),
                    'provision_state' => 'ready',
                ];
            }
        }
        $expected = course_binding_guard::course_key(self::get_installation_id(), $courseid);
        if (
            !isset($response['course_key']) || !is_string($response['course_key']) ||
                !hash_equals($expected, $response['course_key'])
        ) {
            // Never create or overwrite a registry mapping if the ownership
            // response is inconsistent. Requires an administrator's inspection.
            throw new managed_api_diagnostic_exception(
                'DIAG-COURSE-KEY: The licence server course mapping is not consistent.'
            );
        }
        if (!isset($response['provision_state']) || !is_string($response['provision_state'])) {
            throw new managed_api_diagnostic_exception(
                'DIAG-COURSE-STATUS: The licence server course status is incomplete.'
            );
        }
        return self::apply_binding_reset($courseid, $response);
    }

    /**
     * Forget the local binding if the licence server reports that the administrator
     * deleted the previous course agent and knowledge base and released the course.
     *
     * The flag is only honoured as a strict boolean true, and only while the locally
     * stored IDs still differ from the IDs the server reports for the new binding. The
     * server keeps the flag until the new agent is ready; this comparison makes the reset
     * happen exactly once, also across several synchronisation stages. The result carries
     * 'binding_reset_applied' so the caller can inform the teacher.
     *
     * @param int $courseid The course id.
     * @param array $response The licence server response.
     * @return array The response.
     */
    private static function apply_binding_reset(int $courseid, array $response): array {
        $response['binding_reset_applied'] = false;
        if (($response['binding_reset'] ?? false) !== true) {
            return $response;
        }
        $local = course_agent_manager::get_record($courseid);
        if (!$local) {
            return $response;
        }
        $serverknowledge = is_string($response['knowledge_id'] ?? null) ? $response['knowledge_id'] : '';
        $serveragent = is_string($response['agent_id'] ?? null) ? $response['agent_id'] : '';
        $localknowledge = (string)($local->knowledgeid ?? '');
        $localagent = (string)($local->agentid ?? '');
        $stale = (string)$local->status === 'detached'
            || ($localknowledge !== '' && $localknowledge !== $serverknowledge)
            || ($localagent !== '' && $localagent !== $serveragent);
        if ($stale) {
            $response['binding_reset_applied'] = course_agent_manager::reset_binding($courseid);
        }
        return $response;
    }

    /**
     * Prepare or recover the server-owned course knowledge base, without touching the agent.
     *
     * @param \stdClass $course The course.
     * @return array The result.
     */
    public static function prepare_knowledge(\stdClass $course): array {
        self::require_active();
        // Report the knowledge base Moodle still knows. The licence server confirms it as
        // replaced only if it verifiably no longer exists in this customer's folder.
        $local = course_agent_manager::get_record((int)$course->id);
        $known = $local ? (string)($local->knowledgeid ?? '') : '';
        $out = self::request('courses/prepare-knowledge', [
            'installation_id' => self::get_installation_id(), 'course_id' => (int)$course->id,
            'known_knowledge_id' => $known,
            'known_agent_id' => $local ? (string)($local->agentid ?? '') : '',
        ]);
        // A previously provisioned course may be returned as 'ready' instead of
        // 'knowledge_ready'. It is acceptable only with verified agent binding.
        $state = (string)($out['provision_state'] ?? '');
        if (
            !in_array($state, ['knowledge_ready', 'ready'], true) ||
            ($state === 'ready' && ($out['binding_verified'] ?? false) !== true) ||
            !is_string($out['knowledge_id'] ?? null) ||
            !preg_match('/^[A-Za-z0-9_-]{8,100}$/D', $out['knowledge_id'])
        ) {
            throw new managed_api_diagnostic_exception('DIAG-KNOWLEDGE-RESPONSE: Knowledge base not confirmed.');
        }
        if (($out['binding_verified'] ?? false) === true) {
            if (
                !is_string($out['agent_id'] ?? null) ||
                !preg_match('/^[A-Za-z0-9_-]{8,100}$/D', $out['agent_id'])
            ) {
                throw new managed_api_diagnostic_exception(
                    'DIAG-AGENT-REUSE: The server could not confirm the stored agent identifier.'
                );
            }
            course_binding_guard::assert_available(
                (int)$course->id,
                (string)$out['knowledge_id'],
                (string)$out['agent_id']
            );
        }
        return $out;
    }

    /**
     * Prepare a course knowledge base on v2 servers. Older managed servers may
     * implement only the idempotent courses/provision endpoint. Fall back only
     * for a missing API route, never for licensing, network or KB failures.
     *
     * @param \stdClass $course The course.
     * @return array{knowledge_id:string,agent_id?:string,binding_verified?:bool,provision_state:string}
     */
    public static function prepare_or_provision_knowledge(\stdClass $course): array {
        try {
            return self::prepare_knowledge($course);
        } catch (managed_api_diagnostic_exception $e) {
            if (!in_array($e->getCode(), [404, 405], true)) {
                throw $e;
            }
            // A backwards-compatible managed server creates a distinct knowledge
            // base and agent in one verified step. This operation MUST be idempotent
            // for (installation_id, course_id). Validate the same isolation rules.
            $result = self::provision_course($course);
            return [
                'knowledge_id' => (string)$result['knowledge_id'],
                'agent_id' => (string)$result['agent_id'],
                'binding_verified' => true,
                'provision_state' => 'knowledge_ready',
                'compatibility_provisioned' => true,
            ];
        }
    }

    /**
     * Create or reuse resources through the central service. No admin credentials in Moodle.
     *
     * @param \stdClass $course The course.
     * @return array The result.
     */
    public static function provision_course(\stdClass $course): array {
        self::require_active();
        $out = null;
        // Report the course agent Moodle still knows; the licence server confirms a
        // replacement only if that agent verifiably no longer exists in FredKI.
        $local = course_agent_manager::get_record((int)$course->id);
        $knownagent = $local ? (string)($local->agentid ?? '') : '';
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $out = self::request('courses/provision', [
                    'installation_id' => self::get_installation_id(), 'course_id' => (int)$course->id,
                    'known_agent_id' => $knownagent,
                ]);
                break;
            } catch (managed_api_diagnostic_exception $e) {
                // Only the typed *file-lock* conflict is safely retryable.
                // A tenant/agent/knowledge mismatch with HTTP 409 must surface.
                if (!str_contains($e->getMessage(), 'DIAG-COURSE-LOCK-BUSY') || $attempt === 2) {
                    throw $e;
                }
                sleep($attempt + 1);
            }
        }
        if (
            ($out['provision_state'] ?? '') !== 'ready' || ($out['binding_verified'] ?? null) !== true ||
                !is_string($out['agent_id'] ?? null) || !is_string($out['knowledge_id'] ?? null) ||
                !preg_match('/^[A-Za-z0-9_-]{8,100}$/D', $out['agent_id']) ||
                !preg_match('/^[A-Za-z0-9_-]{8,100}$/D', $out['knowledge_id'])
        ) {
            throw new \UnexpectedValueException('FredKI provisioning incomplete.');
        }
        // The agent copy must inherit the AI model of the template. A server that reports the opposite
        // must not be silently accepted: the chat would later fail with an empty answer.
        if (array_key_exists('model_configured', $out) && $out['model_configured'] !== true) {
            throw new managed_api_diagnostic_exception(
                'DIAG-AGENT-MODEL: The course agent was created without an AI model. ' .
                'The licence server must copy the model of the agent template.'
            );
        }
        course_binding_guard::assert_available(
            (int)$course->id,
            (string)$out['knowledge_id'],
            (string)$out['agent_id']
        );
        return $out;
    }


    /**
     * Send course documents server-to-server. No FredKI account secrets in Moodle.
     *
     * @param int $courseid The courseid.
     * @param string $filekey The filekey.
     * @param string $contenthash The contenthash.
     * @param string $file The file.
     * @param string $filename The filename.
     * @param string $sourceurl The sourceurl.
     * @return array The result.
     */
    public static function upload_document(
        int $courseid,
        string $filekey,
        string $contenthash,
        string $file,
        string $filename,
        string $sourceurl
    ): array {
        global $CFG;
        self::require_active();
        require_once($CFG->libdir . '/filelib.php');
        $endpoint = self::document_url('sync');
        $curl = new \curl();
        $curl->setHeader([
        'Accept: application/json',
        'Authorization: Bearer ' . (string)get_config(self::COMPONENT, 'managed_installation_secret'),
        ]);
        $raw = $curl->post($endpoint, [
        'installation_id' => self::get_installation_id(),
        'course_id' => (string)$courseid,
        'file_key' => $filekey, 'content_hash' => $contenthash,
        'filename' => $filename, 'source_url' => $sourceurl,
        'file' => new \CURLFile($file, 'application/octet-stream', $filename),
        ], ['CURLOPT_TIMEOUT' => 240, 'CURLOPT_CONNECTTIMEOUT' => 15]);
        $info = $curl->get_info();
        $http = (int)($info['http_code'] ?? 0);
        $parsed = json_decode((string)$raw, true);
        if (
            $curl->get_errno() || $http < 200 || $http >= 300 || !is_array($parsed) ||
            !in_array(($parsed['state'] ?? ''), ['synced', 'unchanged'], true)
        ) {
            // Only surface explicit, enumerated server-side diagnostic codes. No
            // remote response body, filename, token, URL or user data is exposed.
            $servercode = (string)($parsed['diagnostic_code'] ?? '');
            $allowed = '/^DIAG-FK-(SPLIT|CREATE|AUTH|CONNECT)-HTTP-(0|[1-5][0-9]{2})$/D';
            if (preg_match($allowed, $servercode)) {
                throw new managed_api_diagnostic_exception($servercode);
            }
            throw new managed_api_diagnostic_exception('DIAG-DOCUMENT-HTTP-' . ($http ?: 'NETWORK'));
        }
        if (
            !isset($parsed['document_id']) || !is_string($parsed['document_id']) ||
            !preg_match('/^[A-Za-z0-9_-]{8,100}$/D', $parsed['document_id'])
        ) {
            throw new managed_api_diagnostic_exception('DIAG-DOCUMENT-ID-MISSING');
        }
        return $parsed;
    }

    /**
     * Delete document.
     *
     * @param int $courseid The course id.
     * @param string $filekey The filekey.
     * @return array The result.
     */
    public static function delete_document(int $courseid, string $filekey): array {
        global $CFG;
        self::require_active();
        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl();
        $curl->setHeader([
        'Content-Type: application/json', 'Accept: application/json',
        'Authorization: Bearer ' . (string)get_config(self::COMPONENT, 'managed_installation_secret'),
        ]);
        $raw = $curl->post(self::document_url('delete'), json_encode([
        'installation_id' => self::get_installation_id(),
        'course_id' => $courseid, 'file_key' => $filekey,
        ], JSON_THROW_ON_ERROR), ['CURLOPT_TIMEOUT' => 90]);
        $info = $curl->get_info();
        $http = (int)($info['http_code'] ?? 0);
        $parsed = json_decode((string)$raw, true);
        if (!$curl->get_errno() && $http === 404) {
            // The document is already gone on the server: deleting twice is not an error.
            return ['state' => 'deleted'];
        }
        $state = is_array($parsed) ? (string)($parsed['state'] ?? '') : '';
        $acceptable = in_array($state, ['deleted', 'not_found', 'already_deleted'], true);
        if (!$curl->get_errno() && $http >= 200 && $http < 300 && $acceptable) {
            return ['state' => 'deleted'];
        }
        $suffix = preg_match('/^[a-z_]{1,20}$/D', $state) ? '-' . $state : '';
        throw new managed_api_diagnostic_exception('DIAG-DOCUMENT-DELETE-' . ($http ?: 'NETWORK') . $suffix);
    }

    /**
     * Document url.
     *
     * @param string $action The action.
     * @return string The result.
     */
    private static function document_url(string $action): string {
        $base = self::api_address();
        $url = parse_url($base);
        if (
            !is_array($url) || empty($url['host']) ||
            !in_array(($url['scheme'] ?? ''), ['https', 'http'], true) ||
            isset($url['user']) || isset($url['pass']) || isset($url['query']) ||
            isset($url['fragment']) || !str_ends_with((string)($url['path'] ?? ''), '/api/v1/index.php')
        ) {
            throw new managed_api_diagnostic_exception('DIAG-CONFIG: Unknown licence server API address.');
        }
        if (($url['scheme'] ?? '') === 'http') {
            global $CFG;
            if (
                !in_array(
                    strtolower((string)parse_url($CFG->wwwroot, PHP_URL_HOST)),
                    ['localhost', '127.0.0.1'],
                    true
                ) ||
                !in_array(strtolower((string)$url['host']), ['localhost', '127.0.0.1'], true)
            ) {
                throw new managed_api_diagnostic_exception('DIAG-CONFIG: Documents may only be transferred over HTTPS.');
            }
        }
        return substr($base, 0, -strlen('index.php')) . 'documents.php?action=' . rawurlencode($action);
    }

    /**
     * Server-bound agent lookup: never accept agent IDs from browser/client input.
     *
     * @param int $courseid The courseid.
     * @param string $message The message.
     * @param array $history The history.
     * @return string The result.
     */
    public static function send_chat(int $courseid, string $message, array $history): string {
        self::require_active();
        if ($courseid < 2 || trim($message) === '' || \core_text::strlen($message) > 5000 || count($history) > 20) {
            throw new \invalid_parameter_exception('Invalid chat input.');
        }
        // The agent used for answering is chosen by the central server from the course binding
        // (installation_id + course_id). Before sending, make sure the locally stored binding of
        // THIS course is not shared with any other Moodle course (second line of defence).
        $local = course_agent_manager::get_record($courseid);
        if ($local && !empty($local->agentid) && !empty($local->knowledgeid)) {
            course_binding_guard::assert_available($courseid, (string)$local->knowledgeid, (string)$local->agentid);
        }
        $out = self::request('chat/send', [
            'installation_id' => self::get_installation_id(), 'course_id' => $courseid,
            'message' => $message, 'history' => $history,
        ]);
        if (!isset($out['answer']) || !is_string($out['answer'])) {
            throw new \UnexpectedValueException('Invalid chat response.');
        }
        // If the server reports which agent answered, it MUST be this course's agent.
        if (
            $local && !empty($local->agentid) && isset($out['agent_id']) && is_string($out['agent_id']) &&
                $out['agent_id'] !== '' && !hash_equals((string)$local->agentid, $out['agent_id'])
        ) {
            throw new managed_api_diagnostic_exception(
                'DIAG-CHAT-AGENT-MISMATCH: The reply did not come from the agent of this course and was discarded.'
            );
        }
        return $out['answer'];
    }

    /**
     * Administrative connection test. Does not register a customer or consume a trial.
     */
    public static function diagnose_connection(): array {
        global $CFG;
        $host = strtolower((string)parse_url((string)$CFG->wwwroot, PHP_URL_HOST));
        try {
            $response = self::request('diagnostics/ping', ['probe' => true], false);
            if (($response['status'] ?? '') !== 'ok' || (int)($response['trial_days'] ?? 0) !== 7) {
                throw new managed_api_diagnostic_exception(
                    'DIAG-RESPONSE: The licence server responded but returned an unknown API version.'
                );
            }
            return ['ok' => true, 'message' => 'Connection successful. Licence server reachable, API ready, 7-day rule active.'];
        } catch (managed_api_diagnostic_exception $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        } catch (\Throwable $e) {
            debugging('[FredKI connection test] unexpected exception: ' . get_class($e), DEBUG_DEVELOPER);
            return [
                'ok' => false,
                'message' => 'The connection could not be checked. Please check the Moodle PHP error logs and ' .
                    'the server address.',
            ];
        }
    }

    /**
     * Whitelist of safe details shown only to an administrator.
     *
     * @param \Throwable $e The e.
     * @return string The result.
     */
    public static function safe_error(\Throwable $e): string {
        if ($e instanceof managed_api_diagnostic_exception) {
            return $e->getMessage();
        }
        debugging('[FredKI activation] unexpected exception: ' . get_class($e), DEBUG_DEVELOPER);
        return 'The action could not be completed. Please check the connection status; ' .
            'if it fails again, check the Moodle PHP error log.';
    }

    /**
     * Request.
     *
     * @param string $route The route.
     * @param array $body The body.
     * @param bool $authenticated The authenticated.
     * @return array The result.
     */
    private static function request(string $route, array $body, bool $authenticated = true): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $configured = self::api_address();
        if ($configured === '') {
            throw new managed_api_diagnostic_exception('DIAG-CONFIG: Licence server API address is not configured.');
        }
        $urlparts = parse_url($configured);
        if (
            !is_array($urlparts) || empty($urlparts['host']) || !in_array(($urlparts['scheme'] ?? ''), ['https', 'http'], true)
                || isset($urlparts['user']) || isset($urlparts['pass']) || isset($urlparts['query'])
                || isset($urlparts['fragment'])
        ) {
            throw new managed_api_diagnostic_exception('DIAG-CONFIG: Licence server API address is invalid.');
        }
        // HTTP is permissible only for two services running locally on the same test machine.
        if ($urlparts['scheme'] === 'http') {
            $moodlehost = strtolower((string)parse_url((string)$CFG->wwwroot, PHP_URL_HOST));
            $remotehost = strtolower((string)$urlparts['host']);
            // Plain HTTP is accepted only if both Moodle and the service run on loopback
            // (local development). Public installations always require HTTPS.
            if (
                !in_array($moodlehost, ['localhost', '127.0.0.1'], true)
                    || !in_array($remotehost, ['localhost', '127.0.0.1'], true)
            ) {
                throw new managed_api_diagnostic_exception(
                    'DIAG-LOCAL: Plain HTTP is only allowed for a local Moodle with a local licence server.'
                );
            }
        }
        $base = rtrim($configured, '/');
        $url = str_ends_with($base, '.php')
            ? $base . '?route=' . rawurlencode($route)
            : $base . '/' . $route;
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if ($authenticated) {
            $secret = (string)get_config(self::COMPONENT, 'managed_installation_secret');
            if (!preg_match('/^[0-9a-f]{64}$/D', $secret)) {
                throw new \RuntimeException('No valid FredKI installation secret.');
            }
            $headers[] = 'Authorization: Bearer ' . $secret;
        }
        $curl = new \curl();
        $curl->setHeader($headers);
        $raw = $curl->post($url, json_encode($body, JSON_THROW_ON_ERROR), [
            'CURLOPT_CONNECTTIMEOUT' => 4,
            'CURLOPT_TIMEOUT' => in_array($route, ['chat/send', 'courses/provision', 'courses/prepare-knowledge'], true) ? 120 : 12,
            'CURLOPT_FOLLOWLOCATION' => false,
            'CURLOPT_SSL_VERIFYPEER' => true,
            'CURLOPT_SSL_VERIFYHOST' => 2,
        ]);
        $info = $curl->get_info();
        $code = (int)($info['http_code'] ?? 0);
        if (!in_array($code, [200, 201], true)) {
            // NEVER disclose raw HTTP error pages, curl error strings or request/response bodies.
            // Those may contain confidential values. These codes are safe for site administrators.
            $curlerror = strtolower((string)($curl->error ?? ''));
            if ($code === 0) {
                if (str_contains($curlerror, 'block') || str_contains($curlerror, 'gesperrt')) {
                    $message = 'DIAG-BLOCK: Moodle blocked the local URL through its HTTP security rules.';
                } else if ((int)($curl->errno ?? 0) === 28) {
                    $message = 'DIAG-TIMEOUT: Timeout while connecting to the licence server.';
                } else {
                    $message = 'DIAG-NETWORK: No HTTP connection. Check local URL, proxy, web server and Moodle HTTP security.';
                }
            } else {
                $errorresponse = json_decode((string)$raw, true);
                if (
                    $code === 410 && is_array($errorresponse)
                        && ($errorresponse['error_code'] ?? '') === 'FK-COURSE-DETACHED'
                ) {
                    throw new managed_api_diagnostic_exception(
                        'DIAG-COURSE-DETACHED: The FredKI administrator disconnected this course.',
                        410
                    );
                }
                if (
                    $route === 'courses/register' && $code === 402 && is_array($errorresponse)
                        && ($errorresponse['error_code'] ?? '') === 'FK-COURSE-LIMIT'
                ) {
                    throw new managed_api_diagnostic_exception(
                        'DIAG-COURSE-LIMIT: The course limit of the booked FredKI package is reached.',
                        402
                    );
                }
                if ($route === 'chat/send' && $code === 424) {
                    throw new managed_api_diagnostic_exception(
                        'DIAG-CHAT-EMPTY: FredKI produced no answer text; check the AI model and agent workflow.',
                        424
                    );
                }
                if (
                    $route === 'courses/provision' && $code === 409 && is_array($errorresponse)
                        && ($errorresponse['error_code'] ?? '') === 'FK-COURSE-LOCK-BUSY'
                ) {
                    throw new managed_api_diagnostic_exception(
                        'DIAG-COURSE-LOCK-BUSY: The course is currently being processed. Controlled retry.',
                        409
                    );
                }
                if ($route === 'courses/provision' && $code === 422 && is_array($errorresponse)) {
                    $reasons = [
                        'FK-AGENT-FOREIGN_FOLDER' => 'The existing agent is not in the customer folder. Please check the ' .
                            'mapping on the licence server.',
                        'FK-AGENT-TEMPLATE_AS_COURSE' => 'The agent template was mistakenly assigned as course agent.',
                        'FK-AGENT-SIMPLE_KB_MISMATCH' => 'The simple agent does not use the course knowledge base.',
                        'FK-AGENT-WORKFLOW_MISSING' => 'The stored workflow configuration is missing.',
                        'FK-AGENT-WORKFLOW_NO_SEARCH' => 'The workflow has no recognised knowledge retrieval node.',
                        'FK-AGENT-WORKFLOW_KB_MISMATCH' => 'A knowledge retrieval node does not use the course knowledge base.',
                        'FK-AGENT-UNSUPPORTED_TYPE' => 'The agent type is not recognised by the secure knowledge base check.',
                        'FK-AGENT-TYPE_CONFLICT' => 'The FredKI agent type is reported inconsistently.',
                    ];
                    $reason = (string)($errorresponse['error_code'] ?? '');
                    if (isset($reasons[$reason])) {
                        throw new managed_api_diagnostic_exception('DIAG-AGENT-422: ' . $reasons[$reason], 422);
                    }
                }
                if ($route === 'courses/prepare-knowledge' && $code === 503 && is_array($errorresponse)) {
                    $reasons = [
                        'FK-KB-CONFIGURATION' => 'Check the configuration or the fixed customer folder mapping.',
                        'FK-KB-PARENT_FOLDER' => 'Check the selected knowledge base parent folder.',
                        'FK-KB-FOLDER_LIST' => 'FredKI folder list is not readable.',
                        'FK-KB-CUSTOMER_FOLDER' => 'The customer subfolder could not be reconciled.',
                        'FK-KB-KB_LIST' => 'The list of course knowledge bases is not readable.',
                        'FK-KB-KB_CREATE' => 'The course knowledge base could not be created.',
                        'FK-KB-KB_READBACK' => 'Knowledge base is not readable after creation.',
                        'FK-KB-UNAVAILABLE' => 'Knowledge base is currently unavailable.',
                    ];
                    $reason = (string)($errorresponse['diagnostic_code'] ?? '');
                    if (isset($reasons[$reason])) {
                        throw new managed_api_diagnostic_exception('DIAG-KB-503: ' . $reasons[$reason], 503);
                    }
                }
                $messages = [
                    400 => 'DIAG-400: Request rejected by the licence server. Check the Moodle URL and API protocol.',
                    401 => 'DIAG-401: Authorisation rejected by the licence server.',
                    403 => 'DIAG-403: The licence server denied access. Check local-only mode or HTTP approval.',
                    404 => 'DIAG-404: API route not found. Check installation path and server patch.',
                    405 => 'DIAG-405: POST request was rejected. Check the API path and web server routing.',
                    409 => match ($route) {
                        'installations/register' => 'DIAG-INSTALLATION-409: This Moodle domain is already ' .
                            'registered. Reconnect the existing installation; do not ' .
                            'trigger a second registration.',
                        'courses/register' => 'DIAG-COURSE-409: Course mapping on the licence server is contradictory. ' .
                            'Check the course ID and installation identifier on the server.',
                        'courses/provision' => 'DIAG-PROVISION-409: The licence server is already processing this course ' .
                            'or a course provisioning is locked. Please wait for the ' .
                            'running operation and check the course status.',
                        default => 'DIAG-HTTP-409: Request conflict on the licence server (' . $route . ').',
                    },
                    415 => 'DIAG-415: The licence server rejected the JSON request format.',
                    410 => 'DIAG-COURSE-DETACHED: The FredKI administrator disconnected this course.',
                    429 => 'DIAG-429: Too many registration attempts. Rate limit reached.',
                    503 => 'DIAG-503: Licence server temporarily not ready. Check the initial setup and the PHP error log.',
                ];
                $message = $messages[$code] ?? 'DIAG-HTTP-' . $code . ': The licence server rejected the request.';
            }
            // The numeric HTTP status is used internally only to distinguish a
            // genuine missing course (404) from permissions/conflicts/network.
            throw new managed_api_diagnostic_exception($message, $code);
        }
        try {
            $result = json_decode((string)$raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new managed_api_diagnostic_exception(
                'DIAG-JSON: The server returned no valid JSON. Check PHP warnings or a wrong API address.'
            );
        }
        if (!is_array($result)) {
            throw new managed_api_diagnostic_exception('DIAG-RESPONSE: Invalid API response format.');
        }
        return $result;
    }
}

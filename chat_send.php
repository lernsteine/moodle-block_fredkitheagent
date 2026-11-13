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
 * Same-origin, course-authorised central chat relay. Always checks license.
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Access is checked by managed_chat_access::course() below (require_login, guest denial, course visibility, licence).
// phpcs:ignore moodle.Files.RequireLogin.Missing
require_once(__DIR__ . '/../../config.php');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit;
}
require_sesskey();
$courseid = required_param('courseid', PARAM_INT);
$message = trim(required_param('message', PARAM_RAW));
$threadid = required_param('threadid', PARAM_ALPHANUMEXT);
\block_fredkitheagent\local\managed_chat_access::course($courseid);
if (
    \core_text::strlen($message) < 1 || \core_text::strlen($message) > 5000 ||
    !preg_match('/^[A-Za-z0-9_-]{8,100}$/D', $threadid)
) {
    throw new \invalid_parameter_exception('Invalid chat request');
}
$key = 'c' . $courseid . '_u' . $USER->id . '_' . $threadid;
if (!isset($SESSION->fredki_theagent_history) || !is_array($SESSION->fredki_theagent_history)) {
    $SESSION->fredki_theagent_history = [];
}
$history = $SESSION->fredki_theagent_history[$key] ?? [];
if (!is_array($history)) {
    $history = [];
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
try {
    $answer = \block_fredkitheagent\local\managed_gateway::send_chat($courseid, $message, $history);
    if (trim($answer) === '') {
        throw new \block_fredkitheagent\local\managed_api_diagnostic_exception(
            'DIAG-CHAT-EMPTY: Empty chat answer',
            424
        );
    }
    $history[] = ['role' => 'user', 'content' => $message];
    $history[] = ['role' => 'assistant', 'content' => \core_text::substr($answer, 0, 4000)];
    $SESSION->fredki_theagent_history[$key] = array_slice($history, -10);
    if (count($SESSION->fredki_theagent_history) > 25) {
        $SESSION->fredki_theagent_history = array_slice($SESSION->fredki_theagent_history, -25, null, true);
    }
    echo json_encode(['ok' => true, 'answer' => $answer], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    if (
        $e instanceof \block_fredkitheagent\local\managed_api_diagnostic_exception
            && $e->getCode() === 410
    ) {
        // The administrator disconnected this course; hide the chat until it is set up again.
        \block_fredkitheagent\local\course_agent_manager::mark_detached($courseid);
        http_response_code(410);
        echo json_encode(['ok' => false, 'error_code' => 'COURSE_DETACHED',
            'error' => get_string('chatdetached', 'block_fredkitheagent')], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (
        $e instanceof \block_fredkitheagent\local\managed_api_diagnostic_exception
            && in_array($e->getCode(), [402, 429], true)
    ) {
        // Limits of the free package: show a clear notice instead of a generic error.
        $quota = $e->getCode() === 429;
        http_response_code($e->getCode());
        $notice = get_string($quota ? 'chatfreequota' : 'chatcourselimit', 'block_fredkitheagent');
        echo json_encode(['ok' => false, 'error_code' => $quota ? 'FREE_QUOTA' : 'COURSE_LIMIT',
            'error' => $notice], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // Return only allowlisted diagnostic categories, never raw upstream text.
    $empty = $e instanceof \block_fredkitheagent\local\managed_api_diagnostic_exception
        && $e->getCode() === 424;
    debugging('[FredKI The Agent protected chat] ' . get_class($e) . ($empty ? ' EMPTY_REPLY' : ''), DEBUG_DEVELOPER);
    http_response_code($empty ? 502 : 503);
    echo json_encode(['ok' => false, 'error_code' => $empty ? 'CHAT_EMPTY' : 'CHAT_UNAVAILABLE',
        'error' => get_string('chaterror', 'block_fredkitheagent')], JSON_UNESCAPED_UNICODE);
}

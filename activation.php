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
 * FredKI The Agent – simple manual approval; advanced controls stay collapsed.
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_login();
$syscontext = context_system::instance();
require_capability('moodle/site:config', $syscontext);
if (\block_fredkitheagent\local\version_scheme::repair()) {
    redirect(new moodle_url('/admin/index.php'));
}
$PAGE->set_url(new moodle_url('/blocks/fredkitheagent/activation.php'));
$PAGE->set_context($syscontext);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('managedactivation', 'block_fredkitheagent'));
$PAGE->set_heading(get_string('managedactivation', 'block_fredkitheagent'));
$gateway = \block_fredkitheagent\local\managed_gateway::class;
if (!$gateway::is_managed()) {
    throw new moodle_exception('managedonly', 'block_fredkitheagent');
}
$error = '';
$message = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_sesskey();
    $action = required_param('action', PARAM_ALPHA);
    try {
        switch ($action) {
            case 'register':
            case 'reconnect':
                $email = required_param('contactemail', PARAM_EMAIL);
                if ($action === 'reconnect') {
                    // The licence server no longer accepts the stored credentials: start over. If the site
                    // is still known there, the existing connection (package, courses) is restored.
                    unset_config('managed_installation_id', 'block_fredkitheagent');
                    unset_config('managed_installation_secret', 'block_fredkitheagent');
                }
                $result = $gateway::register($email);
                $message = get_string(
                    $result['state'] === 'reconnected' ? 'managedreconnected' : 'managedregistered',
                    'block_fredkitheagent'
                );
                break;
            case 'acceptlegal':
            case 'startfree':
                if (
                    !optional_param('acceptterms', 0, PARAM_BOOL)
                        || !optional_param('acceptavv', 0, PARAM_BOOL)
                ) {
                    throw new \block_fredkitheagent\local\managed_api_diagnostic_exception(
                        get_string('actfreestartaccept', 'block_fredkitheagent')
                    );
                }
                if ($action === 'acceptlegal') {
                    $gateway::accept_legal();
                    $message = get_string('actlegalaccepted', 'block_fredkitheagent');
                    break;
                }
                $gateway::start_free();
                $message = get_string('actfreestarted', 'block_fredkitheagent');
                break;
            case 'licence':
                // A package comes with a licence key, which is entered on this page.
                try {
                    $gateway::activate_license(required_param('licencekey', PARAM_ALPHANUMEXT));
                } catch (\block_fredkitheagent\local\managed_api_diagnostic_exception | \invalid_parameter_exception $e) {
                    $reasons = [400 => 'actlicenceunknown', 404 => 'actlicenceunknown', 409 => 'actlicenceconflict',
                        410 => 'actlicenceended', 429 => 'actlicencetoomany'];
                    $code = $e instanceof \invalid_parameter_exception ? 400 : (int)$e->getCode();
                    if (!isset($reasons[$code])) {
                        throw $e;
                    }
                    throw new \block_fredkitheagent\local\managed_api_diagnostic_exception(
                        get_string($reasons[$code], 'block_fredkitheagent')
                    );
                }
                $message = get_string('actlicenceactivated', 'block_fredkitheagent');
                break;
            case 'diagnose':
                $diagnosis = $gateway::diagnose_connection();
                if (!empty($diagnosis['ok'])) {
                    $message = (string)$diagnosis['message'];
                } else {
                    $error = (string)$diagnosis['message'];
                }
                break;
            case 'reset':
                if (required_param('confirmreset', PARAM_INT) !== 1) {
                    throw new invalid_parameter_exception('Connection change not confirmed.');
                }
                unset_config('managed_installation_id', 'block_fredkitheagent');
                unset_config('managed_installation_secret', 'block_fredkitheagent');
                $message = get_string('managedresetdone', 'block_fredkitheagent');
                break;
            default:
                throw new invalid_parameter_exception('Unsupported activation action');
        }
    } catch (\Throwable $e) {
        $error = $gateway::safe_error($e);
    }
    // A diagnostic or registration error is obsolete once the licence server reports the site as active.
    if ($error !== '' && in_array($action, ['diagnose', 'register', 'reconnect'], true)) {
        try {
            $current = $gateway::status();
            if (in_array((string)($current['state'] ?? ''), ['trial_active', 'licensed', 'setup_pending'], true)) {
                $error = '';
            }
        } catch (\Throwable $statuserror) {
            debugging('[FredKI activation status] ' . get_class($statuserror), DEBUG_DEVELOPER);
        }
    }
    // Post/Redirect/Get: reloading the page must never send the action again.
    $SESSION->block_fredkitheagent_activation = ['error' => $error, 'message' => $message];
    redirect($PAGE->url);
}
if (!empty($SESSION->block_fredkitheagent_activation) && is_array($SESSION->block_fredkitheagent_activation)) {
    $error = (string)($SESSION->block_fredkitheagent_activation['error'] ?? '');
    $message = (string)($SESSION->block_fredkitheagent_activation['message'] ?? '');
    unset($SESSION->block_fredkitheagent_activation);
}
$status = [];
$connectionlost = false;
try {
    $status = $gateway::status();
} catch (\Throwable $e) {
    debugging('[FredKI activation status] ' . get_class($e), DEBUG_DEVELOPER);
    // 401: the licence server no longer knows these credentials (connection removed or replaced).
    $connectionlost = $e instanceof \block_fredkitheagent\local\managed_api_diagnostic_exception && $e->getCode() === 401;
    if (!$connectionlost) {
        $error = $error ?: get_string('managedstatusunavailable', 'block_fredkitheagent');
    }
}
$state = (string)($status['state'] ?? 'unregistered');
// Take the opening statement of the FredKI template agent over (at most every six hours).
\block_fredkitheagent\local\welcome_sync::sync();
$pn = 'block_fredkitheagent';
$registered = $gateway::is_registered();
$active = in_array($state, ['trial_active', 'licensed', 'setup_pending'], true);
// Position in the three-step journey: 1 = register, 2 = approval, 3 = ready.
$position = !$registered || $connectionlost ? 1 : ($active ? 3 : 2);
$steps = [];
foreach (['actstepregister', 'actstepapproval', 'actstepready'] as $index => $key) {
    $number = $index + 1;
    $done = $number < $position || $position === 3;
    $steps[] = [
        'number' => $number, 'label' => get_string($key, $pn), 'done' => $done,
        'current' => !$done && $number === $position,
    ];
}
// Required checkboxes for the terms, privacy notice and data processing agreement.
$legalchecks = static function (array $legal) use ($pn): array {
    $link = static fn(string $url, string $text): string => html_writer::link(
        $url,
        s($text),
        ['target' => '_blank', 'rel' => 'noopener noreferrer']
    );
    return [
        ['name' => 'acceptterms', 'label' => get_string('actfreestartterms', $pn, (object)[
            'terms' => $link($legal['terms_url'], get_string('actlegalterms', $pn)),
            'privacy' => $link($legal['privacy_url'], get_string('actlegalprivacy', $pn)),
        ])],
        ['name' => 'acceptavv', 'label' => get_string('actfreestartavv', $pn, (object)[
            'avv' => $link($legal['avv_url'], get_string('actlegalavv', $pn)),
        ])],
    ];
};
$data = [
    'url' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'subtitle' => get_string('actsubtitle', $pn),
    'steps' => $steps,
    'register' => false,
    'pending' => false,
    'active' => false,
    'inactive' => false,
    'licenceform' => false,
    'registered' => $registered,
];
if (!$registered || $connectionlost) {
    $data['register'] = [
        'connectionlost' => $connectionlost,
        'action' => $connectionlost ? 'reconnect' : 'register',
        'buttonlabel' => get_string($connectionlost ? 'managedreconnectbutton' : 'managedregisterbutton', $pn),
    ];
} else if ($state === 'pending') {
    $legal = $status['legal'] ?? null;
    $pending = ['freestart' => is_array($legal) ? ['checks' => $legalchecks($legal)] : false, 'mailfallback' => false];
    if ((string)($status['notification_status'] ?? 'unknown') === 'failed') {
        // Mail is only a fallback if the host's outbound mail transport fails.
        $domain = (string)parse_url((string)$CFG->wwwroot, PHP_URL_HOST);
        $pending['mailfallback'] = ['mailto' => 'mailto:info@lernsteine.com?subject='
            . rawurlencode(get_string('activationmailsubject', $pn))
            . '&body=' . rawurlencode(get_string('activationmailbody', $pn, $domain))];
    }
    $data['pending'] = $pending;
} else if ($active) {
    $info = [
        'legalform' => !empty($status['legal_required']) && is_array($status['legal'] ?? null)
            ? ['checks' => $legalchecks($status['legal'])] : false,
        'trialend' => !empty($status['trial_ends_at'])
            ? get_string('managedtrialend', $pn) . ': ' . userdate((int)$status['trial_ends_at']) : false,
        'quota' => false,
        'usage' => false,
    ];
    $free = ($status['tier'] ?? '') === 'free';
    $meters = [];
    $quota = $status['message_quota'] ?? ($status['free_quota'] ?? null);
    if (is_array($quota)) {
        $a = (object)[
            'used' => (int)$quota['used'], 'limit' => (int)$quota['limit'],
            'reset' => !empty($quota['resets_at'])
                ? userdate((int)$quota['resets_at'], get_string('strftimedate', 'langconfig')) : '-',
        ];
        if ($a->used * 5 >= $a->limit * 4) {
            // From 80 %: a clear box in addition to the meter.
            $info['quota'] = \block_fredkitheagent\local\output_helper::quota($a, true, $free);
        }
        $meters[] = [
            'label' => get_string('actmetermessages', $pn),
            'value' => get_string('actmeterof', $pn, $a),
            'current' => min($a->used, $a->limit), 'max' => max(1, $a->limit),
            'sub' => get_string('actmeterreset', $pn, $a->reset),
        ];
    }
    if (!empty($status['course_limit'])) {
        $cu = (object)['used' => (int)($status['courses_used'] ?? 0), 'limit' => (int)$status['course_limit']];
        $meters[] = [
            'label' => get_string('actmetercourses', $pn),
            'value' => get_string('actmeterof', $pn, $cu),
            'current' => min($cu->used, $cu->limit), 'max' => max(1, $cu->limit), 'sub' => false,
        ];
    }
    $licence = false;
    if (!empty($status['licence'])) {
        $licence = get_string('actlicenceactive', $pn, $status['licence']['hint']);
        if (!empty($status['licence']['ends_at'])) {
            $licence .= ' · ' . get_string('actlicenceends', $pn, userdate(
                (int)$status['licence']['ends_at'],
                get_string('strftimedate', 'langconfig')
            ));
        }
    }
    if ($free || !empty($status['plan']) || $meters) {
        $info['usage'] = [
            'badge' => $free ? get_string('actbadgefree', $pn) : get_string('actpackage', $pn, (string)($status['plan'] ?? '')),
            'free' => $free,
            'badgesub' => $free ? get_string('actbadgefreesub', $pn) : false,
            'meters' => $meters,
            'licence' => $licence,
        ];
    }
    $data['active'] = $info;
    $data['licenceform'] = !(bool)$info['legalform'];
} else {
    $statelabel = get_string_manager()->string_exists('state' . $state, $pn) ? get_string('state' . $state, $pn) : $state;
    $data['inactive'] = get_string('managednotactive', $pn) . ': ' . $statelabel;
    $data['licenceform'] = in_array($state, ['revoked', 'trial_expired'], true);
}
echo $OUTPUT->header();
if ($error !== '') {
    echo $OUTPUT->notification($error, 'notifyproblem');
}
if ($message !== '') {
    echo $OUTPUT->notification($message, 'notifysuccess');
}
echo $OUTPUT->render_from_template('block_fredkitheagent/activation', $data);
echo $OUTPUT->footer();

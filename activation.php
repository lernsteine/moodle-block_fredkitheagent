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
echo $OUTPUT->header();
$pn = 'block_fredkitheagent';
if ($error !== '') {
    echo $OUTPUT->notification($error, 'notifyproblem');
}
if ($message !== '') {
    echo $OUTPUT->notification($message, 'notifysuccess');
}
$registered = $gateway::is_registered();
$active = in_array($state, ['trial_active', 'licensed', 'setup_pending'], true);
// Position in the three-step journey: 1 = register, 2 = approval, 3 = ready.
$position = !$registered || $connectionlost ? 1 : ($active ? 3 : 2);
// Package links come from the licence server and are already restricted to its https host.
$checkouturl = (string)($status['checkout_url'] ?? '');
$manageurl = (string)($status['manage_url'] ?? '');
// The booking and subscription pages use the language of this Moodle site (German or English).
$shoplang = str_starts_with(current_language(), 'de') ? 'de' : 'en';
$serverbutton = static function (string $url, string $label) use ($shoplang): string {
    $attributes = ['class' => 'fk-actbutton', 'target' => '_blank', 'rel' => 'noopener noreferrer'];
    return html_writer::link($url . '&lang=' . $shoplang, s($label), $attributes);
};
echo html_writer::start_div('fk-act');
echo html_writer::start_div('fk-head');
echo html_writer::div(html_writer::span('', 'fk-eye') . html_writer::span('', 'fk-eye'), 'fk-orb', ['aria-hidden' => 'true']);
echo html_writer::div(
    html_writer::tag('strong', get_string('pluginname', $pn)) .
    html_writer::tag('small', get_string('actsubtitle', $pn)),
    'fk-headcopy'
);
echo html_writer::end_div();
echo html_writer::start_tag('ol', ['class' => 'fk-steps']);
foreach (['actstepregister', 'actstepapproval', 'actstepready'] as $index => $key) {
    $number = $index + 1;
    $class = $number < $position || $position === 3 ? 'is-done' : ($number === $position ? 'is-current' : '');
    echo html_writer::tag(
        'li',
        html_writer::span($class === 'is-done' ? '✓' : (string)$number, 'fk-bubble') . s(get_string($key, $pn)),
        ['class' => trim('fk-step ' . $class)]
    );
}
echo html_writer::end_tag('ol');
echo html_writer::start_div('fk-actbody');
// Required checkboxes for the terms, privacy notice and data processing agreement.
$legalchecks = static function (array $legal) use ($pn): string {
    $link = static fn(string $url, string $text): string => html_writer::link(
        $url,
        s($text),
        ['target' => '_blank', 'rel' => 'noopener noreferrer']
    );
    $checks = [
        'acceptterms' => get_string('actfreestartterms', $pn, (object)[
            'terms' => $link($legal['terms_url'], get_string('actlegalterms', $pn)),
            'privacy' => $link($legal['privacy_url'], get_string('actlegalprivacy', $pn)),
        ]),
        'acceptavv' => get_string('actfreestartavv', $pn, (object)[
            'avv' => $link($legal['avv_url'], get_string('actlegalavv', $pn)),
        ]),
    ];
    $html = '';
    foreach ($checks as $name => $label) {
        $html .= html_writer::div(
            html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => $name, 'value' => 1,
                'id' => 'fk-' . $name, 'class' => 'form-check-input', 'required' => 'required']) .
            html_writer::tag('label', $label, ['for' => 'fk-' . $name, 'class' => 'form-check-label']),
            'form-check mb-2'
        );
    }
    return $html;
};
if (!$registered || $connectionlost) {
    if ($connectionlost) {
        echo html_writer::div(s(get_string('managedconnectionlost', $pn)), 'fk-actwarn');
    } else {
        echo html_writer::tag('p', s(get_string('managedregisterintro', $pn)));
    }
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action',
        'value' => $connectionlost ? 'reconnect' : 'register']);
    echo html_writer::tag('label', get_string('managedcontactemail', $pn), ['for' => 'contactemail']);
    echo html_writer::empty_tag('input', ['type' => 'email', 'name' => 'contactemail', 'id' => 'contactemail',
        'maxlength' => 254, 'required' => 'required', 'class' => 'form-control']);
    echo html_writer::empty_tag('input', ['type' => 'submit',
        'value' => get_string($connectionlost ? 'managedreconnectbutton' : 'managedregisterbutton', $pn),
        'class' => 'fk-actbutton']);
    echo html_writer::end_tag('form');
} else if ($state === 'pending') {
    $legal = $status['legal'] ?? null;
    if ($checkouturl !== '' && is_array($legal)) {
        // Free package first: the essential functionality needs no payment.
        echo html_writer::tag('p', s(get_string('actfreestartintro', $pn)));
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'startfree']);
        echo $legalchecks($legal);
        echo html_writer::empty_tag('input', ['type' => 'submit',
            'value' => get_string('actfreestartbutton', $pn), 'class' => 'fk-actbutton']);
        echo html_writer::end_tag('form');
    }
    if ($checkouturl !== '') {
        echo html_writer::tag('p', s(get_string('actbookintro', $pn)), ['class' => is_array($legal) ? 'mt-4' : '']);
        echo $serverbutton($checkouturl, get_string('actbookbutton', $pn));
    } else {
        echo html_writer::tag('p', get_string('actpendingcaption', $pn));
    }
    $mailstatus = (string)($status['notification_status'] ?? 'unknown');
    if ($mailstatus === 'failed') {
        echo html_writer::div(get_string('managednotificationfailed', $pn), 'fk-actwarn');
        // Mail is only a fallback if the host's outbound mail transport fails.
        global $CFG;
        $domain = (string)parse_url((string)$CFG->wwwroot, PHP_URL_HOST);
        $mail = 'mailto:info@lernsteine.com?subject=' . rawurlencode(get_string('activationmailsubject', $pn))
            . '&body=' . rawurlencode(get_string('activationmailbody', $pn, $domain));
        echo html_writer::link($mail, get_string('managedmailfallback', $pn), ['class' => 'fk-actbutton']);
    }
} else if ($active) {
    if (!empty($status['legal_required']) && is_array($status['legal'] ?? null)) {
        // Sites that never accepted the legal texts (e.g. approved before they were part of the start) do it once.
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url, 'class' => 'fk-actwarn']);
        echo html_writer::tag('p', s(get_string('actlegalrequired', $pn)));
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'acceptlegal']);
        echo $legalchecks($status['legal']);
        echo html_writer::empty_tag('input', ['type' => 'submit',
            'value' => get_string('actlegalconfirm', $pn), 'class' => 'fk-actbutton']);
        echo html_writer::end_tag('form');
    }
    echo html_writer::div(
        html_writer::tag('p', s(get_string('managedapproved', $pn))) .
        html_writer::tag('p', html_writer::tag('strong', s(get_string('managedapprovedslogan', $pn)))),
        'fk-actok'
    );
    if (!empty($status['trial_ends_at'])) {
        echo html_writer::div(
            s(get_string('managedtrialend', $pn) . ': ' . userdate((int)$status['trial_ends_at'])),
            'fk-caption'
        );
        echo html_writer::div(s(get_string('actaftertrial', $pn)), 'fk-caption');
    }
    if (($status['tier'] ?? '') === 'free') {
        echo html_writer::div(s(get_string('actfreepackage', $pn)), 'fk-caption');
        if (!empty($status['free_quota'])) {
            $quota = $status['free_quota'];
            $a = (object)[
                'used' => (int)$quota['used'], 'limit' => (int)$quota['limit'],
                'reset' => !empty($quota['resets_at'])
                    ? userdate((int)$quota['resets_at'], get_string('strftimedate', 'langconfig')) : '-',
            ];
            if ($a->used * 5 >= $a->limit * 4) {
                // From 80 %: a clear box instead of the small caption; the booking button follows below.
                $full = $a->used >= $a->limit;
                echo html_writer::div(
                    html_writer::tag('strong', s(get_string($full ? 'quotafulltitle' : 'quotanearlytitle', $pn, $a))) .
                    html_writer::tag('progress', s($a->used . ' / ' . $a->limit), ['max' => $a->limit,
                        'value' => min($a->used, $a->limit), 'class' => 'fk-quotabar',
                        'aria-label' => get_string('quotabarlabel', $pn, $a)]) .
                    html_writer::tag('p', s(get_string($full ? 'quotafulladmin' : 'quotanearlyadmin', $pn, $a))),
                    'fk-quota' . ($full ? ' is-full' : ''),
                    ['role' => 'status']
                );
            } else {
                echo html_writer::div(s(get_string('actfreequota', $pn, $a)), 'fk-caption');
            }
        }
    }
    if (!empty($status['plan']) && !empty($status['course_limit'])) {
        echo html_writer::div(s(get_string('actplan', $pn, (object)[
            'plan' => $status['plan'], 'used' => (int)($status['courses_used'] ?? 0), 'limit' => (int)$status['course_limit'],
        ])), 'fk-caption');
    }
    if ($checkouturl !== '') {
        echo $serverbutton($checkouturl, get_string('actbooknowbutton', $pn));
    }
    if ($manageurl !== '') {
        echo $serverbutton($manageurl, get_string('actmanagebutton', $pn));
    }
} else {
    $statelabel = get_string_manager()->string_exists('state' . $state, $pn) ? get_string('state' . $state, $pn) : $state;
    echo html_writer::div(s(get_string('managednotactive', $pn) . ': ' . $statelabel), 'fk-actwarn');
    if ($checkouturl !== '') {
        echo $serverbutton($checkouturl, get_string('actbooknowbutton', $pn));
    }
    if ($manageurl !== '') {
        echo $serverbutton($manageurl, get_string('actmanagebutton', $pn));
    }
}
echo html_writer::end_div();
// Technical diagnostics and connection reset are not part of the normal customer journey.
echo html_writer::start_tag('details', ['class' => 'mt-4']);
echo html_writer::tag('summary', get_string('managedadvanced', 'block_fredkitheagent'));
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url, 'class' => 'mt-3']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'diagnose']);
echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-secondary',
    'value' => get_string('manageddiagnosebutton', 'block_fredkitheagent')]);
echo html_writer::end_tag('form');
if ($gateway::is_registered()) {
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url, 'class' => 'mt-3']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'reset']);
    echo html_writer::tag(
        'label',
        html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'confirmreset', 'value' => '1',
        'required' => 'required']) . ' ' . get_string(
            'managedresetconfirm',
            'block_fredkitheagent'
        )
    );
    echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-outline-secondary mt-2',
        'value' => get_string('managedresetbutton', 'block_fredkitheagent')]);
    echo html_writer::end_tag('form');
}
echo html_writer::end_tag('details');
echo html_writer::end_div();
echo $OUTPUT->footer();

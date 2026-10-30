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
                $email = required_param('contactemail', PARAM_EMAIL);
                $result = $gateway::register($email);
                $message = get_string('managedregistered', 'block_fredkitheagent');
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
    if ($error !== '' && in_array($action, ['diagnose', 'register'], true)) {
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
try {
    $status = $gateway::status();
} catch (\Throwable $e) {
    debugging('[FredKI activation status] ' . get_class($e), DEBUG_DEVELOPER);
    $error = $error ?: get_string('managedstatusunavailable', 'block_fredkitheagent');
}
$state = (string)($status['state'] ?? 'unregistered');
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
$position = !$registered ? 1 : ($active ? 3 : 2);
// Package links come from the licence server and are already restricted to its https host.
$checkouturl = (string)($status['checkout_url'] ?? '');
$manageurl = (string)($status['manage_url'] ?? '');
$serverbutton = static function (string $url, string $label): string {
    return html_writer::link($url, s($label), ['class' => 'fk-actbutton', 'target' => '_blank', 'rel' => 'noopener noreferrer']);
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
if (!$registered) {
    echo html_writer::tag('p', get_string('managedregisterintro', $pn));
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'register']);
    echo html_writer::tag('label', get_string('managedcontactemail', $pn), ['for' => 'contactemail']);
    echo html_writer::empty_tag('input', ['type' => 'email', 'name' => 'contactemail', 'id' => 'contactemail',
        'maxlength' => 254, 'required' => 'required', 'class' => 'form-control']);
    echo html_writer::empty_tag('input', ['type' => 'submit',
        'value' => get_string('managedregisterbutton', $pn), 'class' => 'fk-actbutton']);
    echo html_writer::end_tag('form');
} else if ($state === 'pending') {
    if ($checkouturl !== '') {
        echo html_writer::tag('p', s(get_string('actbookintro', $pn)));
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
    echo html_writer::div(get_string('managednotactive', $pn) . ': ' . s($state), 'fk-actwarn');
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

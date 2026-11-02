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
 * Standalone, protected chat page in the Moodle course: no popup, public URL, or ChatCustom dependency.
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Access is checked by managed_chat_access::course() below (require_login, guest denial, course visibility, licence).
// phpcs:ignore moodle.Files.RequireLogin.Missing
require_once(__DIR__ . '/../../config.php');
$courseid = required_param('courseid', PARAM_INT);
$embedded = optional_param('embedded', 0, PARAM_BOOL);
$PAGE->set_url(new moodle_url('/blocks/fredkitheagent/chat.php', ['courseid' => $courseid, 'embedded' => $embedded]));
$course = \block_fredkitheagent\local\managed_chat_access::course($courseid);
$PAGE->set_context(context_course::instance($courseid));
$PAGE->set_course($course);
$PAGE->set_pagelayout($embedded ? 'embedded' : 'standard');
$PAGE->set_title('FredKI – ' . format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->css(new moodle_url(
    '/blocks/fredkitheagent/course_chat.css',
    ['v' => 2026103100]
));
if ($embedded) {
    $PAGE->add_body_class('fredkitheagent-embedded-chat');
}
// Load in the page footer, after the chat markup exists.
// The script also waits for DOMContentLoaded as a second safeguard.
$PAGE->requires->strings_for_js(
    ['chatdetached', 'chaterrorempty', 'chaterrorload', 'chatshowfullanswer'],
    'block_fredkitheagent'
);
$PAGE->requires->js(new moodle_url(
    '/blocks/fredkitheagent/course_chat.js',
    ['v' => 2026103100]
), false);
$color = static function (string $name, string $default): string {
    $value = (string)get_config('block_fredkitheagent', $name);
    return preg_match('/^#[0-9a-fA-F]{6}$/D', $value) ? $value : $default;
};
$design = \block_fredkitheagent\local\course_design::get($courseid);
$header = \block_fredkitheagent\local\course_design::color(
    $design,
    'headercolor',
    $color('chatheadercolor', '#e6f4f6')
);
$accent = \block_fredkitheagent\local\course_design::color(
    $design,
    'accentcolor',
    $color('chataccentcolor', '#007f91')
);
$background = \block_fredkitheagent\local\course_design::color(
    $design,
    'backgroundcolor',
    $color('chatbackgroundcolor', '#f5f7fa')
);
$botbubble = \block_fredkitheagent\local\course_design::color($design, 'botbubblecolor', '#eaf6f1');
$userbubble = \block_fredkitheagent\local\course_design::color($design, 'userbubblecolor', '#e3f2f5');
$welcome = trim((string)get_config('block_fredkitheagent', 'chatwelcometext'));
// 1.6.0: the agent role of the course brings its own opening statement and name.
$agentrecord = \block_fredkitheagent\local\course_agent_manager::get_record($courseid);
$rolename = '';
foreach ((\block_fredkitheagent\local\managed_gateway::status()['agent_roles'] ?? []) as $role) {
    if ($agentrecord && $role['id'] === (string)($agentrecord->agentrole ?? '')) {
        $rolename = $role['name'];
        if ($role['welcome'] !== '') {
            $welcome = $role['welcome'];
        }
    }
}
if ($design && trim((string)$design->welcometext) !== '') {
    $welcome = trim((string)$design->welcometext);
}
if ($welcome === '') {
    $welcome = get_string('chatwelcomedefault', 'block_fredkitheagent');
}
$footer = trim((string)get_config('block_fredkitheagent', 'chatfootertext'));
if ($design && trim((string)$design->footertext) !== '') {
    $footer = trim((string)$design->footertext);
}
if ($footer === '') {
    $footer = get_string('chatfooterdefault', 'block_fredkitheagent');
}
$courseavatar = \block_fredkitheagent\local\course_design::logo_url($courseid);
$logo = $courseavatar ?: $OUTPUT->image_url('fredki_the_agent', 'block_fredkitheagent')->out(false);
$fs = get_file_storage();
$files = $fs->get_area_files(
    context_system::instance()->id,
    'block_fredkitheagent',
    'chatlogo',
    0,
    'filename',
    false
);
foreach ($files as $file) {
    if (!$courseavatar && !$file->is_directory()) {
        $logo = moodle_url::make_pluginfile_url(
            context_system::instance()->id,
            'block_fredkitheagent',
            'chatlogo',
            0,
            '/',
            $file->get_filename()
        )->out(false);
        break;
    }
}
$endpoint = (new moodle_url('/blocks/fredkitheagent/chat_send.php'))->out(false);
// Generated server-side so a missing WebCrypto API cannot prevent attaching the chat.
$threadid = 'fk' . bin2hex(random_bytes(18));
$backurl = (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false);
header('Cache-Control: private, no-store');
header('Referrer-Policy: no-referrer');
echo $OUTPUT->header();
$vars = '--fredki-header:' . $header . ';--fredki-accent:' . $accent . ';--fredki-bg:' . $background .
    ';--fredki-botbubble:' . $botbubble . ';--fredki-userbubble:' . $userbubble . ';';
echo '<section class="fredki-course-chat" style="' . s($vars) . '" data-fredki-chat="1"'
    . ' data-courseid="' . (int)$courseid . '" data-endpoint="' . s($endpoint) . '" data-sesskey="' . s(sesskey()) . '"'
    . ' data-threadid="' . s($threadid) . '"'
    . ' data-typinglabel="' . s(get_string('chattyping', 'block_fredkitheagent')) . '">';
echo '<header class="fredki-course-header">'
    . ($embedded ? '' : '<a href="' . s($backurl) . '" class="fredki-course-back">' .
        s(get_string('chatbackcourse', 'block_fredkitheagent')) . '</a>')
    . '<img src="' . s($logo) . '" alt="FredKI" class="fredki-course-logo">'
    . '<div><strong>FredKI' . ($rolename !== '' ? ' · ' . s($rolename) : '') . '</strong><span>'
    . s(get_string('nextsubtitle', 'block_fredkitheagent'))
    . ' · ' . s(format_string($course->fullname)) . '</span></div>'
    . '</header>';
echo '<div class="fredki-course-messages" id="fredki-course-messages" aria-live="polite">'
    . '<div class="fredki-course-message bot">' . nl2br(s($welcome)) . '</div></div>';
// Fail closed when JavaScript is unavailable: the Send button is disabled until
// the fetch handler has been installed. Never navigate the iframe to a Moodle page.
echo '<form id="fredki-course-form" class="fredki-course-form" method="post" action="' . s($endpoint) . '">'
    . '<input type="hidden" name="courseid" value="' . (int)$courseid . '">'
    . '<input type="hidden" name="sesskey" value="' . s(sesskey()) . '">'
    . '<input type="hidden" name="threadid" value="' . s($threadid) . '">'
    . '<label class="sr-only" for="fredki-course-input">' . s(get_string('chatask', 'block_fredkitheagent')) . '</label>'
    . '<textarea id="fredki-course-input" name="message" rows="2" maxlength="5000" placeholder="' .
        s(get_string('chatask', 'block_fredkitheagent')) . '" required></textarea>'
    . '<button type="submit" class="fredki-course-send" disabled="disabled">' .
        s(get_string('chatsend', 'block_fredkitheagent')) . '</button>'
    . '</form>';
echo '<noscript><p class="alert alert-warning">' . s(get_string('chatneedsjavascript', 'block_fredkitheagent')) . '</p></noscript>';
echo '<footer class="fredki-course-footer">' . s($footer) . '</footer></section>';
echo $OUTPUT->footer();

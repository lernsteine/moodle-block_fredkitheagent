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
if ($embedded) {
    $PAGE->add_body_class('fredkitheagent-embedded-chat');
}
$PAGE->requires->js_call_amd('block_fredkitheagent/course_chat', 'init');
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
// The opening statement keeps its line breaks; every line is escaped by the template.
$welcomelines = preg_split('/\R/u', $welcome);
$lastline = (string)array_pop($welcomelines);
echo $OUTPUT->render_from_template('block_fredkitheagent/chat_page', [
    'style' => '--fredki-header:' . $header . ';--fredki-accent:' . $accent . ';--fredki-bg:' . $background .
        ';--fredki-botbubble:' . $botbubble . ';--fredki-userbubble:' . $userbubble . ';',
    'courseid' => (int)$courseid,
    'endpoint' => $endpoint,
    'sesskey' => sesskey(),
    'threadid' => $threadid,
    'embedded' => (bool)$embedded,
    'backurl' => $backurl,
    'logo' => $logo,
    'title' => 'FredKI' . ($rolename !== '' ? ' · ' . $rolename : ''),
    'subtitle' => get_string('nextsubtitle', 'block_fredkitheagent') . ' · ' . format_string($course->fullname),
    'welcomelines' => $welcomelines,
    'lastline' => $lastline,
    'footer' => $footer,
]);
echo $OUTPUT->footer();

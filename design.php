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
 * FredKI The Agent: per-course visual configuration for the existing protected chat.
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:update', $context);
$agent = \block_fredkitheagent\local\course_agent_manager::get_record($courseid);
if (!$agent || empty($agent->agentid)) {
    throw new moodle_exception('nopermissions', 'error');
}
$PAGE->set_url(new moodle_url('/blocks/fredkitheagent/design.php', ['courseid' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('coursedesign', 'block_fredkitheagent'));
$PAGE->set_heading(format_string($course->fullname));
$back = new moodle_url('/course/view.php', ['id' => $courseid]);
$design = \block_fredkitheagent\local\course_design::get($courseid);
$globalcolour = static function (string $name, string $fallback): string {
    $colour = (string)get_config('block_fredkitheagent', $name);
    return preg_match('/^#[0-9a-fA-F]{6}$/D', $colour) ? $colour : $fallback;
};
$colors = [
    'headercolor' => [
        'label' => get_string('designheadercolor', 'block_fredkitheagent'),
        'default' => $globalcolour('chatheadercolor', '#e6f4f6'),
    ],
    'accentcolor' => [
        'label' => get_string('designaccentcolor', 'block_fredkitheagent'),
        'default' => $globalcolour('chataccentcolor', '#007f91'),
    ],
    'backgroundcolor' => [
        'label' => get_string('designbackgroundcolor', 'block_fredkitheagent'),
        'default' => $globalcolour('chatbackgroundcolor', '#f5f7fa'),
    ],
    'botbubblecolor' => ['label' => get_string('designbotbubble', 'block_fredkitheagent'), 'default' => '#eaf6f1'],
    'userbubblecolor' => ['label' => get_string('designuserbubble', 'block_fredkitheagent'), 'default' => '#e3f2f5'],
];
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $reset = optional_param('resetdesign', 0, PARAM_BOOL);
    if ($reset) {
        $DB->delete_records('block_fredkitheagent_design', ['courseid' => $courseid]);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'block_fredkitheagent', 'courselogo', 0);
        redirect($PAGE->url, get_string('designreset', 'block_fredkitheagent'));
    }
    $new = (object)['courseid' => $courseid, 'agentid' => (string)$agent->agentid,
        'timemodified' => time()];
    foreach ($colors as $name => $cfg) {
        $value = trim(optional_param($name, $cfg['default'], PARAM_TEXT));
        if (!preg_match('/^#[0-9a-fA-F]{6}$/D', $value)) {
            throw new invalid_parameter_exception('Invalid chat colour');
        }
        $new->{$name} = strtolower($value);
    }
    $new->welcometext = trim(optional_param('welcometext', '', PARAM_TEXT));
    $new->footertext = trim(optional_param('footertext', '', PARAM_TEXT));
    if (core_text::strlen($new->welcometext) > 255 || core_text::strlen($new->footertext) > 255) {
        throw new invalid_parameter_exception('Chat text too long');
    }
    // Validate an optional course-specific logo before updating any design record.
    $imagepath = '';
    $imageext = '';
    if (
        isset($_FILES['courselogo']) && is_array($_FILES['courselogo']) &&
            (int)$_FILES['courselogo']['error'] !== UPLOAD_ERR_NO_FILE
    ) {
        $upload = $_FILES['courselogo'];
        if (
            (int)$upload['error'] !== UPLOAD_ERR_OK || (int)$upload['size'] > 2097152 ||
                !is_uploaded_file((string)$upload['tmp_name'])
        ) {
            throw new invalid_parameter_exception('Invalid image upload');
        }
        $info = @getimagesize($upload['tmp_name']);
        $mime = $info['mime'] ?? '';
        $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime]) || ($info[0] ?? 0) > 4000 || ($info[1] ?? 0) > 4000) {
            throw new invalid_parameter_exception('Unsupported image file');
        }
        $imageext = $extensions[$mime];
        $imagepath = $upload['tmp_name'];
    }
    $existing = $DB->get_record('block_fredkitheagent_design', ['courseid' => $courseid]);
    // A replaced course agent must not silently reuse the previous agent's avatar.
    if ($existing && !hash_equals((string)$existing->agentid, (string)$new->agentid)) {
        get_file_storage()->delete_area_files($context->id, 'block_fredkitheagent', 'courselogo', 0);
    }
    if ($existing) {
        $new->id = $existing->id;
        $DB->update_record('block_fredkitheagent_design', $new);
    } else {
        $DB->insert_record('block_fredkitheagent_design', $new);
    }
    $fs = get_file_storage();
    if ($imagepath !== '') {
        $fs->delete_area_files($context->id, 'block_fredkitheagent', 'courselogo', 0);
        $record = ['contextid' => $context->id, 'component' => 'block_fredkitheagent',
            'filearea' => 'courselogo', 'itemid' => 0, 'filepath' => '/',
            'filename' => 'kurslogo.' . $imageext, 'userid' => $USER->id];
        $fs->create_file_from_pathname($record, $imagepath);
    } else if (optional_param('removelogo', 0, PARAM_BOOL)) {
        $fs->delete_area_files($context->id, 'block_fredkitheagent', 'courselogo', 0);
    }
    redirect($PAGE->url, get_string('designsaved', 'block_fredkitheagent'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('coursedesign', 'block_fredkitheagent'));
echo html_writer::tag('p', get_string('coursedesignintro', 'block_fredkitheagent'));
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false),
    'enctype' => 'multipart/form-data', 'style' => 'max-width:680px']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
foreach ($colors as $name => $cfg) {
    $current = \block_fredkitheagent\local\course_design::color($design, $name, $cfg['default']);
    $input = html_writer::empty_tag('input', ['type' => 'color', 'name' => $name, 'id' => 'fna-' . $name,
        'value' => $current, 'class' => 'form-control', 'style' => 'width:90px;height:42px;padding:3px']);
    echo html_writer::div(
        html_writer::tag('label', s($cfg['label']), ['for' => 'fna-' . $name]) . $input,
        'mb-3 d-flex align-items-center justify-content-between'
    );
}
// Empty course texts use the global texts; show those as placeholder so teachers see what learners get.
$globaltext = static function (string $setting, string $fallback): string {
    $text = trim((string)get_config('block_fredkitheagent', $setting));
    return $text !== '' ? $text : get_string($fallback, 'block_fredkitheagent');
};
$texts = [
    'welcometext' => [get_string('chatwelcometext', 'block_fredkitheagent'), $globaltext('chatwelcometext', 'chatwelcomedefault')],
    'footertext' => [get_string('chatfootertext', 'block_fredkitheagent'), $globaltext('chatfootertext', 'chatfooterdefault')],
];
foreach ($texts as $name => [$label, $global]) {
    $value = $design ? (string)($design->{$name} ?? '') : '';
    echo html_writer::tag('label', s($label), ['for' => 'fna-' . $name]);
    echo html_writer::empty_tag('input', ['type' => 'text', 'name' => $name, 'id' => 'fna-' . $name,
        'value' => $value, 'maxlength' => 255, 'class' => 'form-control',
        'placeholder' => $global, 'aria-describedby' => 'fna-' . $name . '-hint']);
    $hintattrs = ['class' => 'text-muted small mt-1 mb-3', 'id' => 'fna-' . $name . '-hint'];
    echo html_writer::tag('p', s(get_string('designglobalhint', 'block_fredkitheagent')), $hintattrs);
}
echo html_writer::tag('label', get_string('designcourselogo', 'block_fredkitheagent'), ['for' => 'fna-logo']);
echo html_writer::empty_tag('input', ['type' => 'file', 'name' => 'courselogo', 'id' => 'fna-logo',
    'accept' => 'image/png,image/jpeg,image/webp', 'class' => 'form-control mb-2']);
echo html_writer::tag('p', get_string('designlogohint', 'block_fredkitheagent'), ['class' => 'text-muted small']);
if (\block_fredkitheagent\local\course_design::logo_url($courseid)) {
    echo html_writer::div(html_writer::checkbox(
        'removelogo',
        1,
        false,
        get_string('designremovelogo', 'block_fredkitheagent')
    ), 'mb-3');
}
echo html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'btn btn-primary mt-3']);
echo html_writer::end_tag('form');
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false), 'class' => 'mt-3']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'resetdesign', 'value' => 1]);
echo html_writer::tag(
    'button',
    get_string('designresetbutton', 'block_fredkitheagent'),
    ['type' => 'submit', 'class' => 'btn btn-outline-secondary']
);
echo html_writer::end_tag('form');
echo html_writer::div(
    html_writer::link($back, get_string('chatbackcourse', 'block_fredkitheagent')),
    'mt-4'
);
echo $OUTPUT->footer();

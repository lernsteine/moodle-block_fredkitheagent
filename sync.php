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
 * FredKI The Agent: sync.php
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
require_sesskey();
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    throw new moodle_exception('invalidrequest');
}
$context = context_course::instance($courseid);
require_capability('moodle/course:update', $context);
$fullcheck = optional_param('fullcheck', 0, PARAM_INT) ? true : false;
// Always inspect every Moodle course source. When fullcheck is requested, every
// current document is re-validated against the FredKI service so that missing
// knowledge-base documents are uploaded again even if the local mapping exists.

// A sync may take longer than the web server's proxy timeout. Let PHP finish what it started even
// if the browser connection is cut, and give the run a time budget after which it reports progress.
\core_php_time_limit::raise(600);
ignore_user_abort(true);
$budget = (float)max(10, min(120, (int)get_config('block_fredkitheagent', 'sync_time_budget') ?: 30));

$stage = get_string('syncstageregister', 'block_fredkitheagent');
$stats = null;
try {
    // Register/locate course by installation id + Moodle course id; never by domain alone.
    \block_fredkitheagent\local\managed_gateway::ensure_course_registered($course);
    $stage = get_string('syncstageknowledge', 'block_fredkitheagent');
    $prepared = \block_fredkitheagent\local\managed_gateway::prepare_or_provision_knowledge($course);
    $rec = \block_fredkitheagent\local\course_agent_manager::ensure_record($course);
    $knowledgeid = (string)$prepared['knowledge_id'];
    if (!empty($rec->knowledgeid) && !hash_equals((string)$rec->knowledgeid, $knowledgeid)) {
        throw new \block_fredkitheagent\local\managed_api_diagnostic_exception(
            'DIAG-COURSE-BINDING-CHANGED: The licence server returned a different course knowledge base.'
        );
    }
    if (($prepared['binding_verified'] ?? false) === true) {
        \block_fredkitheagent\local\course_binding_guard::assert_stable(
            $rec,
            $knowledgeid,
            (string)$prepared['agent_id']
        );
        \block_fredkitheagent\local\course_binding_guard::assert_available(
            $courseid,
            $knowledgeid,
            (string)$prepared['agent_id']
        );
    }
    \block_fredkitheagent\local\course_binding_guard::assert_knowledge_available($courseid, $knowledgeid);

    // Retain prior acknowledgements only if they belong to the SAME target. Every map row is
    // additionally validated individually (document id, no error, unchanged content), so a
    // failed or empty previous run does not force a complete re-upload on the next attempt.
    $trustedprior = (string)($rec->managedtargetid ?? '') === $knowledgeid &&
        (string)($rec->knowledgeid ?? '') === $knowledgeid;
    $rec->knowledgeid = $knowledgeid;
    $rec->status = 'sync_pending';
    // Do not clear known server ownership IDs or a verified snapshot BEFORE
    // an attempted upload. A failed run must be safely retryable.
    $rec->lasterror = null;
    $rec->timemodified = time();
    $DB->update_record('block_fredkitheagent', $rec);

    $stage = get_string('syncstagedocuments', 'block_fredkitheagent');
    $stats = \block_fredkitheagent\local\managed_content_sync::run(
        $course,
        $rec,
        $trustedprior,
        $fullcheck,
        $budget
    );
    $rec = \block_fredkitheagent\local\course_agent_manager::get_record($courseid);
    if (!$rec) {
        throw new \UnexpectedValueException(get_string('syncrecorddisappeared', 'block_fredkitheagent'));
    }

    if ((int)($stats['deferred'] ?? 0) > 0 && (int)($stats['failed'] ?? 0) === 0) {
        // Time budget reached: continue automatically with the next stage as long as progress is made.
        $done = (int)($stats['verified'] ?? 0);
        $total = (int)($stats['found'] ?? 0);
        $stagenumber = optional_param('stage', 0, PARAM_INT) + 1;
        $previousdone = optional_param('prevdone', -1, PARAM_INT);
        if ($stagenumber < 40 && $done > $previousdone) {
            $PAGE->set_url(new moodle_url('/blocks/fredkitheagent/sync.php'), ['courseid' => $courseid]);
            $PAGE->set_context($context);
            $PAGE->set_pagelayout('incourse');
            $PAGE->set_course($course);
            $PAGE->set_title(get_string('pluginname', 'block_fredkitheagent'));
            $PAGE->set_heading($course->fullname);
            $PAGE->requires->js_init_code(
                'setTimeout(function(){var f=document.getElementById("fredki-continue");if(f){f.submit();}},1200);'
            );
            echo $OUTPUT->header();
            echo $OUTPUT->heading(get_string('pluginname', 'block_fredkitheagent'), 3);
            echo html_writer::tag('p', s(get_string('syncautocontinue', 'block_fredkitheagent', (object)[
                'stage' => $stagenumber,
                'done' => $done,
                'total' => $total,
            ])));
            echo html_writer::start_tag('form', [
                'method' => 'post',
                'action' => (new moodle_url('/blocks/fredkitheagent/sync.php'))->out(false),
                'id' => 'fredki-continue',
            ]);
            foreach (['courseid' => $courseid, 'sesskey' => sesskey(), 'stage' => $stagenumber, 'prevdone' => $done] as $n => $v) {
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $n, 'value' => $v]);
            }
            echo html_writer::tag('button', s(get_string('syncautobutton', 'block_fredkitheagent')), [
                'type' => 'submit', 'class' => 'btn btn-primary',
            ]);
            echo html_writer::end_tag('form');
            echo $OUTPUT->footer();
            exit;
        }
        \core\notification::warning(get_string('syncnoticepartial', 'block_fredkitheagent', (object)[
            'done' => $done,
            'total' => $total,
        ]));
        redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
    }

    // The central server must issue a new agent ID for a new course, while
    // returning the same ID for an already provisioned course.
    $stage = get_string('syncstageagent', 'block_fredkitheagent');
    $result = ($prepared['binding_verified'] ?? false) === true
        ? ['knowledge_id' => $knowledgeid, 'agent_id' => (string)$prepared['agent_id'],
           'binding_verified' => true, 'provision_state' => 'ready']
        : \block_fredkitheagent\local\managed_gateway::provision_course($course);
    if (!hash_equals($knowledgeid, (string)$result['knowledge_id'])) {
        throw new \block_fredkitheagent\local\managed_api_diagnostic_exception(
            'DIAG-COURSE-BINDING-CHANGED: The agent points to a different knowledge base.'
        );
    }
    \block_fredkitheagent\local\course_binding_guard::assert_stable(
        $rec,
        $knowledgeid,
        (string)$result['agent_id']
    );
    \block_fredkitheagent\local\course_binding_guard::assert_available(
        $courseid,
        $knowledgeid,
        (string)$result['agent_id']
    );
    $rec->agentid = (string)$result['agent_id'];
    $documentcomplete = (int)($stats['found'] ?? 0) > 0 &&
        (int)($stats['failed'] ?? 0) === 0 &&
        (int)($stats['verified'] ?? 0) === (int)($stats['found'] ?? 0) &&
        (int)($rec->verifiedat ?? 0) > 0;
    $rec->status = $documentcomplete ? 'ready' : 'agent_created';
    $rec->lasterror = $documentcomplete ? null :
        (string)($stats['warning'] ?? get_string('syncmaterialpending', 'block_fredkitheagent'));
    $rec->timemodified = time();
    $DB->update_record('block_fredkitheagent', $rec);

    $notice = get_string('syncnotice', 'block_fredkitheagent', (object)[
        'found' => (int)($stats['found'] ?? 0),
        'synced' => (int)($stats['synced'] ?? 0),
        'unchanged' => (int)($stats['unchanged'] ?? 0),
        'cached' => (int)($stats['cached'] ?? 0),
        'deleted' => (int)($stats['deleted'] ?? 0),
        'failed' => (int)($stats['failed'] ?? 0),
        'seconds' => (int)ceil((float)($stats['total_seconds'] ?? 0)),
    ]);
    if ($fullcheck) {
        $notice .= ' ' . get_string('syncnoticefull', 'block_fredkitheagent');
    }
    if ($documentcomplete && \block_fredkitheagent\local\import_completion::chat_ready($rec)) {
        \core\notification::success($notice);
    } else if (\block_fredkitheagent\local\import_completion::waiting_for_material($rec)) {
        \core\notification::warning(get_string('syncnoticenomaterial', 'block_fredkitheagent'));
    } else {
        \core\notification::warning($notice . ' ' . get_string('syncnoticeincomplete', 'block_fredkitheagent'));
    }
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
} catch (\Throwable $e) {
    $code = \block_fredkitheagent\local\managed_gateway::safe_error($e);
    // Keep stable IDs and previous snapshots. Never delete knowledge bases,
    // agent or document mappings because of an HTTP timeout.
    try {
        $failed = \block_fredkitheagent\local\course_agent_manager::get_record($courseid);
        if ($failed) {
            $failed->status = 'agent_error';
            $failed->lasterror = get_string('syncerrorstage', 'block_fredkitheagent', (object)['stage' => $stage, 'code' => $code]);
            $failed->timemodified = time();
            $DB->update_record('block_fredkitheagent', $failed);
        }
    } catch (\Throwable $storeerror) {
        debugging('[FredKI The Agent] Could not persist failed stage: ' . get_class($storeerror), DEBUG_DEVELOPER);
    }
    // Stage and whitelisted diagnostic codes only. No secrets, URLs or student data.
    debugging('[FredKI The Agent] ' . $stage . ': ' . $code, DEBUG_DEVELOPER);
    \core\notification::error(get_string('syncfailedstage', 'block_fredkitheagent', (object)['stage' => $stage, 'code' => $code]));
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
}
redirect(new moodle_url('/course/view.php', ['id' => $courseid]));

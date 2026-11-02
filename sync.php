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
// 1.6.0: agent role chosen when the course agent is set up for the first time.
$agentrole = optional_param('agentrole', '', PARAM_ALPHANUMEXT);
$continuing = optional_param('continue', 0, PARAM_INT) ? true : false;
// Long synchronisations run in stages, so no stage exceeds the timeout of a web server
// or proxy in front of Moodle. The stage state lives in the teacher's session only.
$requeststart = microtime(true);
// Seconds per stage (setting "Time budget per synchronisation", 10 to 120, default 30). Let PHP finish
// a started stage even if the browser connection is cut, so no transfer is left half done.
$stagebudget = (float)max(10, min(120, (int)get_config('block_fredkitheagent', 'sync_time_budget') ?: 30));
\core_php_time_limit::raise(600);
ignore_user_abort(true);
if (!isset($SESSION->fredki_theagent_sync) || !is_array($SESSION->fredki_theagent_sync)) {
    $SESSION->fredki_theagent_sync = [];
}
$stagestate = $SESSION->fredki_theagent_sync[$courseid] ?? null;
if (!$continuing || !is_array($stagestate) || (int)($stagestate['since'] ?? 0) < time() - 6 * HOURSECS) {
    $stagestate = ['since' => time(), 'fullcheck' => $fullcheck, 'synced' => 0, 'unchanged' => 0, 'deleted' => 0, 'stages' => 0];
}
$fullcheck = !empty($stagestate['fullcheck']);
// Always inspect every Moodle course source. When fullcheck is requested, every
// current document is re-validated against the FredKI service so that missing
// knowledge-base documents are uploaded again even if the local mapping exists.

// A course agent deleted directly in FredKI is replaced by the licence server. Accept the new
// agent only if the server verified that the agent Moodle knows no longer exists there.
$acceptreplacedagent = static function (\stdClass $rec, array $response): void {
    $serveragent = (string)($response['agent_id'] ?? '');
    $replaced = $response['replaced_agent_ids'] ?? [];
    if (
        !empty($rec->agentid) && $serveragent !== '' && !hash_equals((string)$rec->agentid, $serveragent)
            && is_array($replaced) && in_array((string)$rec->agentid, $replaced, true)
    ) {
        $rec->agentid = $serveragent;
        \core\notification::info(get_string('agentreplaced', 'block_fredkitheagent'));
    }
};

$stage = get_string('syncstageregister', 'block_fredkitheagent');
$stats = null;
try {
    // Register/locate course by installation id + Moodle course id; never by domain alone.
    $registered = \block_fredkitheagent\local\managed_gateway::ensure_course_registered($course);
    if (!empty($registered['binding_reset_applied'])) {
        // The administrator deleted the previous agent and knowledge base and released the course.
        \core\notification::info(get_string('coursebindingreset', 'block_fredkitheagent'));
    }
    if ($agentrole !== '') {
        // Without a course agent yet the licence server only remembers the role for the setup.
        \block_fredkitheagent\local\managed_gateway::set_course_role($courseid, $agentrole);
        // Remember the choice right away, so a retry after an interrupted setup keeps it.
        $DB->set_field('block_fredkitheagent', 'agentrole', $agentrole, ['course' => $courseid]);
    }
    $stage = get_string('syncstageknowledge', 'block_fredkitheagent');
    $prepared = \block_fredkitheagent\local\managed_gateway::prepare_or_provision_knowledge($course);
    $rec = \block_fredkitheagent\local\course_agent_manager::ensure_record($course);
    if ($agentrole !== '' && (string)($rec->agentrole ?? '') !== $agentrole) {
        $DB->set_field('block_fredkitheagent', 'agentrole', $agentrole, ['id' => $rec->id]);
        $rec->agentrole = $agentrole;
    }
    $knowledgeid = (string)$prepared['knowledge_id'];
    if (!empty($rec->knowledgeid) && !hash_equals((string)$rec->knowledgeid, $knowledgeid)) {
        $replaced = $prepared['replaced_knowledge_ids'] ?? [];
        if (!is_array($replaced) || !in_array((string)$rec->knowledgeid, $replaced, true)) {
            throw new \block_fredkitheagent\local\managed_api_diagnostic_exception(
                'DIAG-COURSE-BINDING-CHANGED: The licence server returned a different course knowledge base.'
            );
        }
        // The previous knowledge base verifiably no longer exists in FredKI (for example deleted
        // there by hand). Accept the replacement; all material is transferred again and the
        // licence server reconnects the existing course agent to the new knowledge base.
        $rec->knowledgeid = $knowledgeid;
        \core\notification::info(get_string('knowledgereplaced', 'block_fredkitheagent'));
    }
    if (($prepared['binding_verified'] ?? false) === true) {
        $acceptreplacedagent($rec, $prepared);
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
        $requeststart + $stagebudget,
        (int)$stagestate['since']
    );
    $stagestate['stages']++;
    foreach (['synced', 'unchanged', 'deleted'] as $counter) {
        $stagestate[$counter] += (int)($stats[$counter] ?? 0);
    }
    // Creating a new course agent can take a while on its own. If this request has already
    // used most of its time, the agent step gets a short stage of its own.
    $needsagent = ($prepared['binding_verified'] ?? false) !== true;
    $agentstage = (int)($stats['remaining'] ?? 0) === 0 && $needsagent && empty($stagestate['agentstage'])
        && microtime(true) - $requeststart > 10.0;
    if ($agentstage) {
        $stagestate['agentstage'] = 1;
    }
    if ((int)($stats['remaining'] ?? 0) > 0 || $agentstage) {
        // Not finished yet: show progress and continue automatically with the next stage.
        $SESSION->fredki_theagent_sync[$courseid] = $stagestate;
        $total = max(1, (int)($stats['found'] ?? 0));
        $done = max(0, $total - (int)$stats['remaining']);
        $PAGE->set_url(new moodle_url('/blocks/fredkitheagent/sync.php', ['courseid' => $courseid]));
        $PAGE->set_context($context);
        $PAGE->set_pagelayout('incourse');
        $PAGE->set_title(get_string('syncprogresstitle', 'block_fredkitheagent'));
        $PAGE->set_heading(format_string($course->fullname));
        $PAGE->requires->js_init_code(
            "setTimeout(function(){var f=document.getElementById('fredki-sync-continue');if(f){f.submit();}},400);"
        );
        $progresstext = get_string(
            $agentstage ? 'syncprogressagent' : 'syncprogress',
            'block_fredkitheagent',
            (object)['done' => $done, 'total' => $total]
        );
        $hidden = ['courseid' => $courseid, 'sesskey' => sesskey(), 'continue' => 1, 'fullcheck' => $fullcheck ? 1 : 0];
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('syncprogresstitle', 'block_fredkitheagent'));
        echo html_writer::tag('p', s($progresstext));
        echo html_writer::tag('progress', '', ['max' => $total, 'value' => $done, 'class' => 'w-100']);
        echo html_writer::tag('p', s(get_string('syncprogresshint', 'block_fredkitheagent')), ['class' => 'text-muted']);
        echo html_writer::start_tag('form', [
            'method' => 'post',
            'id' => 'fredki-sync-continue',
            'action' => new moodle_url('/blocks/fredkitheagent/sync.php'),
        ]);
        foreach ($hidden as $name => $value) {
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
        }
        echo html_writer::tag(
            'button',
            s(get_string('syncprogresscontinue', 'block_fredkitheagent')),
            ['type' => 'submit', 'class' => 'btn btn-secondary']
        );
        echo html_writer::end_tag('form');
        echo $OUTPUT->footer();
        exit;
    }
    unset($SESSION->fredki_theagent_sync[$courseid]);
    $rec = \block_fredkitheagent\local\course_agent_manager::get_record($courseid);
    if (!$rec) {
        throw new \UnexpectedValueException(get_string('syncrecorddisappeared', 'block_fredkitheagent'));
    }

    // The central server must issue a new agent ID for a new course, while
    // returning the same ID for an already provisioned course.
    $stage = get_string('syncstageagent', 'block_fredkitheagent');
    $result = ($prepared['binding_verified'] ?? false) === true
        ? ['knowledge_id' => $knowledgeid, 'agent_id' => (string)$prepared['agent_id'],
           'binding_verified' => true, 'provision_state' => 'ready']
        : \block_fredkitheagent\local\managed_gateway::provision_course($course);
    $acceptreplacedagent($rec, $result);
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

    // Totals over all stages; documents sent in earlier stages appear as transferred, not as cached.
    $earlier = (int)$stagestate['synced'] - (int)($stats['synced'] ?? 0)
        + (int)$stagestate['unchanged'] - (int)($stats['unchanged'] ?? 0);
    $notice = get_string('syncnotice', 'block_fredkitheagent', (object)[
        'found' => (int)($stats['found'] ?? 0),
        'synced' => (int)$stagestate['synced'],
        'unchanged' => (int)$stagestate['unchanged'],
        'cached' => max(0, (int)($stats['cached'] ?? 0) - $earlier),
        'deleted' => (int)$stagestate['deleted'],
        'failed' => (int)($stats['failed'] ?? 0),
        'seconds' => max(1, time() - (int)$stagestate['since']),
    ]);
    if ($fullcheck) {
        $notice .= ' ' . get_string('syncnoticefull', 'block_fredkitheagent');
    }
    if ($documentcomplete && \block_fredkitheagent\local\import_completion::chat_ready($rec)) {
        \core\notification::success($notice);
    } else if (\block_fredkitheagent\local\import_completion::waiting_for_material($rec)) {
        $nomaterialnotice = get_string('syncnoticenomaterial', 'block_fredkitheagent');
        if ((int)($stats['deleted'] ?? 0) > 0) {
            $nomaterialnotice .= ' ' . get_string('syncnoticeremoved', 'block_fredkitheagent', (int)$stats['deleted']);
        }
        \core\notification::warning($nomaterialnotice);
    } else {
        \core\notification::warning($notice . ' ' . get_string('syncnoticeincomplete', 'block_fredkitheagent'));
    }
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
} catch (\Throwable $e) {
    unset($SESSION->fredki_theagent_sync[$courseid]);
    if (
        $e instanceof \block_fredkitheagent\local\managed_api_diagnostic_exception
            && $e->getCode() === 410
    ) {
        // Disconnected by the licence server administrator: not an error of the teacher.
        \block_fredkitheagent\local\course_agent_manager::mark_detached($courseid);
        \core\notification::warning(get_string('coursedetachednotice', 'block_fredkitheagent'));
        redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
    }
    if (
        $e instanceof \block_fredkitheagent\local\managed_api_diagnostic_exception
            && $e->getCode() === 402
    ) {
        // Package course limit: nothing was created on the server; existing courses keep working.
        \core\notification::warning(get_string('courselimitnotice', 'block_fredkitheagent'));
        redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
    }
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

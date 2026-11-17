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
 * FredKI The Agent: block_fredkitheagent.php
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Block fredkitheagent class.
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_fredkitheagent extends block_base {
    /**
     * Init.
     */
    public function init(): void {
        // The plugin name is needed for the "add a block" menus of every theme (Classic lists blocks by this title).
        $this->title = get_string('pluginname', 'block_fredkitheagent');
    }

    /**
     * Hides the visible block title once the block exists in a course: the FredKI card carries its own branding.
     * The header stays technically present so that Moodle still offers move/delete in editing mode.
     */
    public function specialization(): void {
        // The card carries its own heading. Only while editing, the standard block header (with the move
        // and settings controls) shows the name, exactly like every other block.
        $editing = !empty($this->page) && $this->page->user_is_editing();
        $this->title = $editing ? get_string('pluginname', 'block_fredkitheagent') : '';
    }

    /**
     * Applicable formats.
     *
     * @return array The result.
     */
    public function applicable_formats(): array {
        return ['course-view' => true, 'site-index' => false, 'my' => false, 'mod' => false, 'all' => false];
    }

    /**
     * Instance allow multiple.
     *
     * @return bool True on success.
     */
    public function instance_allow_multiple(): bool {
        return false;
    }
    /**
     * Instance allow config.
     *
     * @return bool True on success.
     */
    public function instance_allow_config(): bool {
        return false;
    }
    /**
     * Hide header.
     *
     * @return bool True on success.
     */
    public function hide_header(): bool {
        return false;
    }
    /**
     * Has config.
     */
    public function has_config() {
        return true;
    }

    /**
     * Get content.
     *
     * @return ?stdClass The result.
     */
    public function get_content(): ?stdClass {
        global $COURSE;
        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        $courseid = (int)($COURSE->id ?? 0);
        if ($courseid <= 1) {
            $this->content->text = '';
            return $this->content;
        }
        $context = context_course::instance($courseid);
        $canmanage = has_capability('moodle/course:update', $context);

        $statusactive = false;
        try {
            $statusactive = !empty(\block_fredkitheagent\local\managed_gateway::status()['active']);
        } catch (\Throwable $e) {
            $statusactive = false;
        }
        $rec = \block_fredkitheagent\local\course_agent_manager::get_record($courseid);
        // The protected chat is a Moodle-local page; never embed a public agent token.
        // Always show the SAME chat UI to teachers and learners if it is authorised.
        if (!$statusactive) {
            if ($canmanage) {
                $this->content->text = $this->render_inactive_card(
                    has_capability('moodle/site:config', context_system::instance())
                );
            }
            return $this->content;
        }
        if ($canmanage) {
            $this->content->text = $this->render_managed_learning_card(true, $rec);
            $this->page->requires->js_call_amd('block_fredkitheagent/learning_card', 'init');
            if ($this->can_show_chat_launcher($rec)) {
                $this->content->text .= $this->render_chat_launcher(empty($rec->agentcoursevisible));
            }
        } else if ($this->can_show_chat_launcher($rec) && !empty($rec->agentcoursevisible)) {
            // Learners never see this management block. Only show the chat launcher if a
            // teacher has explicitly enabled it for the course.
            $this->title = '';
            $this->content->text = $this->render_chat_launcher();
        }
        return $this->content;
    }


    /**
     * Card shown to teachers while the Moodle site is not yet activated at the licence server.
     *
     * @param bool $isadmin Whether the current user may open the activation page.
     * @return string HTML.
     */
    protected function render_inactive_card(bool $isadmin): string {
        global $OUTPUT;
        return $OUTPUT->render_from_template('block_fredkitheagent/card_inactive', [
            'isadmin' => $isadmin,
            'activationurl' => (new moodle_url('/blocks/fredkitheagent/activation.php'))->out(false),
            'icon' => ['sparkle' => true],
        ]);
    }

    /**
     * Template data of the select box with the agent roles offered by the licence server.
     *
     * @param stdClass|null $rec Local course record.
     * @param string $class CSS class of the wrapper.
     * @return array|null Context for block_fredkitheagent/role_select, or null if there is nothing to choose.
     */
    protected function role_select(?stdClass $rec, string $class): ?array {
        try {
            $roles = \block_fredkitheagent\local\managed_gateway::status()['agent_roles'] ?? [];
        } catch (\Throwable $e) {
            return null;
        }
        if (count($roles) < 2) {
            return null;
        }
        $current = $rec ? (string)($rec->agentrole ?? '') : '';
        $legacy = $current === '' && $rec && !empty($rec->agentid);
        $options = [];
        if ($legacy) {
            // Course agent from before agent roles existed: keep it until the teacher chooses a role.
            $options[] = ['value' => '', 'label' => get_string('rolecurrent', 'block_fredkitheagent'), 'title' => '',
                'selected' => true];
        }
        foreach ($roles as $role) {
            $options[] = [
                'value' => $role['id'],
                'label' => $role['name'],
                'title' => $role['description'],
                'selected' => $role['id'] === $current || ($current === '' && !$legacy && $role['default']),
            ];
        }
        return [
            'wrapclass' => $class,
            'id' => 'fredki-role-' . (int)($rec->course ?? 0) . '-' . random_int(1000, 9999),
            'options' => $options,
        ];
    }

    /**
     * Whether the package has no free place for a course that is not connected yet.
     *
     * @param stdClass|null $rec Local course record.
     * @return bool
     */
    protected function package_full(?stdClass $rec): bool {
        if ($rec && (!empty($rec->knowledgeid) || !empty($rec->agentid))) {
            return false;
        }
        try {
            $status = \block_fredkitheagent\local\managed_gateway::status();
        } catch (\Throwable $e) {
            return false;
        }
        $limit = $status['course_limit'] ?? null;
        $used = $status['courses_used'] ?? null;
        return is_int($limit) && is_int($used) && $limit > 0 && $used >= $limit;
    }

    /**
     * Template data of one POST form to sync.php. The big button and the quiet "full check" row share it.
     *
     * @param bool $fullcheck Whether all material is checked again.
     * @param string $label Button label.
     * @param string $busylabel Label while working.
     * @param string $icon Icon name or empty.
     * @param string $class Button class.
     * @param array|null $roleselect Optional role select box.
     * @return array Context for block_fredkitheagent/sync_form.
     */
    protected function sync_form(
        bool $fullcheck,
        string $label,
        string $busylabel,
        string $icon,
        string $class,
        ?array $roleselect = null
    ): array {
        global $COURSE;
        return [
            'url' => (new moodle_url('/blocks/fredkitheagent/sync.php'))->out(false),
            'courseid' => (int)$COURSE->id,
            'sesskey' => sesskey(),
            'fullcheck' => $fullcheck ? 1 : 0,
            'roleselect' => $roleselect ?? false,
            'icon' => $icon !== '' ? [$icon => true] : false,
            'label' => $label,
            'busylabel' => $busylabel,
            'class' => $class,
        ];
    }

    /**
     * Template data of the notice shown when the monthly messages are (nearly) used up.
     *
     * @return array|null Context for block_fredkitheagent/quota, null below 80 %.
     */
    protected function quota_notice(): ?array {
        try {
            $status = \block_fredkitheagent\local\managed_gateway::status();
        } catch (\Throwable $e) {
            return null;
        }
        $quota = $status['message_quota'] ?? ($status['free_quota'] ?? null);
        if (!is_array($quota) || $quota['limit'] <= 0) {
            return null;
        }
        $used = (int)$quota['used'];
        $limit = (int)$quota['limit'];
        if ($used * 5 < $limit * 4) {
            // Below 80 %: nothing to show.
            return null;
        }
        $a = (object)['used' => $used, 'limit' => $limit, 'reset' => !empty($quota['resets_at'])
            ? userdate((int)$quota['resets_at'], get_string('strftimedate', 'langconfig')) : '-'];
        return \block_fredkitheagent\local\output_helper::quota(
            $a,
            has_capability('moodle/site:config', context_system::instance()),
            ($status['tier'] ?? '') === 'free',
            true
        );
    }

    /**
     * Card for teachers: one big button for everything; extras sit quietly behind the info button.
     *
     * @param bool $canmanage Whether the user may manage the course.
     * @param stdClass|null $rec Local course record.
     * @return string HTML.
     */
    protected function render_managed_learning_card(bool $canmanage, ?stdClass $rec): string {
        global $COURSE, $OUTPUT;
        $pn = 'block_fredkitheagent';
        $courseid = (int)$COURSE->id;
        $ready = $this->can_show_chat_launcher($rec);
        if ($ready && $canmanage) {
            // Opening statement of the FredKI template agent as global welcome text (at most every six hours).
            \block_fredkitheagent\local\welcome_sync::sync();
        }
        $agentcreated = $rec && !empty($rec->agentid);
        $docs = $rec ? (int)($rec->documentcount ?? 0) : 0;
        $last = ($rec && !empty($rec->timesynced)) ?
            userdate((int)$rec->timesynced, get_string('strftimedatetimeshort', 'langconfig')) : '';
        $busy = get_string('iosbusy', $pn);
        $siteadmin = has_capability('moodle/site:config', context_system::instance());
        $removeurl = (new moodle_url('/blocks/fredkitheagent/remove.php', ['courseid' => $courseid]))->out(false);
        $data = [
            'courseid' => $courseid,
            'visibilityurl' => (new moodle_url('/blocks/fredkitheagent/visibility.php'))->out(false),
            'sesskey' => sesskey(),
            'quota' => $canmanage ? ($this->quota_notice() ?? false) : false,
            'detached' => false,
            'limitfull' => false,
            'ready' => false,
            'setup' => false,
            'settings' => false,
        ];
        if ($rec && (string)$rec->status === 'detached') {
            // Disconnected by the licence server administrator. Nothing the teacher must repair;
            // once the course is released, the next check sets it up anew automatically.
            $data['detached'] = [
                'form' => $canmanage ? $this->sync_form(false, get_string('coursedetachedretry', $pn), $busy, 'refresh', 'fk-main')
                    : false,
            ];
        } else if ($canmanage && !$agentcreated && $this->package_full($rec)) {
            // The package has no free place: explain it before the teacher tries to set up the course.
            $billing = \block_fredkitheagent\local\managed_gateway::status();
            $limit = (object)['used' => (int)$billing['courses_used'], 'limit' => (int)$billing['course_limit']];
            $data['limitfull'] = [
                'status' => get_string('limitfullstatus', $pn, $limit),
                'intro' => get_string($siteadmin ? 'limitfullintro' : 'limitfullintroteacher', $pn, $limit),
                // Only site administrators can activate a package; teachers are pointed to them.
                'manageurl' => $siteadmin ? (new moodle_url('/blocks/fredkitheagent/activation.php'))->out(false) : false,
                // A place may have been freed meanwhile: checking again sets the course up if possible.
                'form' => $this->sync_form(true, get_string('limitfullretry', $pn), $busy, '', 'fk-linkbutton'),
            ];
        } else if ($ready) {
            $roleselect = $canmanage ? $this->role_select($rec, 'fk-rolepick') : null;
            $data['ready'] = [
                'status' => $docs > 0 ? get_string('iosready', $pn, $docs) : get_string('nextready', $pn),
                // The teacher chooses which FredKI agent works in this course (Berater, Sparringspartner, ...).
                'roleform' => $roleselect ? [
                    'url' => (new moodle_url('/blocks/fredkitheagent/role.php'))->out(false),
                    'courseid' => $courseid,
                    'sesskey' => sesskey(),
                    'roleselect' => $roleselect,
                ] : false,
                'form' => $this->sync_form(false, get_string('iosupdate', $pn), $busy, 'refresh', 'fk-main'),
                'last' => $last !== '' ? $last : false,
                'courseid' => $courseid,
                'chatvisible' => !empty($rec->agentcoursevisible),
                'designurl' => (new moodle_url('/blocks/fredkitheagent/design.php', ['courseid' => $courseid]))->out(false),
                'paletteicon' => ['palette' => true],
                'infoicon' => ['info' => true],
                // 1.8.4: removing the course agent is visible right in the card, not only behind the info button.
                'removeurl' => $canmanage ? $removeurl : false,
            ];
            if ($canmanage) {
                $data['settings'] = [
                    'form' => $this->sync_form(true, get_string('iosfullcheckrun', $pn), $busy, '', 'fk-rowbutton'),
                    'facts' => [
                        ['name' => get_string('iosagent', $pn),
                            'value' => !empty($rec->agentid) ? '…' . substr((string)$rec->agentid, -6) : '–'],
                        ['name' => get_string('iosknowledge', $pn),
                            'value' => !empty($rec->knowledgeid) ? '…' . substr((string)$rec->knowledgeid, -6) : '–'],
                        ['name' => get_string('iosdocs', $pn), 'value' => (string)$docs],
                        ['name' => get_string('ioslast', $pn), 'value' => $last !== '' ? $last : '–'],
                    ],
                    // Free the course agent for another course: deletes agent and knowledge base in FredKI.
                    'removeurl' => (new moodle_url('/blocks/fredkitheagent/remove.php', ['courseid' => $courseid]))->out(false),
                ];
            }
        } else if ($canmanage) {
            $note = false;
            if ($rec && !empty($rec->lasterror)) {
                $completion = \block_fredkitheagent\local\import_completion::class;
                $heading = 'nextsyncfailed';
                $detail = (string)$rec->lasterror;
                if ($completion::waiting_for_material($rec)) {
                    $heading = 'nextnomaterial';
                    $detail = substr($detail, strlen($completion::NOMATERIAL_PREFIX));
                } else if ($completion::is_partial($rec)) {
                    $heading = 'nextpartial';
                    $detail = substr($detail, strlen($completion::PARTIAL_PREFIX));
                }
                $note = ['heading' => get_string($heading, $pn), 'detail' => $detail];
            }
            $data['setup'] = [
                'intro' => get_string($agentcreated ? 'nextcreatedesc' : 'iosintro', $pn),
                'form' => $this->sync_form(
                    true,
                    get_string($agentcreated ? 'ioscontinue' : 'iossetup', $pn),
                    $busy,
                    'sparkle',
                    'fk-main',
                    $agentcreated ? null : $this->role_select($rec, 'fk-rolepick fk-rolepick-setup')
                ),
                'note' => $note,
                // A course agent that exists but is not ready (for example after an interrupted setup) can be removed.
                'removeurl' => $agentcreated ? $removeurl : false,
            ];
        }
        return $OUTPUT->render_from_template('block_fredkitheagent/learning_card', $data);
    }

    /**
     * Whether the protected chat can be shown in this course.
     *
     * @param ?stdClass $rec Local course record.
     * @return bool True if the chat is ready.
     */
    protected function can_show_chat_launcher(?stdClass $rec): bool {
        return \block_fredkitheagent\local\import_completion::chat_ready($rec);
    }

    /**
     * Floating button that opens the protected course chat.
     *
     * @param bool $initiallyhidden Whether the launcher starts hidden (teachers before showing the chat).
     * @return string HTML.
     */
    protected function render_chat_launcher(bool $initiallyhidden = false): string {
        global $COURSE, $OUTPUT, $USER;
        // This script must run for BOTH teachers and learners; otherwise the launcher
        // remains an inert button after being moved out of Moodle's block drawer.
        $this->page->requires->js_call_amd('block_fredkitheagent/floating_chat', 'init');
        $courseicon = \block_fredkitheagent\local\course_design::logo_url((int)$COURSE->id);
        return $OUTPUT->render_from_template('block_fredkitheagent/chat_launcher', [
            'courseid' => (int)$COURSE->id,
            'userid' => (int)$USER->id,
            'hidden' => $initiallyhidden,
            'icon' => $courseicon ?: $OUTPUT->image_url('icon', 'block_fredkitheagent')->out(false),
            'chaturl' => (new moodle_url('/blocks/fredkitheagent/chat.php', [
                'courseid' => (int)$COURSE->id, 'embedded' => 1,
            ]))->out(false),
        ]);
    }
}

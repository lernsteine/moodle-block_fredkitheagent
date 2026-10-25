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
        $this->title = '';
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
            $this->page->requires->js(new moodle_url(
                '/blocks/fredkitheagent/learning_card.js',
                ['v' => 202610240400]
            ), false);
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
     * Modern, inline, same-origin Moodle chat card. Keeps the original server gateway.
     */
    /**
     * Card shown to teachers while the Moodle site is not yet activated at the licence server.
     *
     * @param bool $isadmin Whether the current user may open the activation page.
     * @return string HTML.
     */
    protected function render_inactive_card(bool $isadmin): string {
        $pn = 'block_fredkitheagent';
        $content = html_writer::start_div('fredki-next-card fredki-ios fk-inactive');
        $content .= html_writer::start_div('fk-head');
        $content .= html_writer::div(
            html_writer::span('', 'fk-eye') . html_writer::span('', 'fk-eye'),
            'fk-orb',
            ['aria-hidden' => 'true']
        );
        $content .= html_writer::start_div('fk-headcopy');
        $content .= html_writer::tag('strong', get_string('pluginname', $pn));
        $content .= html_writer::tag('small', get_string('nextsubtitle', $pn));
        $content .= html_writer::end_div();
        $content .= html_writer::end_div();
        $content .= html_writer::div(
            html_writer::span('', 'fk-dot is-waiting') . s(get_string('iosinactivestatus', $pn)),
            'fk-status is-waiting'
        );
        $content .= html_writer::tag('p', s(get_string('iosinactiveintro', $pn)), ['class' => 'fk-intro']);
        if ($isadmin) {
            $content .= html_writer::link(
                new moodle_url('/blocks/fredkitheagent/activation.php'),
                $this->ios_icon('sparkle') . s(get_string('iosinactiveopen', $pn)),
                ['class' => 'fk-main']
            );
            $content .= html_writer::div(s(get_string('iosinactivetrial', $pn)), 'fk-caption');
        } else {
            $content .= html_writer::div(s(get_string('iosinactivenoadmin', $pn)), 'fk-note');
        }
        $content .= html_writer::end_div();
        return $content;
    }

    /**
     * Inline stroke icons (static markup, no user data).
     *
     * @param string $name The name.
     * @return string The result.
     */
    protected function ios_icon(string $name): string {
        $paths = [
            'refresh' => '<path d="M20 11a8 8 0 0 0-14.3-4.6L4 8.5"/><path d="M4 4v4.5h4.5"/>' .
                '<path d="M4 13a8 8 0 0 0 14.3 4.6L20 15.5"/><path d="M20 20v-4.5h-4.5"/>',
            'sparkle' => '<path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z"/>' .
                '<path d="M19 16l.7 1.8 1.8.7-1.8.7L19 21l-.7-1.8-1.8-.7 1.8-.7z"/>',
            'gear' => '<circle cx="12" cy="12" r="3"/>' .
                '<path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 ' .
                '0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 ' .
                '1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 ' .
                '0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 ' .
                '0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 ' .
                '1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 ' .
                '0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 ' .
                '0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
            'chevron' => '<path d="M9 6l6 6-6 6"/>',
        ];
        return '<svg class="fk-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' .
            'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' .
            ($paths[$name] ?? '') . '</svg>';
    }

    /**
     * One POST form to sync.php. The big button and the quiet "full check" row share it.
     *
     * @param bool $fullcheck The fullcheck.
     * @param string $label The label.
     * @param string $busylabel The busylabel.
     * @param string $icon The icon.
     * @param string $class The class.
     * @return string The result.
     */
    protected function render_ios_sync_form(
        bool $fullcheck,
        string $label,
        string $busylabel,
        string $icon,
        string $class
    ): string {
        global $COURSE;
        $url = new moodle_url('/blocks/fredkitheagent/sync.php');
        $form = html_writer::start_tag('form', ['method' => 'post', 'action' => $url, 'data-fk-syncform' => '1']);
        $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => (int)$COURSE->id]);
        $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'fullcheck', 'value' => $fullcheck ? '1' : '0']);
        $inner = ($icon !== '' ? $this->ios_icon($icon) : '') . html_writer::span(s($label), 'fk-label');
        $form .= html_writer::tag('button', $inner, [
            'type' => 'submit', 'class' => $class,
            'data-fk-label' => $label, 'data-fk-busylabel' => $busylabel,
        ]);
        $form .= html_writer::end_tag('form');
        return $form;
    }

    /**
     * iPhone-style card: ONE big button for everything; extras sit quietly behind the gear.
     *
     * @param bool $canmanage The canmanage.
     * @param stdClass|null $rec The rec.
     * @return string The result.
     */
    protected function render_managed_learning_card(bool $canmanage, ?stdClass $rec): string {
        global $COURSE;
        $pn = 'block_fredkitheagent';
        $ready = $this->can_show_chat_launcher($rec);
        $agentcreated = $rec && !empty($rec->agentid);
        $docs = $rec ? (int)($rec->documentcount ?? 0) : 0;
        $last = ($rec && !empty($rec->timesynced)) ?
            userdate((int)$rec->timesynced, get_string('strftimedatetimeshort', 'langconfig')) : '';
        $content = html_writer::start_div('fredki-next-card fredki-ios', [
            'data-fredki-next-card' => '1',
            'data-course-id' => (int)$COURSE->id,
            'data-visibility-url' => (new moodle_url('/blocks/fredkitheagent/visibility.php'))->out(false),
            'data-sesskey' => sesskey(),
        ]);

        // Main view.
        $content .= html_writer::start_div('fk-view fk-view-main', ['data-fk-view' => 'main']);
        $content .= html_writer::start_div('fk-head');
        $content .= html_writer::div(
            html_writer::span('', 'fk-eye') . html_writer::span('', 'fk-eye'),
            'fk-orb',
            ['aria-hidden' => 'true']
        );
        $content .= html_writer::start_div('fk-headcopy');
        $content .= html_writer::tag('strong', get_string('pluginname', $pn));
        $content .= html_writer::tag('small', get_string('nextsubtitle', $pn));
        $content .= html_writer::end_div();
        $content .= html_writer::end_div();

        if ($ready) {
            $status = $docs > 0 ? get_string('iosready', $pn, $docs) : get_string('nextready', $pn);
            $content .= html_writer::div(html_writer::span('', 'fk-dot is-ready') . s($status), 'fk-status');
            $content .= $this->render_ios_sync_form(
                false,
                get_string('iosupdate', $pn),
                get_string('iosbusy', $pn),
                'refresh',
                'fk-main'
            );
            if ($last !== '') {
                $content .= html_writer::div(s(get_string('ioslastsync', $pn, $last)), 'fk-caption');
            }
            $content .= html_writer::tag('span', '', [
                'class' => 'fk-result', 'data-fredki-next-result' => '1', 'role' => 'status', 'aria-live' => 'polite',
            ]);
            $content .= html_writer::div('', 'fk-rule');
            $checkboxattrs = [
                'type' => 'checkbox', 'class' => 'fk-switch', 'role' => 'switch',
                'data-fredki-next-togglechat' => '1', 'id' => 'fredki-next-chatcheck-' . (int)$COURSE->id,
            ];
            if (!empty($rec->agentcoursevisible)) {
                $checkboxattrs['checked'] = 'checked';
            }
            $content .= html_writer::start_div('fk-footer');
            $content .= html_writer::tag(
                'label',
                html_writer::span(s(get_string('ioschat', $pn)), 'fk-switchtext') .
                html_writer::empty_tag('input', $checkboxattrs),
                ['class' => 'fk-switchrow', 'for' => 'fredki-next-chatcheck-' . (int)$COURSE->id]
            );
            $content .= html_writer::tag('button', $this->ios_icon('gear'), [
                'type' => 'button', 'class' => 'fk-gear', 'data-fk-open' => 'settings',
                'aria-label' => get_string('iossettings', $pn), 'title' => get_string('iossettings', $pn),
                'aria-expanded' => 'false',
            ]);
            $content .= html_writer::end_div();
        } else if ($canmanage) {
            $content .= html_writer::tag(
                'p',
                get_string($agentcreated ? 'nextcreatedesc' : 'iosintro', $pn),
                ['class' => 'fk-intro']
            );
            $content .= $this->render_ios_sync_form(
                true,
                get_string($agentcreated ? 'ioscontinue' : 'iossetup', $pn),
                get_string('iosbusy', $pn),
                'sparkle',
                'fk-main'
            );
            $content .= html_writer::div(s(get_string('iossetupnote', $pn)), 'fk-caption');
            if ($rec && !empty($rec->lasterror)) {
                $content .= html_writer::tag(
                    'p',
                    get_string('nextsyncfailed', $pn) .
                    html_writer::tag('small', s((string)$rec->lasterror), ['class' => 'fk-detail']),
                    ['class' => 'fk-note']
                );
            }
            $content .= html_writer::div('', 'fk-rule');
            $content .= html_writer::div(
                html_writer::span('', 'fk-dot') . s(get_string('iosnotsetup', $pn)),
                'fk-footer fk-footer-status'
            );
        }
        $content .= html_writer::end_div();

        // Quiet settings sheet (only once the agent works).
        if ($ready && $canmanage) {
            $content .= html_writer::start_div('fk-view fk-view-settings', ['data-fk-view' => 'settings', 'hidden' => 'hidden']);
            $content .= html_writer::start_div('fk-sheethead');
            $content .= html_writer::tag('h4', get_string('iossettings', $pn), ['class' => 'fk-sheettitle']);
            $content .= html_writer::tag('button', get_string('iosdone', $pn), [
                'type' => 'button', 'class' => 'fk-done', 'data-fk-close' => 'settings']);
            $content .= html_writer::end_div();
            $content .= html_writer::start_div('fk-group');
            $content .= html_writer::link(
                new moodle_url(
                    '/blocks/fredkitheagent/design.php',
                    ['courseid' => (int)$COURSE->id]
                ),
                html_writer::span(s(get_string('iosdesign', $pn)), 'fk-rowtext') . $this->ios_icon('chevron'),
                ['class' => 'fk-row fk-row-link']
            );
            $content .= html_writer::end_div();
            $content .= html_writer::start_div('fk-group');
            $content .= html_writer::start_tag('div', ['class' => 'fk-row fk-row-form']);
            $content .= html_writer::start_div('fk-rowtext');
            $content .= html_writer::span(s(get_string('iosfullcheck', $pn)), 'fk-rowtitle');
            $content .= html_writer::span(s(get_string('iosfullcheckdesc', $pn)), 'fk-rowdesc');
            $content .= html_writer::end_div();
            $content .= $this->render_ios_sync_form(
                true,
                get_string('iosfullcheckrun', $pn),
                get_string('iosbusy', $pn),
                '',
                'fk-rowbutton'
            );
            $content .= html_writer::end_tag('div');
            $content .= html_writer::end_div();
            $content .= html_writer::start_div('fk-group fk-facts');
            $facts = [
                get_string('iosagent', $pn) => !empty($rec->agentid) ? '…' . substr((string)$rec->agentid, -6) : '–',
                get_string('iosknowledge', $pn) => !empty($rec->knowledgeid) ? '…' . substr((string)$rec->knowledgeid, -6) : '–',
                get_string('iosdocs', $pn) => (string)$docs,
                get_string('ioslast', $pn) => $last !== '' ? $last : '–',
            ];
            foreach ($facts as $name => $value) {
                $content .= html_writer::div(html_writer::span(s($name), 'fk-rowtext') .
                    html_writer::span(s($value), 'fk-value'), 'fk-row fk-row-fact');
            }
            $content .= html_writer::end_div();
            $content .= html_writer::div(s(get_string('iosbindingnote', $pn)), 'fk-sheetnote');
            $content .= html_writer::end_div();
        }
        $content .= html_writer::end_div();
        return $content;
    }

    /**
     * Can show chat launcher.
     *
     * @param ?stdClass $rec The rec.
     * @return bool True on success.
     */
    protected function can_show_chat_launcher(?stdClass $rec): bool {
        return \block_fredkitheagent\local\import_completion::chat_ready($rec);
    }

    /**
     * Render chat launcher.
     *
     * @param bool $initiallyhidden The initiallyhidden.
     * @return string The result.
     */
    protected function render_chat_launcher(bool $initiallyhidden = false): string {
        global $COURSE, $OUTPUT;
        // This script must run for BOTH teachers and learners; otherwise the launcher
        // remains an inert button after being moved out of Moodle's block drawer.
        $this->page->requires->js(new moodle_url(
            '/blocks/fredkitheagent/floating_chat.js',
            ['v' => 202610240200]
        ), false);
        $url = new moodle_url('/blocks/fredkitheagent/chat.php', [
            'courseid' => (int)$COURSE->id, 'embedded' => 1,
        ]);
        $courseicon = \block_fredkitheagent\local\course_design::logo_url((int)$COURSE->id);
        $icon = $courseicon ?: $OUTPUT->image_url('icon', 'block_fredkitheagent')->out(false);
        $open = html_writer::tag(
            'button',
            html_writer::empty_tag('img', ['src' => $icon, 'alt' => '', 'class' => 'fredkitheagent-floating-icon']) .
            html_writer::tag('span', get_string('managedchatopen', 'block_fredkitheagent'), ['class' => 'sr-only']),
            [
            'type' => 'button', 'class' => 'fredkitheagent-floating-trigger',
            'aria-haspopup' => 'dialog', 'aria-expanded' => 'false',
            'aria-controls' => 'fredkitheagent-floating-panel-' . (int)$COURSE->id,
            ]
        );
        $menu = html_writer::tag('button', '☰', [
            'type' => 'button', 'class' => 'fredkitheagent-floating-menu',
            'aria-label' => get_string('managedchatmenu', 'block_fredkitheagent'),
        ]);
        $expand = html_writer::tag('button', '⤢', [
            'type' => 'button', 'class' => 'fredkitheagent-floating-expand',
            'aria-label' => get_string('managedchatexpand', 'block_fredkitheagent'),
            'aria-pressed' => 'false',
        ]);
        $close = html_writer::tag('button', '×', [
            'type' => 'button', 'class' => 'fredkitheagent-floating-close',
            'aria-label' => get_string('managedchatclose', 'block_fredkitheagent'),
        ]);
        $head = html_writer::div($menu . $expand . $close, 'fredkitheagent-floating-header');
        $historytitle = html_writer::tag('strong', get_string('managedchathistory', 'block_fredkitheagent'));
        $historyhint = html_writer::tag('p', get_string('managedchathistoryhint', 'block_fredkitheagent'));
        $historylist = html_writer::tag(
            'div',
            '',
            ['class' => 'fredkitheagent-history-list', 'data-fredkitheagent-historylist' => '1']
        );
        $history = html_writer::div($historytitle . $historyhint . $historylist, 'fredkitheagent-floating-history', [
            'hidden' => 'hidden', 'data-fredkitheagent-history' => '1',
            'data-label-delete' => get_string('managedchathistorydelete', 'block_fredkitheagent'),
            'data-label-clear' => get_string('managedchathistoryclear', 'block_fredkitheagent'),
            'data-label-empty' => get_string('managedchathistoryempty', 'block_fredkitheagent'),
        ]);
        $frame = html_writer::tag('iframe', '', [
            'title' => get_string('managedchattitle', 'block_fredkitheagent'),
            'data-chat-src' => $url->out(false), 'class' => 'fredkitheagent-floating-frame',
            'loading' => 'lazy', 'referrerpolicy' => 'same-origin',
        ]);
        $content = html_writer::div(
            $history . html_writer::div($frame, 'fredkitheagent-floating-main'),
            'fredkitheagent-floating-body'
        );
        $panel = html_writer::div($head . $content, 'fredkitheagent-floating-panel', [
            'id' => 'fredkitheagent-floating-panel-' . (int)$COURSE->id,
            'role' => 'dialog',
            'aria-label' => get_string('managedchattitle', 'block_fredkitheagent'),
            'hidden' => 'hidden',
        ]);
        $backdrop = html_writer::div('', 'fredkitheagent-floating-backdrop', ['hidden' => 'hidden']);
        $rootattrs = [
            'data-fredkitheagent-launcher' => '1',
            'data-courseid' => (int)$COURSE->id,
            'data-userid' => (int)$USER->id,
        ];
        if ($initiallyhidden) {
            $rootattrs['hidden'] = 'hidden';
        }
        return html_writer::div($open . $backdrop . $panel, 'fredkitheagent-floating-root', $rootattrs);
    }
}

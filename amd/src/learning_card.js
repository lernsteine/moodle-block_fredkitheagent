// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Persist the FredKI chat visibility per Moodle course.
 *
 * @module     block_fredkitheagent/learning_card
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getStrings} from 'core/str';

/** @var {string[]} Language strings used by this module. */
const STRINGKEYS = ['syncprogresshint2', 'visibilityfailed', 'visibilityhidden', 'visibilitysaving', 'visibilityshown'];

/**
 * Show the progress bar of a running synchronisation inside the block.
 *
 * @param {HTMLFormElement} form The synchronisation form.
 * @param {string} hint Short hint under the bar.
 * @return {object} Elements of the progress box (bar, text).
 */
const progressBox = (form, hint) => {
    let box = form.parentNode.querySelector('.fk-progress');
    if (!box) {
        box = document.createElement('div');
        box.className = 'fk-progress';
        box.setAttribute('role', 'status');
        box.setAttribute('aria-live', 'polite');
        box.innerHTML = '<div class="fk-progressbar"><span></span></div><div class="fk-progresstext"></div>' +
            '<div class="fk-progresshint"></div>';
        form.parentNode.insertBefore(box, form.nextSibling);
    }
    box.querySelector('.fk-progresshint').textContent = hint;
    return {bar: box.querySelector('.fk-progressbar span'), text: box.querySelector('.fk-progresstext')};
};

/**
 * Send one stage of the synchronisation and read the JSON answer.
 *
 * @param {string} url Address of sync.php.
 * @param {string} fields Form fields of the synchronisation form (URL encoded).
 * @param {boolean} continuing Whether this is a following stage.
 * @return {Promise<object>} The answer (state, done, total, text, url).
 */
const syncStage = (url, fields, continuing) => {
    const body = new URLSearchParams(fields);
    body.set('ajax', '1');
    if (continuing) {
        body.set('continue', '1');
    }
    return fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8', 'Accept': 'application/json'},
        body: body.toString(),
        cache: 'no-store'
    }).then((response) => {
        const type = response.headers.get('content-type') || '';
        if (!response.ok || type.indexOf('application/json') === -1) {
            throw new Error('No JSON answer');
        }
        return response.json();
    });
};

/**
 * Wait a moment, then send the same stage again.
 *
 * @param {string} url Address of sync.php.
 * @param {string} fields Form fields of the synchronisation form (URL encoded).
 * @param {number} attempt Number of repetitions so far.
 * @return {Promise<object>} The answer.
 */
function pauseAndRepeat(url, fields, attempt) {
    return new Promise((resolve) => {
        window.setTimeout(resolve, 2000);
    }).then(() => syncStageWithRetry(url, fields, true, attempt + 1));
}

/**
 * Send one stage. A stage that gets no answer (a short network interruption, a busy server) is repeated twice
 * before the caller falls back to the classic page; the stages continue where the previous one stopped,
 * so repeating is safe.
 *
 * @param {string} url Address of sync.php.
 * @param {string} fields Form fields of the synchronisation form (URL encoded).
 * @param {boolean} continuing Whether this is a following stage.
 * @param {number} attempt Number of repetitions so far.
 * @return {Promise<object>} The answer.
 */
function syncStageWithRetry(url, fields, continuing, attempt) {
    return syncStage(url, fields, continuing).catch((error) => {
        if (attempt >= 2) {
            throw error;
        }
        return pauseAndRepeat(url, fields, attempt);
    });
}

/**
 * Run all stages of a synchronisation inside the block, then reload the course page.
 * Falls back to the classic form submission if the server does not answer with JSON.
 *
 * @param {HTMLFormElement} form The synchronisation form.
 * @param {string} hint Short hint under the bar.
 * @param {HTMLFormElement|null} anchor Form under which the bar is shown (the main button), else the form itself.
 */
const runSync = (form, hint, anchor) => {
    const ui = progressBox(anchor || form, hint);
    // Read the fields now: the controls of the card are disabled while the synchronisation runs.
    const fields = new URLSearchParams(new FormData(form)).toString();
    const step = (continuing) => syncStageWithRetry(form.action, fields, continuing, 0).then((answer) => {
        if (answer.state === 'progress') {
            const percent = Math.max(3, Math.min(100, Math.round(100 * answer.done / Math.max(1, answer.total))));
            ui.bar.style.width = percent + '%';
            ui.text.textContent = answer.text || '';
            return step(true);
        }
        ui.bar.style.width = '100%';
        window.location.href = answer.url || window.location.href;
        return answer;
    });
    step(false).catch(() => {
        // Classic flow: the server shows its own progress page.
        form.querySelectorAll('input, select, button').forEach((control) => {
            control.disabled = false;
        });
        HTMLFormElement.prototype.submit.call(form);
    });
};

/**
 * Initialise the module.
 */
export const init = () => {
    const strings = {};
    // Strings are only needed after user actions: load them without delaying the initialisation.
    getStrings(STRINGKEYS.map((key) => ({
            key,
            component: 'block_fredkitheagent'
        })))
        .then((values) => {
            STRINGKEYS.forEach((key, index) => {
                strings[key] = values[index];
            });
            return values;
        })
        .catch(() => {
            // English fallbacks are used.
        });

    /**
     * Language string loaded through core/str, English fallback.
     *
     * @param {string} key The string identifier.
     * @param {string} fallback English text if the string could not be loaded.
     * @return {string} The text.
     */
    function str(key, fallback) {
        return strings[key] || fallback;
    }
    /**
     * Initialise.
     */
    function initialise() {
        document.querySelectorAll('[data-fredki-next-card="1"]').forEach(function(root) {
            if (root.dataset.visibilityReady === '1') {
                return;
            }
            root.dataset.visibilityReady = '1';
            // Quiet settings sheet behind the gear.
            var views = {};
            root.querySelectorAll('[data-fk-view]').forEach(function(view) {
                views[view.getAttribute('data-fk-view')] = view;
            });
            /**
             * Show.
             *
             * @param {*} name Name.
             */
            function show(name) {
                Object.keys(views).forEach(function(key) {
                    views[key].hidden = key !== name;
                });
                var gear = root.querySelector('[data-fk-open="settings"]');
                if (gear) {
                    gear.setAttribute('aria-expanded', name === 'settings' ? 'true' : 'false');
                }
                var focus = name === 'settings' ? root.querySelector('[data-fk-close="settings"]') : gear;
                if (focus) {
                    focus.focus();
                }
            }
            root.querySelectorAll('[data-fk-open]').forEach(function(button) {
                button.addEventListener('click', function() {
                    show(button.getAttribute('data-fk-open'));
                });
            });
            root.querySelectorAll('[data-fk-close]').forEach(function(button) {
                button.addEventListener('click', function() {
                    show('main');
                });
            });
            root.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && views.settings && !views.settings.hidden) {
                    show('main');
                }
            });
            // The big button turns into a calm progress state while the synchronisation runs.
            root.querySelectorAll('form[data-fk-syncform]').forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    var button = form.querySelector('button[type="submit"]');
                    if (!button || root.classList.contains('is-busy')) {
                        return;
                    }
                    // 1.8.4: the stages run in the background; the block shows the progress.
                    if (window.fetch && window.FormData && window.URLSearchParams) {
                        event.preventDefault();
                        // From the info sheet back to the main view, where the bar is shown.
                        var mainform = root.querySelector('[data-fk-view="main"] form[data-fk-syncform]');
                        if (views.main && views.settings && !views.settings.hidden) {
                            show('main');
                        }
                        runSync(form, str('syncprogresshint2', 'Please keep the page open.'), mainform);
                    }
                    root.classList.add('is-busy');
                    button.classList.add('is-busy');
                    button.setAttribute('aria-busy', 'true');
                    var label = button.querySelector('.fk-label');
                    if (label && button.getAttribute('data-fk-busylabel')) {
                        label.textContent = button.getAttribute('data-fk-busylabel');
                    }
                    // Disable AFTER the browser has collected the form data.
                    setTimeout(function() {
                        root.querySelectorAll('button, input').forEach(function(control) {
                            control.disabled = true;
                        });
                    }, 0);
                });
            });
            var checkbox = root.querySelector('[data-fredki-next-togglechat]');
            var message = root.querySelector('[data-fredki-next-result]');
            var url = root.getAttribute('data-visibility-url');
            var courseid = root.getAttribute('data-course-id');
            var sesskey = root.getAttribute('data-sesskey');
            if (!checkbox || !url || !courseid || !sesskey) {
                return;
            }
            checkbox.addEventListener('change', function() {
                var requested = checkbox.checked;
                checkbox.disabled = true;
                if (message) {
                    message.textContent = str('visibilitysaving', 'Saving …');
                }
                var body = new URLSearchParams({
                    courseid: courseid,
                    sesskey: sesskey,
                    visible: requested ? '1' : '0'
                });
                fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: body.toString(),
                    redirect: 'error',
                    cache: 'no-store'
                }).then(function(response) {
                    var contenttype = response.headers.get('content-type') || '';
                    if (!response.ok || contenttype.indexOf('application/json') === -1) {
                        throw new Error('Server rejected change');
                    }
                    return response.json();
                }).then(function(payload) {
                    if (!payload || payload.ok !== true || payload.visible !== requested) {
                        throw new Error('Invalid response');
                    }
                    document.dispatchEvent(new CustomEvent('fredkitheagent:visibility', {
                        detail: {
                            enabled: requested,
                            courseid: courseid
                        }
                    }));
                    if (message) {
                        message.textContent = requested ?
                            str('visibilityshown', 'The chat is visible in the course.') :
                            str('visibilityhidden', 'The chat is hidden in the course.');
                    }
                    return payload;
                }).catch(function() {
                    checkbox.checked = !requested;
                    if (message) {
                        message.textContent = str('visibilityfailed', 'Saving failed. Please try again.');
                    }
                }).finally(function() {
                    checkbox.disabled = false;
                });
            });
        });
    }
    // Coming back via the browser's back button must not leave a disabled card behind.
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    });
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialise);
    } else {
        initialise();
    }
};

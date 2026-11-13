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

/**
 * Initialise the module.
 */
export const init = () => {
    /**
     * Language string from Moodle (lang/<lang>/block_fredkitheagent.php), English fallback.
     *
     * @param {string} key The string identifier.
     * @param {string} fallback English text if the string was not passed to the page.
     * @return {string} The text.
     */
    function str(key, fallback) {
        if (window.M && M.str && M.str.block_fredkitheagent && M.str.block_fredkitheagent[key]) {
            return M.str.block_fredkitheagent[key];
        }
        return fallback;
    }
    function initialise() {
        document.querySelectorAll('[data-fredki-next-card="1"]').forEach(function (root) {
            if (root.dataset.visibilityReady === '1') { return; }
            root.dataset.visibilityReady = '1';
            // Quiet settings sheet behind the gear.
            var views = {};
            root.querySelectorAll('[data-fk-view]').forEach(function (view) { views[view.getAttribute('data-fk-view')] = view; });
            function show(name) {
                Object.keys(views).forEach(function (key) { views[key].hidden = key !== name; });
                var gear = root.querySelector('[data-fk-open="settings"]');
                if (gear) { gear.setAttribute('aria-expanded', name === 'settings' ? 'true' : 'false'); }
                var focus = name === 'settings' ? root.querySelector('[data-fk-close="settings"]') : gear;
                if (focus) { focus.focus(); }
            }
            root.querySelectorAll('[data-fk-open]').forEach(function (button) {
                button.addEventListener('click', function () { show(button.getAttribute('data-fk-open')); });
            });
            root.querySelectorAll('[data-fk-close]').forEach(function (button) {
                button.addEventListener('click', function () { show('main'); });
            });
            root.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && views.settings && !views.settings.hidden) { show('main'); }
            });
            // The big button turns into a calm progress state while the synchronisation runs.
            root.querySelectorAll('form[data-fk-syncform]').forEach(function (form) {
                form.addEventListener('submit', function () {
                    var button = form.querySelector('button[type="submit"]');
                    if (!button || root.classList.contains('is-busy')) { return; }
                    root.classList.add('is-busy');
                    button.classList.add('is-busy');
                    button.setAttribute('aria-busy', 'true');
                    var label = button.querySelector('.fk-label');
                    if (label && button.getAttribute('data-fk-busylabel')) { label.textContent = button.getAttribute('data-fk-busylabel'); }
                    // Disable AFTER the browser has collected the form data.
                    setTimeout(function () {
                        root.querySelectorAll('button, input').forEach(function (control) { control.disabled = true; });
                    }, 0);
                });
            });
            var checkbox = root.querySelector('[data-fredki-next-togglechat]');
            var message = root.querySelector('[data-fredki-next-result]');
            var url = root.getAttribute('data-visibility-url');
            var courseid = root.getAttribute('data-course-id');
            var sesskey = root.getAttribute('data-sesskey');
            if (!checkbox || !url || !courseid || !sesskey) { return; }
            checkbox.addEventListener('change', function () {
                var requested = checkbox.checked;
                checkbox.disabled = true;
                if (message) { message.textContent = str('visibilitysaving', 'Saving …'); }
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
                    redirect: 'error', cache: 'no-store'
                }).then(function (response) {
                    var contenttype = response.headers.get('content-type') || '';
                    if (!response.ok || contenttype.indexOf('application/json') === -1) {
                        throw new Error('Server rejected change');
                    }
                    return response.json();
                }).then(function (payload) {
                    if (!payload || payload.ok !== true || payload.visible !== requested) {
                        throw new Error('Invalid response');
                    }
                    document.dispatchEvent(new CustomEvent('fredkitheagent:visibility', {
                        detail: {enabled: requested, courseid: courseid}
                    }));
                    if (message) {
                        message.textContent = requested
                            ? str('visibilityshown', 'The chat is visible in the course.')
                            : str('visibilityhidden', 'The chat is hidden in the course.');
                    }
                }).catch(function () {
                    checkbox.checked = !requested;
                    if (message) { message.textContent = str('visibilityfailed', 'Saving failed. Please try again.'); }
                }).finally(function () {
                    checkbox.disabled = false;
                });
            });
        });
    }
    // Coming back via the browser's back button must not leave a disabled card behind.
    window.addEventListener('pageshow', function (event) { if (event.persisted) { window.location.reload(); } });
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialise);
    } else { initialise(); }
};

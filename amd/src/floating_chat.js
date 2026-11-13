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
 * Accessible launcher for the protected Moodle-local course chat.
 *
 * @module     block_fredkitheagent/floating_chat
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Initialise the module.
 */
export const init = () => {
    function setup(root) {
        if (root.dataset.ready) { return; }
        root.dataset.ready = '1';
        var courseid = root.getAttribute('data-courseid') || '0';
        var userid = root.getAttribute('data-userid') || '0';
        var storageKey = 'fredki-chat-history-' + courseid + '-u' + userid;
        var block = root.closest('.block_fredkitheagent');
        var onlyLauncher = block && !block.querySelector('.fredkitheagent-brand-card') && !block.querySelector('.fredki-next-card');
        document.body.appendChild(root);
        if (onlyLauncher) { block.hidden = true; block.style.display = 'none'; }
        var trigger = root.querySelector('.fredkitheagent-floating-trigger');
        var panel = root.querySelector('.fredkitheagent-floating-panel');
        var close = root.querySelector('.fredkitheagent-floating-close');
        var expand = root.querySelector('.fredkitheagent-floating-expand');
        var menu = root.querySelector('.fredkitheagent-floating-menu');
        var frame = root.querySelector('.fredkitheagent-floating-frame');
        var backdrop = root.querySelector('.fredkitheagent-floating-backdrop');
        var history = root.querySelector('[data-fredkitheagent-history="1"]');
        var historylist = root.querySelector('[data-fredkitheagent-historylist="1"]');
        if (!trigger || !panel || !close || !frame || !backdrop || !expand || !menu || !history || !historylist) { return; }
        function loadHistory() {
            try { return JSON.parse(sessionStorage.getItem(storageKey) || '[]'); } catch (e) { return []; }
        }
        function saveHistory(items) {
            try { sessionStorage.setItem(storageKey, JSON.stringify(items.slice(0, 20))); } catch (e) { /* ignore */ }
        }
        function renderHistory() {
            var items = loadHistory();
            var labeldelete = history.getAttribute('data-label-delete') || 'Delete';
            var labelclear = history.getAttribute('data-label-clear') || 'Clear';
            var labelempty = history.getAttribute('data-label-empty') || '';
            historylist.innerHTML = '';
            if (!items.length) {
                var empty = document.createElement('p');
                empty.className = 'fredkitheagent-history-empty';
                empty.textContent = labelempty;
                historylist.appendChild(empty);
                return;
            }
            items.forEach(function (item) {
                var row = document.createElement('div');
                row.className = 'fredkitheagent-history-row';
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'fredkitheagent-history-item';
                btn.textContent = item.prompt;
                btn.addEventListener('click', function () {
                    open();
                    setTimeout(function () {
                        if (frame.contentWindow) {
                            frame.contentWindow.postMessage({type: 'fredki:prefill', prompt: item.prompt}, window.location.origin);
                        }
                    }, 200);
                });
                var del = document.createElement('button');
                del.type = 'button';
                del.className = 'fredkitheagent-history-delete';
                del.setAttribute('aria-label', labeldelete);
                del.title = labeldelete;
                del.textContent = '×';
                del.addEventListener('click', function (event) {
                    event.stopPropagation();
                    saveHistory(loadHistory().filter(function (entry) {
                        return !(entry.prompt === item.prompt && entry.time === item.time);
                    }));
                    renderHistory();
                });
                row.appendChild(btn);
                row.appendChild(del);
                historylist.appendChild(row);
            });
            var clear = document.createElement('button');
            clear.type = 'button';
            clear.className = 'fredkitheagent-history-clear';
            clear.textContent = labelclear;
            clear.addEventListener('click', function () {
                saveHistory([]);
                renderHistory();
            });
            historylist.appendChild(clear);
        }
        function addHistory(prompt) {
            if (!prompt) { return; }
            var items = loadHistory().filter(function (item) { return item.prompt !== prompt; });
            items.unshift({prompt: prompt, time: Date.now()});
            saveHistory(items);
            renderHistory();
        }
        function notifyState() {
            window.postMessage({type: 'fredki:chatstate', open: !panel.hidden}, window.location.origin);
        }
        function open() {
            panel.hidden = false; backdrop.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            document.body.classList.add('fredkitheagent-chat-open');
            if (!frame.getAttribute('src')) { frame.setAttribute('src', frame.dataset.chatSrc); }
            renderHistory();
            notifyState();
        }
        function closePanel() {
            panel.hidden = true; backdrop.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('fredkitheagent-chat-open');
            notifyState();
        }
        trigger.addEventListener('click', function (event) { event.preventDefault(); if (panel.hidden) { open(); } else { closePanel(); } });
        close.addEventListener('click', function () { closePanel(); });
        backdrop.addEventListener('click', function () { closePanel(); });
        menu.addEventListener('click', function () {
            history.hidden = !history.hidden;
            panel.classList.toggle('is-history-open', !history.hidden);
            if (!history.hidden) { renderHistory(); }
        });
        expand.addEventListener('click', function () {
            var expanded = panel.classList.toggle('is-expanded');
            expand.setAttribute('aria-pressed', expanded ? 'true' : 'false');
        });
        window.addEventListener('message', function (event) {
            if (event.origin !== window.location.origin || event.source !== frame.contentWindow || !event.data) { return; }
            if (event.data.type === 'fredki:history' && typeof event.data.prompt === 'string') {
                addHistory(event.data.prompt);
            }
        });
        // Changing the teacher checkbox controls visibility of the fixed launcher.
        // No agent is created or deleted and the protected chat endpoint remains unchanged.
        document.addEventListener('fredkitheagent:visibility', function (event) {
            if (!event.detail || String(event.detail.courseid) !== courseid) { return; }
            if (!event.detail.enabled) { closePanel(); }
            root.hidden = !event.detail.enabled;
        });
        document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && !panel.hidden) { event.preventDefault(); closePanel(); } });
        renderHistory();
    }
    function ready() { document.querySelectorAll('[data-fredkitheagent-launcher="1"]').forEach(setup); }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', ready); }
    else { ready(); }
};

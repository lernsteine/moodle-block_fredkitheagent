/*
 * This file is part of Moodle - http://moodle.org/
 * Moodle is free software: you can redistribute it and/or modify it under the terms of the
 * GNU General Public License as published by the Free Software Foundation, either version 3
 * of the License, or (at your option) any later version. Moodle is distributed in the hope
 * that it will be useful, but WITHOUT ANY WARRANTY. See http://www.gnu.org/licenses/ .
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/* Protected Moodle-local FredKI chat. Never submit a traditional HTML page inside the iframe. */
(function () {
    'use strict';


    /* ------------------------------------------------------------------
     * Minimal, safe Markdown renderer for model answers.
     * Builds DOM nodes with createElement/createTextNode ONLY (never innerHTML),
     * links are limited to http(s). Supports: headings, paragraphs, bold, italic,
     * inline code, code blocks, (nested) lists, blockquotes, tables, rules, links.
     * ------------------------------------------------------------------ */
    function el(name, parent) {
        var node = document.createElement(name);
        if (parent) { parent.appendChild(node); }
        return node;
    }
    function inline(text, parent) {
        var rules = [
            {re: /`([^`\n]+)`/, make: function (m, host) { el('code', host).textContent = m[1]; }},
            {re: /\*\*([^\n]+?)\*\*/, make: function (m, host) { inline(m[1], el('strong', host)); }},
            {re: /\[([^\]\n]+)\]\((https?:\/\/[^\s)]+)\)/, make: function (m, host) {
                var a = el('a', host);
                a.href = m[2];
                a.target = '_blank';
                a.rel = 'noopener noreferrer nofollow';
                inline(m[1], a);
            }},
            {re: /\*([^*\s][^*\n]*?)\*/, make: function (m, host) { inline(m[1], el('em', host)); }}
        ];
        var rest = text;
        while (rest.length) {
            var best = null;
            for (var i = 0; i < rules.length; i++) {
                var m = rules[i].re.exec(rest);
                if (m && (best === null || m.index < best.m.index)) { best = {m: m, rule: rules[i]}; }
            }
            if (!best) { parent.appendChild(document.createTextNode(rest)); return; }
            if (best.m.index > 0) { parent.appendChild(document.createTextNode(rest.slice(0, best.m.index))); }
            best.rule.make(best.m, parent);
            rest = rest.slice(best.m.index + best.m[0].length);
        }
    }
    function inlineLines(lines, parent) {
        lines.forEach(function (line, index) {
            if (index > 0) { el('br', parent); }
            inline(line, parent);
        });
    }
    function renderMarkdown(source) {
        var root = document.createDocumentFragment();
        var lines = String(source).replace(/\r\n?/g, '\n').split('\n');
        var i = 0;
        var itemre = /^(\s*)([-*+•]|\d+[.)])\s+(.*)$/;
        function blank(line) { return /^\s*$/.test(line); }
        function nextnonblank(from) {
            while (from < lines.length && blank(lines[from])) { from++; }
            return from;
        }
        function isrow(line) { return /^\s*\|.*\|\s*$/.test(line); }
        function cells(line) { return line.trim().replace(/^\||\|$/g, '').split('|').map(function (c) { return c.trim(); }); }
        while (i < lines.length) {
            var line = lines[i];
            var m;
            if (blank(line)) { i++; continue; }
            if (/^\s*```/.test(line)) {
                var code = [];
                i++;
                while (i < lines.length && !/^\s*```/.test(lines[i])) { code.push(lines[i]); i++; }
                i++;
                el('code', el('pre', root)).textContent = code.join('\n');
                continue;
            }
            if ((m = /^(#{1,6})\s+(.*?)\s*#*\s*$/.exec(line))) {
                inline(m[2], el('h' + Math.min(6, m[1].length + 2), root));
                i++;
                continue;
            }
            if (/^\s*([-*_])(\s*\1){2,}\s*$/.test(line)) { el('hr', root); i++; continue; }
            if (isrow(line) && i + 1 < lines.length && /^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?\s*$/.test(lines[i + 1])) {
                var table = el('table', root);
                var headrow = el('tr', el('thead', table));
                cells(line).forEach(function (c) { inline(c, el('th', headrow)); });
                var body = el('tbody', table);
                i += 2;
                while (i < lines.length && isrow(lines[i])) {
                    var row = el('tr', body);
                    cells(lines[i]).forEach(function (c) { inline(c, el('td', row)); });
                    i++;
                }
                continue;
            }
            if (/^\s*>/.test(line)) {
                var quote = [];
                while (i < lines.length && /^\s*>/.test(lines[i])) { quote.push(lines[i].replace(/^\s*>\s?/, '')); i++; }
                inlineLines(quote, el('blockquote', root));
                continue;
            }
            if (itemre.test(line)) {
                // Nested lists by indentation; indented plain lines continue the current item.
                var stack = [];   // {indent, list}
                var lastitem = null;
                while (i < lines.length) {
                    var cur = lines[i];
                    if (blank(cur)) {
                        var peek = nextnonblank(i);
                        if (peek < lines.length && itemre.test(lines[peek])) { i = peek; continue; }
                        break;
                    }
                    var im = itemre.exec(cur);
                    if (im) {
                        var indent = im[1].replace(/\t/g, '    ').length;
                        var ordered = /\d/.test(im[2].charAt(0));
                        while (stack.length && indent < stack[stack.length - 1].indent) { stack.pop(); }
                        if (!stack.length || indent > stack[stack.length - 1].indent) {
                            var host = stack.length && lastitem ? lastitem : root;
                            var list = el(ordered ? 'ol' : 'ul', host);
                            if (ordered && /^\d+/.test(im[2]) && parseInt(im[2], 10) !== 1) {
                                list.start = parseInt(im[2], 10);
                            }
                            stack.push({indent: indent, list: list});
                        }
                        lastitem = el('li', stack[stack.length - 1].list);
                        inline(im[3], lastitem);
                        i++;
                        continue;
                    }
                    if (/^\s+\S/.test(cur) && lastitem) {
                        el('br', lastitem);
                        inline(cur.trim(), lastitem);
                        i++;
                        continue;
                    }
                    break;
                }
                continue;
            }
            var para = [];
            while (i < lines.length && !blank(lines[i]) && !itemre.test(lines[i]) &&
                    !/^(#{1,6})\s/.test(lines[i]) && !/^\s*```/.test(lines[i]) && !/^\s*>/.test(lines[i])) {
                para.push(lines[i]);
                i++;
            }
            if (!para.length) { para.push(lines[i]); i++; }
            inlineLines(para, el('p', root));
        }
        return root;
    }

    function initializeChat() {
        var root = document.querySelector('[data-fredki-chat="1"]');
        if (!root || root.dataset.chatInitialized === '1') { return; }
        var form = document.getElementById('fredki-course-form');
        var input = document.getElementById('fredki-course-input');
        var list = document.getElementById('fredki-course-messages');
        var button = form && form.querySelector('button[type="submit"]');
        if (!form || !input || !list || !button || typeof fetch !== 'function') { return; }
        var endpoint = root.dataset.endpoint || '';
        var courseid = root.dataset.courseid || '';
        var sesskey = root.dataset.sesskey || '';
        var threadid = root.dataset.threadid || '';
        if (!/^\d+$/.test(courseid) || !/^[a-zA-Z0-9_-]{8,100}$/.test(threadid) || !sesskey || !endpoint) {
            return; // Keep the submit button disabled instead of navigating to Moodle.
        }
        var busy = false;
        function showTyping() {
            var node = add('', 'bot');
            node.classList.add('fredki-typing');
            node.setAttribute('role', 'status');
            node.setAttribute('aria-label', 'FredKI erstellt eine Antwort');
            // Each dot is a real element so CSS can animate it with staggered delays.
            // DOM text is never parsed as HTML and no server response is interpreted as markup.
            for (var i = 0; i < 3; i++) {
                var dot = document.createElement('span');
                dot.className = 'fredki-typing-dot';
                dot.setAttribute('aria-hidden', 'true');
                node.appendChild(dot);
            }
            return node;
        }
        function finishTyping(node, text) {
            node.classList.remove('fredki-typing');
            node.removeAttribute('role');
            node.removeAttribute('aria-label');
            node.textContent = text;
        }
        function add(text, type) {
            var node = document.createElement('div');
            node.className = 'fredki-course-message ' + type;
            node.textContent = text; // Never interpret model output as HTML.
            list.appendChild(node);
            list.scrollTop = list.scrollHeight;
            try {
                if (window.parent && window.parent !== window && type === 'user') {
                    window.parent.postMessage({type: 'fredki:history', prompt: text}, window.location.origin);
                }
            } catch (e) { /* ignore */ }
            return node;
        }
        /**
         * Show the completed answer as formatted Markdown, revealed calmly word by word
         * with a soft fade-in. This is a display animation, not upstream token streaming:
         * the licence gateway returns one completed JSON answer. Pace: roughly 9-15 words
         * per second, about 8 seconds at most; the visitor can skip it.
         */
        function revealAnswer(node, answer) {
            node.classList.remove('fredki-typing');
            node.removeAttribute('role');
            node.removeAttribute('aria-label');
            node.textContent = '';
            node.classList.add('fredki-md');
            var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            var wordcount = (answer.match(/\S+/g) || []).length;
            if (reduce || wordcount < 6 || typeof requestAnimationFrame !== 'function') {
                node.appendChild(renderMarkdown(answer));
                return Promise.resolve();
            }
            // Render the finished markup once, then hide it and uncover it piece by piece.
            node.appendChild(renderMarkdown(answer));
            var pieces = [];
            var walker = document.createTreeWalker(node, NodeFilter.SHOW_TEXT | NodeFilter.SHOW_ELEMENT, null);
            var texts = [];
            var rules = [];
            var hidden = [];
            while (walker.nextNode()) {
                var current = walker.currentNode;
                if (current.nodeType === 3) { texts.push(current); }
                else if (current.tagName === 'HR') { rules.push(current); }
                else if (!/^(STRONG|EM|CODE|A|BR|SPAN)$/.test(current.tagName)) { hidden.push(current); }
            }
            texts.forEach(function (textnode) {
                var tokens = textnode.nodeValue.match(/\S+\s*/g);
                if (!tokens) { return; }   // whitespace only: keep as is
                // Keep leading whitespace (e.g. the space after a bold phrase) with the first word.
                var lead = /^\s+/.exec(textnode.nodeValue);
                if (lead) { tokens[0] = lead[0] + tokens[0]; }
                var fragment = document.createDocumentFragment();
                tokens.forEach(function (token) {
                    var word = document.createElement('span');
                    word.className = 'fredki-word';
                    word.hidden = true;
                    word.textContent = token;
                    fragment.appendChild(word);
                });
                textnode.parentNode.replaceChild(fragment, textnode);
            });
            hidden.forEach(function (block) { block.hidden = true; });
            rules.forEach(function (rule) { rule.hidden = true; });
            // Document order of words and rules.
            node.querySelectorAll('.fredki-word, hr').forEach(function (piece) { pieces.push(piece); });
            if (!pieces.length) { return Promise.resolve(); }
            node.classList.add('fredki-revealing');
            // Only announce the finished, verified answer to screen readers.
            node.setAttribute('aria-hidden', 'true');
            var skip = document.createElement('button');
            skip.type = 'button';
            skip.className = 'fredki-reveal-skip';
            skip.textContent = 'Ganze Antwort anzeigen';
            node.insertAdjacentElement('afterend', skip);
            var perword = Math.min(110, Math.max(65, 8000 / pieces.length));
            var count = 0;
            var start = 0;
            var lastscroll = 0;
            var frameid = 0;
            var completed = false;
            function uncover(piece) {
                for (var up = piece; up && up !== node; up = up.parentElement) { up.hidden = false; }
            }
            function nearbottom() {
                return list.scrollHeight - list.scrollTop - list.clientHeight < 120;
            }
            return new Promise(function (resolve) {
                function finish() {
                    if (completed) { return; }
                    completed = true;
                    if (frameid) { cancelAnimationFrame(frameid); }
                    node.textContent = '';
                    node.appendChild(renderMarkdown(answer));
                    node.classList.remove('fredki-revealing');
                    node.removeAttribute('aria-hidden');
                    skip.remove();
                    list.scrollTop = list.scrollHeight;
                    resolve();
                }
                function draw(timestamp) {
                    if (completed) { return; }
                    if (!start) { start = timestamp; }
                    var target = Math.min(pieces.length, Math.floor((timestamp - start) / perword) + 1);
                    if (target > count) {
                        var follow = nearbottom();
                        for (; count < target; count++) { uncover(pieces[count]); }
                        // Follow the text smoothly, but only while the reader has not scrolled away.
                        if (follow && timestamp - lastscroll > 250) {
                            lastscroll = timestamp;
                            if (list.scrollTo) { list.scrollTo({top: list.scrollHeight, behavior: 'smooth'}); }
                            else { list.scrollTop = list.scrollHeight; }
                        }
                    }
                    if (count >= pieces.length) {
                        // Let the last word finish fading before swapping in the final markup.
                        setTimeout(finish, 500);
                        return;
                    }
                    frameid = requestAnimationFrame(draw);
                }
                skip.addEventListener('click', finish);
                frameid = requestAnimationFrame(draw);
            });
        }
        form.addEventListener('submit', function (event) {
            event.preventDefault(); // MUST be installed before enabling Send.
            var question = input.value.trim();
            if (busy || !question || question.length > 5000) { return; }
            busy = true;
            button.disabled = true;
            input.value = '';
            add(question, 'user');
            var waiting = showTyping();
            var body = new URLSearchParams({
                sesskey: sesskey,
                courseid: courseid,
                threadid: threadid,
                message: question
            });
            fetch(endpoint, {
                method: 'POST', credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: body.toString(), cache: 'no-store', redirect: 'error'
            }).then(function (response) {
                // Moodle redirects and HTML errors must NEVER be embedded as the bot reply.
                var type = response.headers && response.headers.get('content-type') || '';
                if (response.redirected || type.indexOf('application/json') === -1) {
                    throw new Error('Unexpected chat response');
                }
                return response.json().then(function (payload) {
                    if (!response.ok) {
                        if (payload && payload.error_code === 'CHAT_EMPTY') {
                            throw new Error('CHAT_EMPTY');
                        }
                        throw new Error('Unexpected chat response');
                    }
                    return payload;
                });
            }).then(function (answer) {
                if (!answer || answer.ok !== true || typeof answer.answer !== 'string') {
                    throw new Error('Chat reply missing');
                }
                if (answer.answer.trim() === '') {
                    throw new Error('CHAT_EMPTY');
                }
                return revealAnswer(waiting, answer.answer);
            }).catch(function (error) {
                finishTyping(waiting, error && error.message === 'CHAT_EMPTY'
                    ? 'FredKI hat keinen Antworttext erzeugt. Bitte das KI-Sprachmodell und den Antwortknoten des Kursagenten prüfen.'
                    : 'Die Antwort konnte nicht geladen werden. Bitte versuche es erneut.');
            }).finally(function () {
                busy = false;
                button.disabled = false;
                list.scrollTop = list.scrollHeight;
                input.focus();
            });
        });
        window.addEventListener('message', function (event) {
            if (event.origin !== window.location.origin || event.source !== window.parent ||
                    !event.data || event.data.type !== 'fredki:prefill' ||
                    typeof event.data.prompt !== 'string' || event.data.prompt.length > 1200) {
                return;
            }
            input.value = event.data.prompt;
            input.focus();
        });
        root.dataset.chatInitialized = '1';
        button.disabled = false;
    }

    // Moodle themes may place a script in the head; wait for the markup in either case.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeChat);
    } else {
        initializeChat();
    }
})();

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
 * Protected Moodle-local FredKI course chat.
 *
 * @module     block_fredkitheagent/course_chat
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getStrings} from 'core/str';

/** @var {string[]} Language strings used by this module. */
const STRINGKEYS = ['chatdetached', 'chaterrorempty', 'chaterrorload', 'chatshowfullanswer'];

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

    /* ------------------------------------------------------------------
     * Minimal, safe Markdown renderer for model answers.
     * Builds DOM nodes with createElement/createTextNode ONLY (never innerHTML),
     * links are limited to http(s). Supports: headings, paragraphs, bold, italic,
     * inline code, code blocks, (nested) lists, blockquotes, tables, rules, links.
     * ------------------------------------------------------------------ */

    /**
     * Create an element and append it to a parent.
     *
     * @param {string} name Tag name.
     * @param {HTMLElement} parent Parent node.
     * @return {HTMLElement} The new element.
     */
    function el(name, parent) {
        var node = document.createElement(name);
        if (parent) {
            parent.appendChild(node);
        }
        return node;
    }
    /**
     * Inline.
     *
     * @param {*} text Text.
     * @param {*} parent Parent.
     */
    function inline(text, parent) {
        var rules = [{
            re: /`([^`\n]+)`/,
            make: function(m, host) {
                el('code', host).textContent = m[1];
            }
        }, {
            re: /\*\*([^\n]+?)\*\*/,
            make: function(m, host) {
                inline(m[1], el('strong', host));
            }
        }, {
            re: /\[([^\]\n]+)\]\((https?:\/\/[^\s)]+)\)/,
            make: function(m, host) {
                var a = el('a', host);
                a.href = m[2];
                a.target = '_blank';
                a.rel = 'noopener noreferrer nofollow';
                inline(m[1], a);
            }
        }, {
            re: /\*([^*\s][^*\n]*?)\*/,
            make: function(m, host) {
                inline(m[1], el('em', host));
            }
        }];
        var rest = text;
        while (rest.length) {
            var best = null;
            for (var i = 0; i < rules.length; i++) {
                var m = rules[i].re.exec(rest);
                if (m && (best === null || m.index < best.m.index)) {
                    best = {
                        m: m,
                        rule: rules[i]
                    };
                }
            }
            if (!best) {
                parent.appendChild(document.createTextNode(rest));
                return;
            }
            if (best.m.index > 0) {
                parent.appendChild(document.createTextNode(rest.slice(0, best.m.index)));
            }
            best.rule.make(best.m, parent);
            rest = rest.slice(best.m.index + best.m[0].length);
        }
    }
    /**
     * Inline lines.
     *
     * @param {*} lines Lines.
     * @param {*} parent Parent.
     */
    function inlineLines(lines, parent) {
        lines.forEach(function(line, index) {
            if (index > 0) {
                el('br', parent);
            }
            inline(line, parent);
        });
    }
    /** @var {RegExp} A list item: indentation, bullet or number, text. */
    var ITEMRE = /^(\s*)([-*+•]|\d+[.)])\s+(.*)$/;

    /**
     * Whether a line is empty.
     *
     * @param {string} line Line.
     * @return {boolean}
     */
    function mdBlank(line) {
        return /^\s*$/.test(line);
    }

    /**
     * Whether a line is a table row.
     *
     * @param {string} line Line.
     * @return {boolean}
     */
    function mdIsRow(line) {
        return /^\s*\|.*\|\s*$/.test(line);
    }

    /**
     * Cells of a table row.
     *
     * @param {string} line Line.
     * @return {string[]}
     */
    function mdCells(line) {
        return line.trim().replace(/^\||\|$/g, '').split('|').map(function(c) {
            return c.trim();
        });
    }

    /**
     * Fenced code block.
     *
     * @param {string[]} lines All lines.
     * @param {number} i Current line.
     * @param {Node} root Target.
     * @return {number} Next line, or i if this is no code block.
     */
    function mdCode(lines, i, root) {
        if (!/^\s*```/.test(lines[i])) {
            return i;
        }
        var code = [];
        var j = i + 1;
        while (j < lines.length && !/^\s*```/.test(lines[j])) {
            code.push(lines[j]);
            j++;
        }
        el('code', el('pre', root)).textContent = code.join('\n');
        return j + 1;
    }

    /**
     * Heading.
     *
     * @param {string[]} lines All lines.
     * @param {number} i Current line.
     * @param {Node} root Target.
     * @return {number} Next line, or i if this is no heading.
     */
    function mdHeading(lines, i, root) {
        var m = /^(#{1,6})\s+(.*?)\s*#*\s*$/.exec(lines[i]);
        if (!m) {
            return i;
        }
        inline(m[2], el('h' + Math.min(6, m[1].length + 2), root));
        return i + 1;
    }

    /**
     * Horizontal rule.
     *
     * @param {string[]} lines All lines.
     * @param {number} i Current line.
     * @param {Node} root Target.
     * @return {number} Next line, or i if this is no rule.
     */
    function mdRule(lines, i, root) {
        if (!/^\s*([-*_])(\s*\1){2,}\s*$/.test(lines[i])) {
            return i;
        }
        el('hr', root);
        return i + 1;
    }

    /**
     * Table with a header row and a separator row.
     *
     * @param {string[]} lines All lines.
     * @param {number} i Current line.
     * @param {Node} root Target.
     * @return {number} Next line, or i if this is no table.
     */
    function mdTable(lines, i, root) {
        var separator = /^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?\s*$/;
        if (!mdIsRow(lines[i]) || i + 1 >= lines.length || !separator.test(lines[i + 1])) {
            return i;
        }
        var table = el('table', root);
        var headrow = el('tr', el('thead', table));
        mdCells(lines[i]).forEach(function(c) {
            inline(c, el('th', headrow));
        });
        var body = el('tbody', table);
        var j = i + 2;
        var rows = [];
        while (j < lines.length && mdIsRow(lines[j])) {
            rows.push(lines[j]);
            j++;
        }
        rows.forEach(function(line) {
            var row = el('tr', body);
            mdCells(line).forEach(function(c) {
                inline(c, el('td', row));
            });
        });
        return j;
    }

    /**
     * Block quote.
     *
     * @param {string[]} lines All lines.
     * @param {number} i Current line.
     * @param {Node} root Target.
     * @return {number} Next line, or i if this is no quote.
     */
    function mdQuote(lines, i, root) {
        var quote = [];
        var j = i;
        while (j < lines.length && /^\s*>/.test(lines[j])) {
            quote.push(lines[j].replace(/^\s*>\s?/, ''));
            j++;
        }
        if (j > i) {
            inlineLines(quote, el('blockquote', root));
        }
        return j;
    }

    /**
     * Add one list item, opening or closing nested lists by indentation.
     *
     * @param {Array} match Result of ITEMRE.
     * @param {Object} state stack of {indent, list}, last item, root.
     */
    function mdListItem(match, state) {
        var indent = match[1].replace(/\t/g, '    ').length;
        var ordered = /\d/.test(match[2].charAt(0));
        while (state.stack.length && indent < state.stack[state.stack.length - 1].indent) {
            state.stack.pop();
        }
        if (!state.stack.length || indent > state.stack[state.stack.length - 1].indent) {
            var host = state.stack.length && state.last ? state.last : state.root;
            var list = el(ordered ? 'ol' : 'ul', host);
            if (ordered && /^\d+/.test(match[2]) && parseInt(match[2], 10) !== 1) {
                list.start = parseInt(match[2], 10);
            }
            state.stack.push({indent: indent, list: list});
        }
        state.last = el('li', state.stack[state.stack.length - 1].list);
        inline(match[3], state.last);
    }

    /**
     * Index of the next line that is not empty.
     *
     * @param {string[]} lines All lines.
     * @param {number} from Start.
     * @return {number}
     */
    function mdNextNonBlank(lines, from) {
        while (from < lines.length && mdBlank(lines[from])) {
            from++;
        }
        return from;
    }

    /**
     * Lists, nested by indentation; indented plain lines continue the current item.
     *
     * @param {string[]} lines All lines.
     * @param {number} i Current line.
     * @param {Node} root Target.
     * @return {number} Next line, or i if this is no list.
     */
    function mdList(lines, i, root) {
        if (!ITEMRE.test(lines[i])) {
            return i;
        }
        var state = {stack: [], last: null, root: root};
        var j = i;
        while (j < lines.length) {
            var cur = lines[j];
            var match = ITEMRE.exec(cur);
            if (mdBlank(cur)) {
                var peek = mdNextNonBlank(lines, j);
                if (peek >= lines.length || !ITEMRE.test(lines[peek])) {
                    break;
                }
                j = peek;
            } else if (match) {
                mdListItem(match, state);
                j++;
            } else if (/^\s+\S/.test(cur) && state.last) {
                el('br', state.last);
                inline(cur.trim(), state.last);
                j++;
            } else {
                break;
            }
        }
        return j;
    }

    /**
     * Paragraph: all following lines up to the next block.
     *
     * @param {string[]} lines All lines.
     * @param {number} i Current line.
     * @param {Node} root Target.
     * @return {number} Next line.
     */
    function mdParagraph(lines, i, root) {
        var para = [];
        var j = i;
        while (j < lines.length && !mdBlank(lines[j]) && !ITEMRE.test(lines[j]) &&
            !/^(#{1,6})\s/.test(lines[j]) && !/^\s*```/.test(lines[j]) && !/^\s*>/.test(lines[j])) {
            para.push(lines[j]);
            j++;
        }
        if (!para.length) {
            para.push(lines[j]);
            j++;
        }
        inlineLines(para, el('p', root));
        return j;
    }

    /**
     * Render markdown.
     *
     * @param {*} source Source.
     * @return {DocumentFragment}
     */
    function renderMarkdown(source) {
        var root = document.createDocumentFragment();
        var lines = String(source).replace(/\r\n?/g, '\n').split('\n');
        var blocks = [mdCode, mdHeading, mdRule, mdTable, mdQuote, mdList, mdParagraph];
        var i = 0;
        while (i < lines.length) {
            if (mdBlank(lines[i])) {
                i++;
                continue;
            }
            for (var b = 0; b < blocks.length; b++) {
                var next = blocks[b](lines, i, root);
                if (next > i) {
                    i = next;
                    break;
                }
            }
        }
        return root;
    }

    /**
     * Error for a refused chat request, from the JSON answer of chat_send.php.
     *
     * @param {Object} payload JSON answer.
     * @return {Error}
     */
    function chatError(payload) {
        var code = payload && payload.error_code;
        var text = payload && typeof payload.error === 'string' ? payload.error : '';
        var error;
        if (code === 'CHAT_EMPTY') {
            error = new Error('CHAT_EMPTY');
        } else if (code === 'FREE_QUOTA' || code === 'COURSE_LIMIT') {
            error = new Error('LIMIT');
        } else if (code === 'COURSE_DETACHED') {
            error = new Error('COURSE_DETACHED');
        } else {
            error = new Error('Unexpected chat response');
        }
        error.text = text;
        return error;
    }

    /**
     * Initialize chat.
     */
    function initializeChat() {
        var root = document.querySelector('[data-fredki-chat="1"]');
        if (!root || root.dataset.chatInitialized === '1') {
            return;
        }
        var form = document.getElementById('fredki-course-form');
        var input = document.getElementById('fredki-course-input');
        var list = document.getElementById('fredki-course-messages');
        var button = form && form.querySelector('button[type="submit"]');
        if (!form || !input || !list || !button || typeof fetch !== 'function') {
            return;
        }
        var endpoint = root.dataset.endpoint || '';
        var courseid = root.dataset.courseid || '';
        var sesskey = root.dataset.sesskey || '';
        var threadid = root.dataset.threadid || '';
        if (!/^\d+$/.test(courseid) || !/^[a-zA-Z0-9_-]{8,100}$/.test(threadid) || !sesskey || !endpoint) {
            return; // Keep the submit button disabled instead of navigating to Moodle.
        }
        var busy = false;
        /**
         * Show typing.
         */
        function showTyping() {
            var node = add('', 'bot');
            node.classList.add('fredki-typing');
            node.setAttribute('role', 'status');
            node.setAttribute('aria-label', root.dataset.typinglabel || 'FredKI');
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
        /**
         * Finish typing.
         *
         * @param {*} node Node.
         * @param {*} text Text.
         */
        function finishTyping(node, text) {
            node.classList.remove('fredki-typing');
            node.removeAttribute('role');
            node.removeAttribute('aria-label');
            node.textContent = text;
        }
        /**
         * Add.
         *
         * @param {*} text Text.
         * @param {*} type Type.
         */
        function add(text, type) {
            var node = document.createElement('div');
            node.className = 'fredki-course-message ' + type;
            node.textContent = text; // Never interpret model output as HTML.
            list.appendChild(node);
            list.scrollTop = list.scrollHeight;
            try {
                if (window.parent && window.parent !== window && type === 'user') {
                    window.parent.postMessage({
                        type: 'fredki:history',
                        prompt: text
                    }, window.location.origin);
                }
            } catch (e) {
                // The parent page may not listen; the chat works without it.
            }
            return node;
        }
        /**
         * Show the completed answer as formatted Markdown, revealed calmly word by word
         * with a soft fade-in. This is a display animation, not upstream token streaming:
         * the licence gateway returns one completed JSON answer. Pace: roughly 9-15 words
         * per second, about 8 seconds at most; the visitor can skip it.
         * @param {*} node Node.
         * @param {*} answer Answer.
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
            var walker = document.createTreeWalker(node, NodeFilter.SHOW_TEXT + NodeFilter.SHOW_ELEMENT, null);
            var texts = [];
            var rules = [];
            var hidden = [];
            while (walker.nextNode()) {
                var current = walker.currentNode;
                if (current.nodeType === 3) {
                    texts.push(current);
                } else if (current.tagName === 'HR') {
                    rules.push(current);
                } else if (!/^(STRONG|EM|CODE|A|BR|SPAN)$/.test(current.tagName)) {
                    hidden.push(current);
                }
            }
            texts.forEach(function(textnode) {
                var tokens = textnode.nodeValue.match(/\S+\s*/g);
                if (!tokens) {
                    // Whitespace only: keep as is.
                    return;
                }
                // Keep leading whitespace (e.g. the space after a bold phrase) with the first word.
                var lead = /^\s+/.exec(textnode.nodeValue);
                if (lead) {
                    tokens[0] = lead[0] + tokens[0];
                }
                var fragment = document.createDocumentFragment();
                tokens.forEach(function(token) {
                    var word = document.createElement('span');
                    word.className = 'fredki-word';
                    word.hidden = true;
                    word.textContent = token;
                    fragment.appendChild(word);
                });
                textnode.parentNode.replaceChild(fragment, textnode);
            });
            hidden.forEach(function(block) {
                block.hidden = true;
            });
            rules.forEach(function(rule) {
                rule.hidden = true;
            });
            // Document order of words and rules.
            node.querySelectorAll('.fredki-word, hr').forEach(function(piece) {
                pieces.push(piece);
            });
            if (!pieces.length) {
                return Promise.resolve();
            }
            node.classList.add('fredki-revealing');
            // Only announce the finished, verified answer to screen readers.
            node.setAttribute('aria-hidden', 'true');
            var skip = document.createElement('button');
            skip.type = 'button';
            skip.className = 'fredki-reveal-skip';
            skip.textContent = str('chatshowfullanswer', 'Show full answer');
            node.insertAdjacentElement('afterend', skip);
            var perword = Math.min(110, Math.max(65, 8000 / pieces.length));
            var count = 0;
            var start = 0;
            var lastscroll = 0;
            var frameid = 0;
            var completed = false;
            /**
             * Uncover.
             *
             * @param {*} piece Piece.
             */
            function uncover(piece) {
                for (var up = piece; up && up !== node; up = up.parentElement) {
                    up.hidden = false;
                }
            }
            /**
             * Nearbottom.
             */
            function nearbottom() {
                return list.scrollHeight - list.scrollTop - list.clientHeight < 120;
            }
            return new Promise(function(resolve) {
                /**
                 * Finish.
                 */
                function finish() {
                    if (completed) {
                        return;
                    }
                    completed = true;
                    if (frameid) {
                        cancelAnimationFrame(frameid);
                    }
                    node.textContent = '';
                    node.appendChild(renderMarkdown(answer));
                    node.classList.remove('fredki-revealing');
                    node.removeAttribute('aria-hidden');
                    skip.remove();
                    list.scrollTop = list.scrollHeight;
                    resolve();
                }
                /**
                 * Draw.
                 *
                 * @param {*} timestamp Timestamp.
                 */
                function draw(timestamp) {
                    if (completed) {
                        return;
                    }
                    if (!start) {
                        start = timestamp;
                    }
                    var target = Math.min(pieces.length, Math.floor((timestamp - start) / perword) + 1);
                    if (target > count) {
                        var follow = nearbottom();
                        for (; count < target; count++) {
                            uncover(pieces[count]);
                        }
                        // Follow the text smoothly, but only while the reader has not scrolled away.
                        if (follow && timestamp - lastscroll > 250) {
                            lastscroll = timestamp;
                            if (list.scrollTo) {
                                list.scrollTo({
                                    top: list.scrollHeight,
                                    behavior: 'smooth'
                                });
                            } else {
                                list.scrollTop = list.scrollHeight;
                            }
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
        form.addEventListener('submit', function(event) {
            event.preventDefault(); // MUST be installed before enabling Send.
            var question = input.value.trim();
            if (busy || !question || question.length > 5000) {
                return;
            }
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
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: body.toString(),
                cache: 'no-store',
                redirect: 'error'
            }).then(function(response) {
                // Moodle redirects and HTML errors must NEVER be embedded as the bot reply.
                var type = response.headers && response.headers.get('content-type') || '';
                if (response.redirected || type.indexOf('application/json') === -1) {
                    throw new Error('Unexpected chat response');
                }
                return Promise.all([response.ok, response.json()]);
            }).then(function(result) {
                if (!result[0]) {
                    throw chatError(result[1]);
                }
                return result[1];
            }).then(function(answer) {
                if (!answer || answer.ok !== true || typeof answer.answer !== 'string') {
                    throw new Error('Chat reply missing');
                }
                if (answer.answer.trim() === '') {
                    throw new Error('CHAT_EMPTY');
                }
                return revealAnswer(waiting, answer.answer);
            }).catch(function(error) {
                if (error && error.message === 'LIMIT') {
                    finishTyping(waiting, error.text || str('chaterrorload', 'The answer could not be loaded. Please try again.'));
                    return;
                }
                if (error && error.message === 'COURSE_DETACHED') {
                    finishTyping(waiting, error.text ||
                        str('chatdetached', 'The FredKI chat is not available for this course at the moment.'));
                    input.disabled = true;
                    return;
                }
                finishTyping(waiting, error && error.message === 'CHAT_EMPTY' ?
                    str('chaterrorempty',
                        'FredKI produced no answer text. Please check the AI model and the answer node of the course agent.') :
                    str('chaterrorload', 'The answer could not be loaded. Please try again.'));
            }).finally(function() {
                busy = false;
                // Stays disabled only after the course was disconnected by the administrator.
                button.disabled = input.disabled;
                list.scrollTop = list.scrollHeight;
                input.focus();
            });
        });
        window.addEventListener('message', function(event) {
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
};

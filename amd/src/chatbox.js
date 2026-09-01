// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AMD module for local_ai_tutor.
 *
 * Drives the floating chat widget: generates a session_id client-side when
 * the widget first opens, tracks whether `content` has already been sent for
 * that session (per CLAUDE.md's session-once flow), streams the Foundry SSE
 * response via chat.php, and renders the answer/citations/connects_to/
 * practice_problems from the terminal `result` event.
 *
 * @module     local_ai_tutor/chatbox
 * @copyright  2026 Highskills and more <info@highskills.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/str', 'core/templates'], function(Str, Templates) {

    'use strict';

    /** @type {Object} Config injected via js_call_amd. */
    var cfg = {};

    /** @type {Object} Language strings. */
    var strings = {};

    /**
     * Per-widget session state. A fresh session_id is minted each time the
     * page loads — reused for every message sent while this widget instance
     * stays open, per the "session-once" content flow.
     */
    var state = {
        sessionId: null,
        needsContent: true,
        recentQuestions: [],
        busy: false,
    };

    /**
     * Generate a UUID-shaped session_id, using the platform RNG when available.
     *
     * Falls back to a Math.random()-based generator on older browsers — not
     * cryptographically strong, but session_id only needs to be unique per
     * open widget, not unguessable.
     *
     * @returns {string} A UUID-shaped random string.
     */
    function generateSessionId() {
        if (window.crypto && window.crypto.randomUUID) {
            return window.crypto.randomUUID();
        }
        var group = function(length) {
            var out = '';
            for (var i = 0; i < length; i++) {
                out += Math.floor(Math.random() * 16).toString(16);
            }
            return out;
        };
        return [group(8), group(4), group(4), group(4), group(12)].join('-');
    }

    /**
     * Append a message bubble to the message list.
     *
     * @param {string} role Either 'user' or 'tutor'.
     * @param {string} html Inner HTML for the bubble body.
     * @returns {Element} The created bubble element.
     */
    function appendBubble(role, html) {
        var messages = document.getElementById('local-ai-tutor-messages');
        var bubble = document.createElement('div');
        bubble.className = 'local-ai-tutor-bubble local-ai-tutor-bubble-' + role;
        bubble.innerHTML = html;
        messages.appendChild(bubble);
        messages.scrollTop = messages.scrollHeight;
        return bubble;
    }

    /**
     * Strip the model's inline [Cn:confidence] citation tags out of the
     * displayed answer text — citations are rendered separately as chips.
     *
     * @param {string} answer Raw answer text from the result event.
     * @returns {string} Answer text with citation tags removed.
     */
    function stripCitationTags(answer) {
        return (answer || '').replace(/\[C\d+:(high|medium)\]/g, '').trim();
    }

    /**
     * Render the citation chip row under an answer bubble.
     *
     * @param {Element} bubble The answer bubble to append chips to.
     * @param {Array} citations Array of {id, title, url, confidence, source_type}.
     */
    function renderCitations(bubble, citations) {
        if (!citations || !citations.length) {
            return;
        }
        var row = document.createElement('div');
        row.className = 'local-ai-tutor-citations';
        bubble.appendChild(row);

        citations.forEach(function(citation) {
            Templates.renderForPromise('local_ai_tutor/citation', {
                id: citation.id,
                title: citation.title,
                url: citation.url,
                confidencehigh: citation.confidence === 'high',
                confidencelabel: citation.confidence,
            }).then(function(result) {
                var wrapper = document.createElement('span');
                row.appendChild(wrapper);
                return Templates.replaceNodeContents(wrapper, result.html, result.js);
            }).catch(function() {
                // Swallow render failures — the chip simply doesn't appear.
            });
        });
    }

    /**
     * Render the "connects the dots" related-content row.
     *
     * @param {Element} bubble The answer bubble to append the row to.
     * @param {Array} connectsTo Array of {title, url, source_type}.
     */
    function renderConnectsTo(bubble, connectsTo) {
        if (!connectsTo || !connectsTo.length) {
            return;
        }
        var wrapper = document.createElement('div');
        bubble.appendChild(wrapper);

        Templates.renderForPromise('local_ai_tutor/connects_to', {
            label: strings.connectsthedots,
            items: connectsTo,
        }).then(function(result) {
            return Templates.replaceNodeContents(wrapper, result.html, result.js);
        }).catch(function() {
            // Swallow render failures — the section simply doesn't appear.
        });
    }

    /**
     * Render stuck-student practice problems.
     *
     * @param {Element} bubble The answer bubble to append the section to.
     * @param {Array} problems Array of practice-problem strings.
     */
    function renderPracticeProblems(bubble, problems) {
        if (!problems || !problems.length) {
            return;
        }
        var wrapper = document.createElement('div');
        bubble.appendChild(wrapper);

        Templates.renderForPromise('local_ai_tutor/practice_problems', {
            label: strings.practiceproblems,
            items: problems.map(function(text) {
                return {text: text};
            }),
        }).then(function(result) {
            return Templates.replaceNodeContents(wrapper, result.html, result.js);
        }).catch(function() {
            // Swallow render failures — the section simply doesn't appear.
        });
    }

    /**
     * Set the transient status line under the message list.
     *
     * @param {string} text Status text, or empty string to clear.
     */
    function setStatus(text) {
        document.getElementById('local-ai-tutor-status').textContent = text || '';
    }

    /**
     * Handle one parsed SSE event.
     *
     * @param {Object} msg Parsed JSON event.
     * @param {string} question The question this turn answers, for a content_required retry.
     */
    function handleEvent(msg, question) {
        switch (msg.event) {
            case 'agent_start':
                setStatus(strings.thinking);
                break;

            case 'error':
                setStatus('');
                appendBubble('tutor', escapeHtml(msg.message || strings.unknownerror));
                state.busy = false;
                break;

            case 'result':
                setStatus('');
                if (msg.content_required) {
                    // Session cache expired or was never populated — resend content and retry.
                    state.needsContent = true;
                    sendMessage(question);
                    return;
                }

                state.needsContent = false;
                state.recentQuestions.push(question);
                state.recentQuestions = state.recentQuestions.slice(-5);

                var bubble = appendBubble('tutor', escapeHtml(stripCitationTags(msg.answer)));
                renderCitations(bubble, msg.citations);
                renderConnectsTo(bubble, msg.connects_to);
                if (msg.stuck) {
                    renderPracticeProblems(bubble, msg.practice_problems);
                }
                state.busy = false;
                break;

            case 'done':
                state.busy = false;
                break;
        }
    }

    /**
     * Escape HTML special characters before inserting text into innerHTML.
     *
     * @param {string} text Raw text.
     * @returns {string} Escaped text.
     */
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    /**
     * Read one chunk from the SSE stream and schedule the next read.
     *
     * @param {ReadableStreamDefaultReader} reader The response body reader.
     * @param {TextDecoder} decoder Shared UTF-8 decoder.
     * @param {string} buffer Undecoded tail from the previous chunk.
     * @param {string} question The question this stream is answering.
     * @returns {Promise} Resolves when the stream is exhausted.
     */
    function pumpStream(reader, decoder, buffer, question) {
        return reader.read().then(function(result) {
            if (result.done) {
                return null;
            }

            buffer += decoder.decode(result.value, {stream: true});

            var blocks = buffer.split('\n\n');
            buffer = blocks.pop();

            blocks.forEach(function(block) {
                var dataLine = null;
                block.split('\n').forEach(function(line) {
                    if (line.indexOf('data: ') === 0 && dataLine === null) {
                        dataLine = line.slice(6);
                    }
                });
                if (dataLine === null) {
                    return;
                }
                try {
                    handleEvent(JSON.parse(dataLine), question);
                } catch (e) {
                    // Ignore malformed JSON lines.
                }
            });

            return pumpStream(reader, decoder, buffer, question);
        });
    }

    /**
     * Submit a question and consume the SSE response.
     *
     * @param {string} question The question text.
     */
    function sendMessage(question) {
        state.busy = true;

        var body = new URLSearchParams();
        body.append('sesskey', cfg.sesskey);
        body.append('course_id', cfg.courseId);
        body.append('session_id', state.sessionId);
        body.append('question', question);
        body.append('needs_content', state.needsContent ? '1' : '0');
        body.append('recent_questions', JSON.stringify(state.recentQuestions));

        fetch(cfg.chatUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: body.toString(),
        }).then(function(response) {
            if (!response.ok) {
                return Promise.reject(new Error((strings.httpstatuslabel || 'HTTP') + ' ' + response.status));
            }
            var reader = response.body.getReader();
            var decoder = new TextDecoder();
            return pumpStream(reader, decoder, '', question);
        }).catch(function(err) {
            setStatus('');
            appendBubble('tutor', escapeHtml(String(err)));
            state.busy = false;
        });
    }

    return {
        /**
         * Initialise the chat widget.
         *
         * @param {Object} config Configuration object from the server.
         * @param {number} config.courseId Course ID the widget is scoped to.
         * @param {string} config.sesskey Moodle session key.
         * @param {string} config.chatUrl URL of chat.php.
         */
        init: function(config) {
            cfg = config || {};
            state.sessionId = generateSessionId();

            var widget = document.getElementById('local-ai-tutor-widget');
            var toggle = document.getElementById('local-ai-tutor-toggle');
            var close = document.getElementById('local-ai-tutor-close');
            var form = document.getElementById('local-ai-tutor-form');
            var input = document.getElementById('local-ai-tutor-input');
            if (!widget || !form || !input) {
                return;
            }

            toggle.addEventListener('click', function() {
                widget.classList.toggle('local-ai-tutor-collapsed');
            });
            close.addEventListener('click', function() {
                widget.classList.add('local-ai-tutor-collapsed');
            });

            Str.get_strings([
                {key: 'chatplaceholder', component: 'local_ai_tutor'},
                {key: 'send', component: 'local_ai_tutor'},
                {key: 'thinking', component: 'local_ai_tutor'},
                {key: 'connectsthedots', component: 'local_ai_tutor'},
                {key: 'practiceproblems', component: 'local_ai_tutor'},
                {key: 'unknownerror', component: 'local_ai_tutor'},
                {key: 'httpstatuslabel', component: 'local_ai_tutor'},
            ]).then(function(s) {
                strings.chatplaceholder = s[0];
                strings.send = s[1];
                strings.thinking = s[2];
                strings.connectsthedots = s[3];
                strings.practiceproblems = s[4];
                strings.unknownerror = s[5];
                strings.httpstatuslabel = s[6];

                input.setAttribute('placeholder', strings.chatplaceholder);
                document.getElementById('local-ai-tutor-send').textContent = strings.send;

                return null;
            }).catch(function() {
                // If string loading fails the form still works with blank labels.
            });

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                if (state.busy) {
                    return;
                }
                var question = input.value.trim();
                if (!question) {
                    return;
                }
                appendBubble('user', escapeHtml(question));
                input.value = '';
                sendMessage(question);
            });
        },
    };
});

# Changelog

## 1.2.1 (2026-10-24)

* New start state: before the site is activated the block shows a clear card with a single "Start activation" button
  (administrators) or a short note (other teachers).
* Activation page redesigned as a three-step journey (Register, Approval, Ready); the redundant second status bar was removed.
* The plugin now rejects a provisioning response that reports the course agent was created without an AI model
  (`model_configured: false`) instead of failing later with an empty chat.

## 1.2.0 (2026-10-24) – first public release

* Marketplace edition: released under GPL v3 or later; all legacy credential-based code paths
  and the proprietary licence text were removed.
* One-button course setup and synchronisation with a calm, iPhone-like block design; settings and
  technical details are tucked away behind a small gear.
* Delta synchronisation (only new or changed course files); optional full check.
* Per-course agent and knowledge base with local isolation guard against shared bindings.
* Protected learner chat: participants can use the chat without any course-view capability,
  guests are excluded; Markdown rendering and word-by-word reveal of answers; chat history with
  delete per question and clear-all.
* Privacy API metadata extended (site URL, contact e-mail, installation id, chat data).
* All user-facing strings moved to language files (English and German); Moodle Code Checker clean.

## 1.2.0-alpha14.x (internal, 2026-06 – 2026-10)

Development builds that were never published. See the project history for details.

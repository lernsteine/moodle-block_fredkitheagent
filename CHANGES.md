# Changelog

## 1.2.2 (2026-10-24)

* Database tables are now prefixed with the component name (`block_fredkitheagent`, `block_fredkitheagent_map`,
  `block_fredkitheagent_design`). Existing installations are migrated automatically by an upgrade step; no data is lost.
* Capability names (`addinstance`, `myaddinstance`) now have language strings.
* `install.xml` validates against the Moodle XMLDB schema; PHPDoc of all functions completed; language strings sorted.
* Continuous integration (moodle-plugin-ci) on Moodle 4.5, 5.0 and 5.1 with PHP 8.2/8.3 passes: PHP lint, Code Checker,
  PHPDoc, validation, upgrade savepoints and PHPUnit.

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

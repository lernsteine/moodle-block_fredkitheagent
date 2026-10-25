# Changelog

## 1.2.6 (2026-10-25)

* Fewer "Gateway Timeout" errors: a synchronisation now stops uploading after a configurable time budget
  (default 30 seconds, setting "Time budget per synchronisation") and reports its progress. The next click continues;
  documents already confirmed are not sent again. The run itself is no longer cancelled when the browser connection drops.
* Licence server address: the setting is now "Keep the licence server address locked" and is ticked by default, so the
  address field stays hidden until an administrator unticks it.

## 1.2.5 (2026-10-25)

* More robust re-synchronisation: after a failed or empty run the next sync reuses every document the server has
  already acknowledged instead of uploading the whole course again.
* The course structure is read fresh at the start of every sync, so material that was just deleted or shown/hidden is
  not judged from a stale cache.
* If no material is visible to participants, the block now says so in plain words (make material visible and sync
  again) instead of the generic "connection could not be completed" message. Registration and course agent are kept.

## 1.2.4 (2026-10-25)

* Synchronisation after deleting course material: a failed or slow document transfer or deletion is now repeated once
  automatically within the same run, so a second click is no longer needed.
* Deleting a document that no longer exists on the server counts as success.
* If a transfer still fails, the block shows the diagnostic code (for example `DIAG-DOCUMENT-DELETE-200-queued`) so the
  cause can be identified.

## 1.2.3 (2026-10-25)

* Maturity set to stable.
* The block now appears in the "add a block" list of every theme (Classic listed it without a title and hid it);
  the title inside the course stays hidden.
* The licence server address is locked by default; it can only be edited after ticking "Allow editing the licence
  server address", and is ignored otherwise.
* Clearer wording: approval step ("Our team member has been notified ...") and the chat switch
  ("Make the agent visible now").

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

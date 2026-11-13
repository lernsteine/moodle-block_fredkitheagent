# Changelog

## 1.7.6 (2026-11-13)

* Free package: from 80 % of the monthly messages, site administrators and teachers see a notice with a progress bar in the
  block and on the activation page, with a link to book a package. The licence server also e-mails the contact at 80 % and 100 %.

## 1.7.5 (2026-11-13)

* Links to the terms, privacy notice and data processing agreement follow the Moodle language (German or English).

## 1.7.4 (2026-11-13)

* Sites that were activated before the acceptance became part of the start confirm the terms, the privacy
  notice and the data processing agreement once on the activation page.

## 1.7.3 (2026-11-13)

* Texts on the block, the activation page and in the README now describe the free start instead of a required booking.

## 1.7.2 (2026-11-13)

* "Start for free": the administrator accepts the terms, the privacy notice and the data processing agreement
  (Art. 28 GDPR), as with a booking. The licence server stores version and time of the acceptance.

## 1.7.1 (2026-11-13)

* New button "Start for free" on the activation page (see 1.7.0 notes).

## 1.7.0 (2026-11-13)

* New button "Start for free" on the activation page: a registered site starts directly in the free package,
  without booking or payment method (domain check only). Booking a package stays optional.
* Free package: after the 7-day trial, or when a subscription ends, the site continues in the free package
  instead of being paused: one course with a course agent and 200 answered chat messages per month.
  The activation page shows the package and the messages used this month. The chat explains when the monthly
  messages are used up or when a further course is not included. Requires licence server 13.38.
* JavaScript moved to AMD modules (amd/src, built with grunt) and loaded with js_call_amd.
* The styles of the course chat are now part of styles.css; no separate stylesheet is loaded any more.

## 1.6.0 (2026-11-04)

* Agent roles: the licence server can offer several FredKI agents (for example Berater, Sparringspartner,
  Critical Friend, Tester, Motivator). Teachers choose the agent for their course in the block, when setting up the
  course or later. Changing it creates a new course agent from the role's template on the same knowledge base; the
  course material is not transferred again. The chat shows the agent's name and its opening statement.
  Requires licence server 13.19; without roles nothing changes.

## 1.5.5 (2026-11-03)

* Package fully used: in a course without a course agent the block now says so before anything is tried
  ("Package fully used · 5 of 5 courses"), explains how to free a place (remove the course agent of another course)
  and, for site administrators, links to the booking of a larger package. "Check again" sets the course up as soon
  as a place is free. The message after a rejected setup names the same ways.

## 1.5.4 (2026-11-02)

* "Remove course agent" in the course agent details (info button): deletes the course agent and its knowledge base in
  FredKI, hides the chat and takes the block out of the course. The place in the FredKI package becomes free for
  another course; the course material in Moodle is not changed. Requires licence server 13.19.

## 1.5.3 (2026-11-01)

* New colour language in Moodle's teal for course material, so FredKI fits into the course page (block, settings,
  activation page; new default colours for the chat header and the message bubbles).
* "Agent appearance" is easier to find: its own palette button in the block. The gear became an info button that opens
  the course agent details (full check, agent, knowledge base, documents, last sync) directly, without an extra
  "Advanced" section.
* The global welcome text is taken over from the opening statement of the FredKI template agent as long as the
  administrator has not changed it (requires licence server 13.19). On the course appearance page, empty fields show
  the global text that applies instead of "Use global setting".

## 1.5.2 (2026-11-01)

* Reconnecting after a reinstall: if the plugin was removed and installed again, registering the site again restores
  the existing connection automatically (package, trial and course agents are kept). The licence server confirms
  through siteproof.php that the request really comes from this Moodle site. If the licence server no longer accepts
  the stored connection, the activation page now explains this and offers "Reconnect this site".
* Activation texts describe the booking-based activation (no manual approval any more); inactive states are shown in
  plain words.
* The booking and subscription pages open in the language of the Moodle site (English or German).
* Requires licence server 13.19 for the automatic reconnect.

## 1.5.1 (2026-10-31)

* Supports Moodle 4.5 to 5.3; the automated tests now also run on Moodle 5.2 and 5.3.
* Version number in the Moodle format YYYYMMDDXX (2026103100), as required by the Moodle Plugins directory.
  The upgrade steps were renumbered in the same order. Sites that still have a former 12-digit version stored
  are converted once when a site administrator opens the FredKI activation page; then the normal Moodle
  upgrade runs. No data is changed.
* Chat window: the subtitle and the screen-reader label of the typing indicator now come from the language pack
  (no hard-coded German text in the English interface).

## 1.5.0 (2026-10-31)

* Merges 1.4.6–1.4.9 (block design, removal of hidden material) with the Moodle Marketplace preparation.
* Moodle Marketplace: the plugin ships with English only; German and other translations are provided through AMOS
  (lang.moodle.org). README without prices (current packages and prices are shown on the booking page) and with the
  booking-based activation steps.

## 1.4.9 (2026-10-31)

* Main button: the hover and focus state keeps the violet background (white text no longer turns white on white).

## 1.4.8 (2026-10-31)

* The main button is calmer: a soft violet gradient with a light shadow; only the progress animation remains.

## 1.4.7 (2026-10-31)

* Tidier block: the grey notice box is the only status hint; the extra "Not set up yet" line was removed.
* The main button is redesigned (a calmer version followed in 1.4.8).
* In editing mode the block uses the standard Moodle frame and header with the move/settings controls, like every
  other block, instead of a floating card.

## 1.4.6 (2026-10-31)

* Hidden or deleted material now disappears from the knowledge base even when nothing else is visible any more
  (before, an "empty" course snapshot kept all earlier documents). The notice states how many documents were removed.

## 1.4.5 (2026-10-31)

* Settings of the course block redesigned: "Agent appearance" is now a prominent, inviting entry at the top (icon,
  short description, arrow). The technical details (course agent, knowledge base, documents, last synchronisation) and
  the full check are collected behind a collapsed "Advanced" section. The full check button no longer breaks
  the text into single syllables in narrow blocks.

## 1.4.4 (2026-10-30)

* Activation page: while the licence server offers booking, only "Book a package" is shown; the hint about manual
  approval by the FredKI team was removed (licence server 13.18 approves automatically after booking).

## 1.4.3 (2026-10-30)

* Activation page: reloading the page no longer sends the last action (registration, connection test) again; the
  result is shown once after a redirect. Unexpected errors show a translated message instead of an English text.

## 1.4.2 (2026-10-27)

* The licence server subdomain serves the API at its root: `https://fredki-theagent.lernsteine.de/api/v1/index.php`.
  Stored copies of the former default addresses are updated during the upgrade.

## 1.4.1 (2026-10-27)

* The FredKI licence server moved to `https://fredki-theagent.lernsteine.de/fredki-hub/`. Sites with the locked
  default address switch automatically; a stored copy of the former default address is updated during the upgrade.
  An address entered deliberately by the administrator is kept.

## 1.4.0 (2026-10-27)

* Packages and booking (licence server 13.18 or later): the activation page shows "Book a package – test 7 days
  free" while the site waits for approval, and "Manage subscription" with package and course usage once a
  subscription exists. Existing testers can book from the activation page as well. Links are accepted only as
  https links to the configured licence server.
* Course limit of the booked package: if it is reached, synchronising a new course shows a clear message instead of
  an error; connected courses keep working.
* PHPUnit tests for the package fields of the status response.

## 1.3.0 (2026-10-26)

* Staged synchronisation reworked: each stage uses the time budget from the settings, the next stage starts
  automatically with a progress page ("35 of 80 course materials transferred"). Documents acknowledged in an earlier
  stage are not sent again, also during the full check of the first setup. A file that fails is not retried within
  the same run, so the synchronisation always finishes; setting up a new course agent gets its own short stage.
* Courses disconnected by the FredKI administrator (licence server 13.15 or later) are recognised: the block shows
  "Disconnected by the administrator", the chat is hidden. After the administrator releases the course, the next
  synchronisation forgets the previous agent and knowledge base once and sets the course up anew; course design and
  the chat switch are kept.
* A knowledge base or course agent deleted directly in FredKI is replaced cleanly (licence server 13.17 or later):
  Moodle accepts the replacement only if the server verified that the old resource no longer exists.
* New wording after activation, with "AI can be this simple." as a separate line.
* Moodle Marketplace preparation: all JavaScript texts come from the language files, the approval e-mail and the
  "Moodle file is missing" message are translatable, a global style rule for MaxKB chat buttons was removed and
  `version.php` declares the supported Moodle versions (4.5 to 5.1).
* PHPUnit tests for disconnecting, resetting a binding and staged synchronisation.

## 1.2.7 (2026-10-26)

* A synchronisation that reaches its time budget now continues automatically with the next stage (progress page
  with a "Continue now" button as fallback) until all documents are confirmed; it stops if a stage makes no progress.
* New wording on the activation page once the site is approved: add the block to a course and one click turns all
  course materials into the chatbot knowledge.

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

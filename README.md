# FredKI The Agent (block_fredkitheagent)

A Moodle block that gives every course its own AI learning companion. With one click a teacher
creates a dedicated knowledge base and a dedicated chat agent for the course and transfers the
course materials. Learners then ask questions in a protected chat inside the course; the answers
are based on the materials of that course only.

*Deutsche Kurzbeschreibung siehe unten.*

## Features

* **One button for everything** – "Set up course agent" the first time, "Update course material"
  afterwards. Only new or changed files are transferred; a "full check" is available in the
  discreet settings sheet of the block.
* **One agent and one knowledge base per course.** The mapping is bound to
  (installation, course). The plugin refuses to continue if a knowledge base or agent would be
  shared between two Moodle courses and discards chat replies that do not come from the agent of
  the course.
* **Material that is imported:** files in resources and folders, pages, labels, books, lessons,
  glossaries, URLs, section and activity descriptions, and summaries of H5P and SCORM packages.
  Hidden or restricted activities are not transferred.
* **Protected chat for learners** (floating button on the course page). The block itself is only
  shown to teachers; learners just see the chat button when the teacher has enabled it.
  Answers support Markdown (headings, lists, tables, code) and are revealed word by word.
  The chat history is kept only in the learner's browser session and can be deleted per question
  or completely.
* Chat colours, welcome and footer text and a logo can be set site-wide and per course.
* Guests cannot use the chat; access requires course login, a visible block and a teacher-enabled chat.

## Requirements

* Moodle 4.5 to 5.3 (tested on 4.5, 5.0, 5.1, 5.2 and 5.3).
* **A hosted service is required** (see "Costs" below). The plugin does not contain any AI model. It
  talks to the FredKI licence/gateway server operated by LERNSTEINE® (lernsteine.com), which provides
  the knowledge bases, the AI agents and the language models.
* Outgoing HTTPS from the Moodle server to the gateway (default
  `https://fredki-theagent.lernsteine.de/api/v1/index.php`; the address is locked in the plugin
  settings and only needs to be changed if the provider asks you to).
* Moodle's "cURL blocked hosts" security setting must not block the gateway host.

## Costs

* The plugin itself is **free and open source** (GNU GPL v3 or later). There is no licence key and
  no payment inside Moodle.
* The plugin uses the hosted FredKI service by lernsteine.com. FredKI itself is open source
  (GNU GPL v3): <https://github.com/lernsteine/FredKI>.
* A site administrator registers the Moodle site once (Site administration → Plugins → Blocks →
  FredKI The Agent → "Open activation"). Registration is free.
* **Free package – no payment needed for the essential functionality.** Every site can use the
  plugin without paying: one course with a course agent and 200 answered chat messages per month
  (the counter starts again on the 1st of each month). All functions of the plugin are available
  in that course: setting up the course agent, transferring the course material, the protected
  course chat and agent roles.
* After registration a **7-day trial** of a paid package starts. If no package is booked, the site
  automatically continues in the free package. The same happens when a subscription ends.
* **Paid packages** (optional) give more courses with a course agent and no monthly message limit. They
  are booked and paid on the booking page of the hosted service via Stripe (card, PayPal, SEPA
  direct debit). Current packages and prices are shown before anything is ordered; prices are
  maintained by the provider and may change. The package can be changed or cancelled at any time
  via "Manage subscription".
* When the course limit is reached, further courses cannot be connected; connected courses keep
  working. When the monthly messages of the free package are used up, the chat says so and is
  available again next month. Questions: info@lernsteine.com

## Languages

The plugin ships with English. Translations, including German, are provided through Moodle's
translation portal AMOS (lang.moodle.org) and installed with the Moodle language packs.

## Installation

1. Unzip into `blocks/` so that the folder is `blocks/fredkitheagent`, or use
   *Site administration → Plugins → Install plugins*.
2. Run the Moodle upgrade.
3. As administrator open *Site administration → Plugins → Blocks → FredKI The Agent → Open activation*,
   enter a contact e-mail address and register the site.
4. On the activation page choose "Book a package – test 7 days free". After the booking the site
   is approved automatically within a few minutes.
5. Add the block "FredKI The Agent" to a course and press the large button.

### Reinstalling the plugin

Uninstalling the plugin removes its stored connection and the blocks in the courses. After installing it again,
register the site again on the activation page with the contact e-mail address. The licence server recognises the
site (it checks `blocks/fredkitheagent/siteproof.php` on the site's usual address) and restores the existing
connection: package, trial and course agents are kept. Then add the block to the courses again; course material that
is already known is not transferred twice.

## Privacy

The plugin implements the Moodle Privacy API. Data sent to the external service:

| Data | When |
| --- | --- |
| Site URL, administrator contact e-mail | once, at registration |
| Random installation identifier, Moodle course id | with every request |
| Course name | when a course is registered |
| Course material (text, files and file names of visible activities) | on every synchronisation |
| Chat question and up to 20 previous messages of the current conversation | on every chat message |

No Moodle user id, name or e-mail address of learners or teachers is transmitted by the plugin.
The browser of a learner never contacts the external service; all calls are made by the Moodle
server. Course material written by teachers is transferred as it is, so teachers should not
publish personal data of third parties in course material that is meant for the agent.
The administrator remains the controller and must have a data processing agreement with the
provider before activating the plugin.

## Licence

GNU GPL v3 or later, see `LICENSE`. © 2026 lernsteine.com.
The licence server and the AI service are not part of this plugin and are not covered by this licence.
The name "FredKI" and the FredKI logo (`pix/fredki_the_agent.png`) are trademarks / works of lernsteine.com; the GPL
does not grant any right to use them for other products.

## Support

Please report bugs and feature requests in the issue tracker:
https://github.com/lernsteine/moodle-block_fredkitheagent/issues
Questions about the hosted service, trial and subscription: info@lernsteine.com

---

## Kurzbeschreibung (Deutsch)

„FredKI The Agent“ legt per Klick für jeden Kurs eine eigene Wissensbasis und einen eigenen
Kurs-Agenten an, überträgt das Kursmaterial und stellt den Lernenden einen geschützten Chat im Kurs
bereit. Der Block ist nur für Lehrkräfte sichtbar. Für den Betrieb ist ein externer Dienst
(FredKI-Freigabeserver von LERNSTEINE®) nötig; die Moodle-Installation wird einmalig kostenlos
registriert. Ohne Bezahlung steht das kostenlose Paket zur Verfügung: ein Kurs mit Kurs-Agent und
200 beantwortete Nachrichten pro Monat, mit allen Funktionen des Plugins. Nach der Registrierung läuft
7 Tage ein Test eines bezahlten Pakets; wird nichts gebucht oder endet ein Abo, geht es automatisch im
kostenlosen Paket weiter. Bezahlte Pakete (monatliches Hosting-Abo bei LERNSTEINE®) bieten mehr Kurse
und kein Monatslimit für Nachrichten; aktuelle Preise stehen auf der Buchungsseite. Das Plugin selbst
ist kostenlos (GNU GPL v3 oder später). FredKI ist Open Source: <https://github.com/lernsteine/FredKI>.

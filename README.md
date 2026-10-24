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

* Moodle 4.5 or later.
* **An external service is required.** The plugin does not contain any AI model. It talks to the
  FredKI licence/gateway server operated by LERNSTEINE® (lernsteine.com). A site administrator has
  to register the Moodle site once (Site administration → Plugins → Blocks → FredKI The Agent →
  "Open activation"). Registration is reviewed manually and starts a seven-day trial; continued use
  needs a licence from the provider. The plugin is GPL, the hosted service is a separate, commercial
  offer.
* Outgoing HTTPS from the Moodle server to the gateway (default
  `https://bildungbleibt.de/fredki-hub/api/v1/index.php`, changeable in the plugin settings).
* Moodle's "cURL blocked hosts" security setting must not block the gateway host.

## Installation

1. Unzip into `blocks/` so that the folder is `blocks/fredkitheagent`, or use
   *Site administration → Plugins → Install plugins*.
2. Run the Moodle upgrade.
3. As administrator open *Site administration → Plugins → Blocks → FredKI The Agent → Open activation*,
   enter a contact e-mail address and register the site.
4. After the provider has approved the site, add the block "FredKI The Agent" to a course and press
   the large button.

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

Please use the issue tracker of the plugin repository. The provider's contact data are shown on the
plugin's page in the Moodle plugins directory / Marketplace.

---

## Kurzbeschreibung (Deutsch)

„FredKI The Agent“ legt per Klick für jeden Kurs eine eigene Wissensbasis und einen eigenen
Kurs-Agenten an, überträgt das Kursmaterial und stellt den Lernenden einen geschützten Chat im Kurs
bereit. Der Block ist nur für Lehrkräfte sichtbar. Für den Betrieb ist ein externer Dienst
(FredKI-Freigabeserver von LERNSTEINE®) nötig; die Moodle-Installation wird einmalig registriert
und manuell freigeschaltet (7 Tage Test). Das Plugin steht unter GPL v3 oder später.

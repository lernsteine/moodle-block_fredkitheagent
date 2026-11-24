# Editions of FredKI The Agent

There are two installable ZIP files built from the same code:

* **marketplace** – the plugin as listed in Moodle Marketplace.
* **direct** – the plugin as downloaded from fredki.com or GitHub. It additionally shows a card on the activation page
  that leads to the FredKI website, where packages with more courses and messages can be booked. The card is added by
  `direct/direct-edition.patch`.

Both editions use the same licence key field; a key works in either edition.

Build both: `tools/build-editions.sh` (the ZIP files are written to `build/`).

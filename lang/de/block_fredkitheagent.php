<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Language strings (de) for FredKI The Agent.
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['knowledgeid'] = 'Wissensbasis ID';
$string['knowledgebasename'] = 'Wissensbasis Name';
$string['files'] = 'Erzeugte Importdateien';
$string['nofiles'] = 'Noch keine Kursdaten übernommen.';
$string['lastsync'] = 'Letzte Synchronisation';
$string['knowledgebase'] = 'Wissensbasis';

$string['glossaryentries'] = 'Glossarbeiträge';
$string['untitledglossaryentry'] = 'Unbenannter Glossarbeitrag';
$string['sourcelabel'] = 'Textfeld';

$string['ready'] = 'Bereit';
$string['selection'] = 'Auswahl';
$string['agentid'] = 'FredKI Agent';
$string['fredkiembedcode'] = 'FredKI Embed-Code';

$string['agentcoursevisible'] = 'Chatbot auf der Kursseite anzeigen';
$string['fredkichaturl'] = 'FredKI Chat-URL';

$string['pluginname'] = 'FredKI The Agent';
$string['privacy:metadata'] = 'FredKI The Agent speichert Kurszuordnungen zu FredKI-Wissensbasen und Agenten.';
$string['agentcreated'] = 'Agent erstellt';
$string['workspace'] = 'Workspace';
$string['debuglog'] = 'Debug-Log aktivieren';

$string['apierror'] = 'Die FredKI-Anfrage ist fehlgeschlagen. Bitte versuchen Sie es später erneut oder wenden Sie sich an die Administration.';
$string['privacy:metadata:fredkitheagent'] = 'Speichert die kursbezogene FredKI-Konfiguration.';
$string['privacy:metadata:fredkitheagent:course'] = 'ID des Moodle-Kurses.';
$string['privacy:metadata:fredkitheagent:knowledgeid'] = 'ID der externen FredKI-Wissensbasis.';
$string['privacy:metadata:fredkitheagent:agentid'] = 'ID des externen FredKI-Agenten.';
$string['privacy:metadata:fredkitheagent:chaturl'] = 'Öffentliche Chat-URL des Agenten.';
$string['privacy:metadata:fredkitheagent:lasterror'] = 'Bereinigte technische Statusmeldung.';
$string['privacy:metadata:map'] = 'Speichert Zuordnungen zwischen Moodle-Dateien und FredKI-Dokumenten.';
$string['privacy:metadata:map:filename'] = 'Dateiname des übertragenen Kursinhalts.';
$string['privacy:metadata:map:documentid'] = 'Externe FredKI-Dokument-ID.';
$string['privacy:metadata:map:lasterror'] = 'Bereinigte Synchronisationsmeldung.';
$string['privacy:metadata:fredki'] = 'FredKI ist ein externer Dienst (Betreiber: lernsteine.com, Standard-Endpunkt https://bildungbleibt.de/fredki-hub/), der die Kurs-Wissensbasis und den Kurs-Chatagenten bereitstellt. Alle Anfragen werden serverseitig von Moodle gesendet; der Browser der Lernenden verbindet sich nie direkt mit FredKI. Es werden weder Moodle-Benutzer-IDs noch Namen oder E-Mail-Adressen von Lernenden oder Lehrenden übertragen.';
$string['privacy:metadata:fredki:coursecontent'] = 'Ausgewählte Kursinhalte und Metadaten werden an FredKI übertragen.';
$string['privacy:metadata:fredki:siteurl'] = 'Die Adresse (URL) dieser Moodle-Website; wird einmalig bei der Registrierung am Lizenzserver gesendet.';
$string['privacy:metadata:fredki:contactemail'] = 'Die bei der Registrierung eingegebene Kontakt-E-Mail-Adresse der Administration; wird einmalig gesendet und für Lizenz- und Supportnachrichten verwendet.';
$string['privacy:metadata:fredki:installationid'] = 'Eine zufällige, vom Lizenzserver erzeugte Installationskennung, die mit jeder Anfrage gesendet wird, um diese Moodle-Website zu identifizieren.';
$string['privacy:metadata:fredki:filename'] = 'Dateinamen werden an FredKI übertragen.';

// Managed 7-day activation / safe migration.
$string['managedactivation'] = 'FredKI – Installation aktivieren';
$string['managedstatusunavailable'] = 'Lizenzserver derzeit nicht erreichbar. Die Funktion bleibt aus Sicherheitsgründen gesperrt.';
$string['managedtrialend'] = 'Ende der Testzeit';
$string['managedcontactemail'] = 'Kontakt-E-Mail für Freigabe';
$string['managedregister'] = 'Installation registrieren';
$string['managedinactive'] = 'FredKI ist noch nicht freigeschaltet oder die Testzeit ist abgelaufen. Bitte die Installation im Lizenzserver freigeben beziehungsweise eine Lizenz aktivieren.';
$string['alreadyregistered'] = 'Diese Moodle-Installation ist bereits registriert.';
$string['notregistered'] = 'Die Moodle-Installation ist noch nicht registriert.';
$string['managedonly'] = 'Diese Seite ist nur im zentral verwalteten Modus verfügbar.';

$string['manageddiagnosebutton'] = 'Verbindung zum Lizenzserver pruefen (ohne Registrierung)';

// Moodle-local managed chat overlay.
$string['managedchatopen'] = 'Geschützten FredKI-Chat öffnen';
$string['managedchatclose'] = 'Chat schließen';
$string['managedchattitle'] = 'FredKI – Dein Kursassistent';

$string['managedtargetmissing'] = 'Der Kurs ist noch keiner FredKI-Wissensbasis zugeordnet.';
$string['chatheadercolor'] = 'Chat-Kopfbereich';
$string['chataccentcolor'] = 'Akzentfarbe';
$string['chatbackgroundcolor'] = 'Chat-Hintergrund';
$string['chatwelcometext'] = 'Begrüßungstext';
$string['chatwelcomedefault'] = 'Hallo! Ich helfe dir beim Lernen mit den Materialien dieses Kurses.';
$string['chatfootertext'] = 'Fußzeile';
$string['chatfooterdefault'] = 'KI-Antworten können Fehler enthalten.';
$string['chatlogo'] = 'Chat-Logo';
$string['chatbackcourse'] = 'Zurück zum Kurs';
$string['chatask'] = 'Deine Frage eingeben …';
$string['chatsend'] = 'Senden';
$string['chaterror'] = 'FredKI ist zurzeit nicht verfügbar. Bitte später erneut versuchen.';

$string['managedregistered'] = 'Registrierung erfolgreich. Deine Moodle-Installation wartet auf die Freigabe durch LERNSTEINE®.';
$string['managedregisterintro'] = 'FredKI kostenlos testen: Gib einmalig deine Kontakt-E-Mail-Adresse ein. LERNSTEINE® prüft deine Moodle-Domain und startet die siebentägige Testphase.';
$string['managedregisterbutton'] = 'FredKI kostenlos testen';
$string['managednotificationfailed'] = 'Die E-Mail-Benachrichtigung konnte vom Hostingserver nicht versendet werden.';
$string['managedmailfallback'] = 'Freigabeanfrage per E-Mail senden';
$string['managedapproved'] = 'FredKI ist freigeschaltet. Du kannst die Kursmaterialien mit dem Synchronisieren-Knopf aktualisieren.';
$string['managednotactive'] = 'FredKI ist momentan nicht freigeschaltet';
$string['managedadvanced'] = 'Erweiterte Verbindungseinstellungen (nur Administration)';
$string['managedresetdone'] = 'Die lokale Verbindung zum bisherigen Server wurde gelöst. Kursmaterialien wurden nicht gelöscht.';
$string['managedresetconfirm'] = 'Ich möchte die bisherige Serververbindung bewusst lösen.';
$string['managedresetbutton'] = 'Serververbindung wechseln';

$string['nextready'] = 'Bereit';
$string['nextsubtitle'] = 'Dein KI-Lernbegleiter im Kurs';
$string['nextcreate'] = 'FredKI für diesen Kurs aktivieren';
$string['nextcreatedesc'] = 'Mit einem Klick wird der Kurs verbunden. Der geschützte Chat erscheint nach bestätigter Synchronisierung der Kursmaterialien.';
$string['nextsyncfailed'] = 'Die letzte Verbindung konnte nicht abgeschlossen werden. Bitte erneut synchronisieren oder die Serverdiagnose prüfen.';

$string['privacy:metadata:fredki:chatprompt'] = 'Bei einer Chatnachricht wird die eingegebene Frage an den externen FredKI-Dienst übertragen.';
$string['privacy:metadata:fredki:chathistory'] = 'Bis zu 20 vorherige Nachrichten des aktuellen Chatverlaufs werden an FredKI übertragen, um kontextbezogene Antworten zu ermöglichen. Der Verlauf wird nur in der Browser- und PHP-Sitzung der Lernenden gehalten und nicht in der Moodle-Datenbank gespeichert.';
$string['privacy:metadata:fredki:chatresponse'] = 'Von FredKI erzeugte Antworten werden im Chat angezeigt. Sie werden nicht in der Moodle-Datenbank gespeichert.';
$string['privacy:metadata:fredki:courseid'] = 'Die Moodle-Kurs-ID und der Kursname werden übertragen, um den eigenen Kursagenten auszuwählen und zu benennen.';

$string['managedchatmenu'] = 'Verlauf anzeigen';
$string['managedchatexpand'] = 'Chatfenster vergrößern';
$string['managedchathistory'] = 'Verlauf';
$string['managedchathistoryhint'] = 'Deine letzten Fragen im Kurs. Ein Klick übergibt sie wieder an den Chat.';
$string['managedchathistorydelete'] = 'Eintrag löschen';
$string['managedchathistoryclear'] = 'Gesamten Verlauf löschen';
$string['managedchathistoryempty'] = 'Noch keine Fragen im Verlauf.';
$string['iosready'] = 'Bereit · {$a} Dokumente verbunden';
$string['iosupdate'] = 'Kursmaterial aktualisieren';
$string['iosbusy'] = 'Wird abgeglichen …';
$string['ioslastsync'] = 'Zuletzt abgeglichen · {$a}';
$string['ioschat'] = 'Chat im Kurs';
$string['iossettings'] = 'Einstellungen';
$string['iosdone'] = 'Fertig';
$string['iosintro'] = 'Ein Klick richtet alles für diesen Kurs ein: Wissensbasis, Ordner und Ihren eigenen Kursagenten. Danach wird das Kursmaterial übertragen.';
$string['iossetup'] = 'Kursagent einrichten';
$string['ioscontinue'] = 'Einrichtung abschließen';
$string['iossetupnote'] = 'Dauert je nach Materialmenge einige Minuten';
$string['iosnotsetup'] = 'Noch nicht eingerichtet';
$string['iosdesign'] = 'Aussehen des Agenten';
$string['iosfullcheck'] = 'Vollständiger Abgleich';
$string['iosfullcheckdesc'] = 'Prüft alle Dokumente und sendet fehlende erneut';
$string['iosfullcheckrun'] = 'Starten';
$string['iosagent'] = 'Kursagent';
$string['iosknowledge'] = 'Wissensbasis';
$string['iosdocs'] = 'Dokumente';
$string['ioslast'] = 'Letzter Abgleich';
$string['iosbindingnote'] = 'Zur Kontrolle: Jeder Kurs hat seinen eigenen Agenten und seine eigene Wissensbasis.';

$string['coursedesign'] = 'Kursagent gestalten';
$string['coursedesignintro'] = 'Individuelle Farben, Begrüßung und optional ein eigenes Logo für den Agenten dieses Kurses. Leere Textfelder verwenden die globalen Einstellungen. Die Wissensbasis und die Agentenverknüpfung bleiben unverändert.';
$string['designheadercolor'] = 'Kopfbereich';
$string['designaccentcolor'] = 'Akzentfarbe';
$string['designbackgroundcolor'] = 'Hintergrundfarbe';
$string['designbotbubble'] = 'FredKI-Sprechblasen';
$string['designuserbubble'] = 'Antworten der Lernenden';
$string['designuseglobal'] = 'Globale Einstellung verwenden';
$string['designcourselogo'] = 'Kursagent-Logo (optional)';
$string['designlogohint'] = 'PNG, JPG oder WebP, maximal 2 MB. Ohne eigenes Logo gilt das globale Chatlogo.';
$string['designremovelogo'] = 'Kurslogo entfernen';
$string['designresetbutton'] = 'Kursgestaltung zurücksetzen';
$string['designreset'] = 'Kursgestaltung auf die globalen Einstellungen zurückgesetzt.';
$string['designsaved'] = 'Kursgestaltung gespeichert.';

$string['privacy:metadata:design'] = 'Kursbezogene Anzeigeeinstellungen für den FredKI-Agenten.';
$string['privacy:metadata:design:course'] = 'Moodle-Kurs-ID.';
$string['privacy:metadata:design:agent'] = 'FredKI-Agentenkennung.';
$string['privacy:metadata:design:text'] = 'Optionale benutzerdefinierte Texte für Begrüßung und Fußzeile.';

// Marketplace preparation: settings, synchronisation messages.
$string['settingapiurl'] = 'FredKI Freigabeserver – API';
$string['settingapiurl_desc'] = 'Vorkonfigurierte HTTPS-Adresse des FredKI-Freigabeservers. Kursdaten werden an diesen Dienst übertragen; siehe die Datenschutzangaben dieses Plugins.';
$string['settingactivationheading'] = 'Freigabe der Moodle-Installation';
$string['settingactivationlink'] = 'Freigabe öffnen / E-Mail schreiben';
$string['settingappearanceheading'] = 'Geschütztes Chatfenster gestalten';
$string['settingappearanceheading_desc'] = 'Farben und Logo gelten für die Chatoberfläche und die Schaltfläche unten rechts.';
$string['settingheadercolor'] = 'Kopfbereich';
$string['settingaccentcolor'] = 'Akzentfarbe';
$string['settingbgcolor'] = 'Hintergrundfarbe';
$string['settingwelcome'] = 'Begrüßung';
$string['settingwelcomedefault'] = 'Ich helfe dir, passende Informationen zu finden.';
$string['settingfooter'] = 'Fußzeile';
$string['settingfooterdefault'] = 'KI-generierte Antworten können Fehler enthalten.';
$string['settinglogo'] = 'Chatlogo';
$string['settinglogo_desc'] = 'Das Logo erscheint rechts unten im Kurs erst nach erfolgreicher Synchronisierung.';
$string['syncstageregister'] = 'Kursregistrierung';
$string['syncstageknowledge'] = 'Wissensbasis bereitstellen';
$string['syncstagedocuments'] = 'Kursmaterialien abgleichen';
$string['syncstageagent'] = 'Kursagent zuordnen';
$string['syncnotice'] = '{$a->found} Kursmaterialien geprüft, {$a->synced} neu übertragen, {$a->unchanged} vom Server unverändert bestätigt, {$a->cached} bereits bestätigte unveränderte Dokumente übernommen, {$a->deleted} entfernt, {$a->failed} fehlgeschlagen. Dauer: {$a->seconds} Sek.';
$string['syncnoticefull'] = 'Vollständiger Abgleich aktiv: auch fehlende Dokumente in der FredKI-Wissensbasis werden erneut übertragen.';
$string['syncnoticeincomplete'] = 'Die Kursagenten-Zuordnung ist erhalten; Chatfreigabe erst nach erfolgreichem Abgleich.';
$string['syncfailedstage'] = 'FredKI-Abgleich nicht abgeschlossen ({$a->stage}). {$a->code}';
$string['syncerrorstage'] = 'Fehler bei {$a->stage}: {$a->code}';
$string['syncmaterialpending'] = 'Materialabgleich noch nicht abgeschlossen.';
$string['syncrecorddisappeared'] = 'Der Kursdatensatz ist während des Abgleichs verschwunden.';
$string['synclockbusy'] = 'Für diesen Kurs läuft bereits ein Abgleich.';
$string['syncdeletepending'] = 'Löschung beim FredKI-Dienst noch ausstehend.';
$string['syncuploadfailed'] = 'Übertragung fehlgeschlagen ({$a}); erneuter Versuch möglich.';
$string['syncwarningunconfirmed'] = 'Materialien vorhanden, aber keine Bestätigung des zentralen FredKI-Dienstes. Bitte die lokalen Dateizuordnungen und den Dokumenten-Endpunkt prüfen.';
$string['syncwarningnone'] = 'Keine importierbaren Kursmaterialien gefunden. Moodle-Aktivitäten: {$a->scanned} geprüft, {$a->eligible} verfügbar, {$a->restricted} gesperrt oder verborgen, {$a->resource} Datei-/Ordneraktivitäten, {$a->nofiles} davon ohne Dateien. Es wurden wegen dieses Leerlaufs keine bestehenden FredKI-Dokumente gelöscht.';
$string['syncfileserrors'] = '{$a->count} Kursdatei(en) noch nicht synchronisiert (erste Diagnose: {$a->first})';
$string['syncunavailable'] = 'nicht verfügbar';
$string['syncnotcomplete'] = 'Materialübertragung nicht abgeschlossen; Chat bleibt gesperrt.';
$string['chatneedsjavascript'] = 'Für den FredKI-Chat muss JavaScript aktiviert sein.';
$string['iosinactivestatus'] = 'Noch nicht freigeschaltet';
$string['iosinactiveintro'] = 'Diese Moodle-Seite muss einmalig registriert und von LERNSTEINE® freigeschaltet werden. Danach richtest du den Agenten in jedem Kurs mit einem Klick ein.';
$string['iosinactiveopen'] = 'Freischaltung starten';
$string['iosinactivetrial'] = '7 Tage kostenlos testen';
$string['iosinactivenoadmin'] = 'Bitte die Administration dieser Moodle-Seite bitten, FredKI freizuschalten.';
$string['actsubtitle'] = 'Einmalige Einrichtung für diese Moodle-Seite';
$string['actstepregister'] = 'Registrieren';
$string['actstepapproval'] = 'Freigabe';
$string['actstepready'] = 'Fertig';
$string['actpendingcaption'] = 'Wir prüfen deine Registrierung. Du musst dich nicht erneut registrieren.';
$string['fredkitheagent:addinstance'] = 'Neuen FredKI-The-Agent-Block hinzufügen';
$string['fredkitheagent:myaddinstance'] = 'Neuen FredKI-The-Agent-Block zum Dashboard hinzufügen';

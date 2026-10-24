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
 * Builds importable documents from Moodle course content.
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_fredkitheagent\local;

/**
 * Course content importer class.
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_content_importer {
    /** @var array<string,array{filepath:string,filename:string,content:string}> */
    protected $textfiles = [];
    /** @var array<string, bool> Pathnames of source files in this snapshot. */
    protected $capturedpaths = [];

    /**
     * Import course content.
     *
     * @param \stdClass $course The course record.
     * @param \stdClass $activity The FredKI activity record.
     * @param ?array $selectedcmids The selected course module ids.
     * @param bool $includesectiontexts Whether to include section texts.
     * @return array The result.
     */
    public function import_course_content(
        \stdClass $course,
        \stdClass $activity,
        ?array $selectedcmids = null,
        bool $includesectiontexts = true
    ): array {
        global $DB;

        $context = \context_course::instance($course->id);
        $fs = get_file_storage();
        // Do not delete the previous snapshot merely because a Moodle visibility
        // restriction leaves nothing to import on the current run. In particular,
        // an empty scan must not trigger deletion of existing remote documents.
        $this->textfiles = [];
        $this->capturedpaths = [];

        $modinfo = get_fast_modinfo($course);
        $counters = [
            'scannedactivities' => 0,
            'eligibleactivities' => 0,
            'skippedactivities' => 0,
            'restrictedactivities' => 0,
            'unsupportedactivities' => 0,
            'resourceactivities' => 0,
            'resourceswithoutfiles' => 0,
            'files' => 0,
            'sectiontexts' => 0,
            'activitytexts' => 0,
            'urls' => 0,
            'glossaries' => 0,
            'pages' => 0,
            'labels' => 0,
            'books' => 0,
            'lessons' => 0,
            'h5p' => 0,
            'scorm' => 0,
        ];

        $selectedmap = null;
        if ($selectedcmids !== null) {
            $selectedmap = array_flip(array_map('intval', $selectedcmids));
        }

        foreach ($modinfo->cms as $sourcecm) {
            $counters['scannedactivities']++;
            if ($sourcecm->deletioninprogress || !$this->is_available_for_participants($sourcecm)) {
                $counters['skippedactivities']++;
                $counters['restrictedactivities']++;
                continue;
            }
            if ($selectedmap !== null && !isset($selectedmap[(int)$sourcecm->id])) {
                $counters['skippedactivities']++;
                continue;
            }
            $counters['eligibleactivities']++;

            if (in_array($sourcecm->modname, ['resource', 'folder'], true)) {
                $counters['resourceactivities']++;
                $added = $this->copy_module_files($context->id, $sourcecm);
                $counters['files'] += $added;
                if ($added === 0) {
                    $counters['resourceswithoutfiles']++;
                }
            } else if (
                !in_array($sourcecm->modname, [
                    'url', 'glossary', 'page', 'label', 'book', 'lesson', 'h5pactivity', 'scorm',
                ], true)
            ) {
                $counters['unsupportedactivities']++;
            }

            $introrecord = $this->get_instance_record($sourcecm);
            if (
                $introrecord && !empty(trim(strip_tags((string)($introrecord->intro ?? '')))) &&
                in_array($sourcecm->modname, ['resource', 'folder'], true)
            ) {
                $this->add_text_file(
                    '/activity-texts/',
                    $this->build_safe_filename($sourcecm->modname . '-cm' . $sourcecm->id . '-beschreibung.html'),
                    $this->render_activity_intro_html($course, $sourcecm, $introrecord)
                );
                $counters['activitytexts']++;
            }

            if ($sourcecm->modname === 'url' && $introrecord) {
                $this->add_text_file(
                    '/urls/',
                    $this->build_safe_filename('url-cm' . $sourcecm->id . '.html'),
                    $this->render_url_html($course, $sourcecm, $introrecord)
                );
                $counters['urls']++;
            }

            if ($sourcecm->modname === 'glossary') {
                $counters['glossaries'] += $this->add_glossary_export($course, $sourcecm, $introrecord);
            }

            if ($sourcecm->modname === 'page' && $introrecord) {
                $this->add_text_file(
                    '/pages/',
                    $this->build_safe_filename('seite-cm' . $sourcecm->id . '-' . ($sourcecm->name ?: 'seite') . '.html'),
                    $this->render_page_html($course, $sourcecm, $introrecord)
                );
                $counters['pages']++;
            }

            if ($sourcecm->modname === 'label') {
                $counters['labels'] += $this->add_label_export($course, $sourcecm);
            }

            if ($sourcecm->modname === 'book' && $introrecord) {
                $counters['books'] += $this->add_book_export($course, $sourcecm, $introrecord);
            }

            if ($sourcecm->modname === 'lesson' && $introrecord) {
                $counters['lessons'] += $this->add_lesson_export($course, $sourcecm, $introrecord);
            }

            if ($sourcecm->modname === 'h5pactivity' && $introrecord) {
                $this->add_text_file(
                    '/guides/',
                    $this->build_safe_filename('h5p-cm' . $sourcecm->id . '-' . ($sourcecm->name ?: 'h5p') . '.html'),
                    $this->render_guide_html(
                        $course,
                        $sourcecm,
                        $introrecord,
                        'H5P',
                        'Diese interaktive Übung ist direkt in Moodle verfügbar. Bitte Nutzer über den Aktivitätslink führen.'
                    )
                );
                $counters['h5p']++;
            }

            if ($sourcecm->modname === 'scorm' && $introrecord) {
                $this->add_text_file(
                    '/guides/',
                    $this->build_safe_filename('scorm-cm' . $sourcecm->id . '-' . ($sourcecm->name ?: 'scorm') . '.html'),
                    $this->render_guide_html(
                        $course,
                        $sourcecm,
                        $introrecord,
                        'SCORM',
                        'Dieses Lernpaket ist direkt in Moodle verfügbar. Bitte Nutzer über den Aktivitätslink führen.'
                    )
                );
                $counters['scorm']++;
            }
        }

        $sections = $includesectiontexts ? $DB->get_records('course_sections', ['course' => $course->id], 'section ASC') : [];
        foreach ($sections as $section) {
            $plain = trim(strip_tags((string)$section->summary));
            if ($plain === '') {
                continue;
            }
            $sectionname = get_section_name($course, $section);
            $this->add_text_file(
                '/section-texts/',
                $this->build_safe_filename('abschnitt-' . $section->section . '.html'),
                $this->render_section_html($course, $sectionname, $section)
            );
            $counters['sectiontexts']++;
        }

        foreach ($this->textfiles as $filedef) {
            $record = [
                'contextid' => $context->id,
                'component' => 'block_fredkitheagent',
                'filearea' => 'generated_files',
                'itemid' => 0,
                'filepath' => $filedef['filepath'],
                'filename' => $filedef['filename'],
            ];
            $prior = $fs->get_file(
                $context->id,
                'block_fredkitheagent',
                'generated_files',
                0,
                $filedef['filepath'],
                $filedef['filename']
            );
            if ($prior) {
                // Avoid changing remote IDs when a generated HTML snapshot did
                // not change. Moodle's pathname hashes remain stable.
                if ($prior->get_contenthash() === sha1($filedef['content'])) {
                    continue;
                }
                $prior->delete();
            }
            $fs->create_file_from_string($record, $filedef['content']);
        }

        $generated = $counters['files'] + count($this->textfiles);
        if ($generated === 0) {
            // Do not mark previously synchronised documents as removed when
            // Moodle returned an empty course snapshot due to restrictions.
            return array_merge($counters, [
                'created' => 0, 'updated' => 0, 'skipped' => 0, 'deleted' => 0,
                'emptycapture' => true,
            ]);
        }
        // A successful nonempty snapshot must not retain stale generated
        // documents from removed activities. Retire copies that were not part
        // of this run without touching the remote API yet.
        $targetpaths = $this->capturedpaths;
        foreach ($this->textfiles as $filedef) {
            $targetpaths[$filedef['filepath'] . $filedef['filename']] = true;
        }
        // For resource files, source links are represented by their source
        // module + virtual path; only files currently accessible are retained.
        $files = $fs->get_area_files(
            $context->id,
            'block_fredkitheagent',
            'generated_files',
            0,
            'filepath, filename',
            false
        );
        foreach ($files as $generatedfile) {
            if ($generatedfile->is_directory()) {
                continue;
            }
            $key = $generatedfile->get_filepath() . $generatedfile->get_filename();
            if (!isset($targetpaths[$key])) {
                $generatedfile->delete();
            }
        }
        $syncstats = file_sync_helper::scan_generated_files((int)$course->id, (int)$activity->id);
        return array_merge($counters, $syncstats, ['emptycapture' => false]);
    }

    /**
     * Only transfer material that participants may use: visible activities and
     * activities hidden on the course page but available by link. Completely
     * hidden/unavailable trainer-only material is skipped.
     */
    protected function is_available_for_participants(\cm_info $sourcecm): bool {
        // Moodle 5.2: visible=0 denotes a hidden module, even if a trainer
        // can view it. A stealth module instead has visible=1 and
        // visibleoncoursepage=0, and must remain eligible.
        // 'available' checks conditional availability for the current user.
        if (empty($sourcecm->visible) || empty($sourcecm->available)) {
            return false;
        }
        return true;
    }

    /**
     * Clear generated area.
     *
     * @param int $contextid The contextid.
     */
    protected function clear_generated_area(int $contextid): void {
        $fs = get_file_storage();
        $fs->delete_area_files($contextid, 'block_fredkitheagent', 'generated_files', 0);
    }

    /**
     * Copy module files.
     *
     * @param int $targetcontextid The targetcontextid.
     * @param \cm_info $sourcecm The sourcecm.
     * @return int The result.
     */
    protected function copy_module_files(int $targetcontextid, \cm_info $sourcecm): int {
        $sourcecontext = \context_module::instance($sourcecm->id);
        $component = 'mod_' . $sourcecm->modname;
        $fs = get_file_storage();
        $files = $fs->get_area_files($sourcecontext->id, $component, 'content', 0, 'filepath, filename', false);
        $count = 0;

        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }
            $filepath = '/course-files/cm' . $sourcecm->id . $file->get_filepath();
            $this->capturedpaths[$filepath . $file->get_filename()] = true;
            $filerecord = [
                'contextid' => $targetcontextid,
                'component' => 'block_fredkitheagent',
                'filearea' => 'generated_files',
                'itemid' => 0,
                'filepath' => $filepath,
                'filename' => $file->get_filename(),
            ];
            // Preserve previous snapshot until we have identified at least one
            // eligible file. Replacing a file in-place requires removing the
            // prior generated copy with the identical virtual pathname.
            $prior = $fs->get_file(
                $targetcontextid,
                'block_fredkitheagent',
                'generated_files',
                0,
                $filepath,
                $file->get_filename()
            );
            if ($prior) {
                // Equal content is already present; keep the stable mapping.
                if ($prior->get_contenthash() === $file->get_contenthash()) {
                    $count++;
                    continue;
                }
                $prior->delete();
            }
            $fs->create_file_from_storedfile($filerecord, $file);
            $count++;
        }

        return $count;
    }

    /**
     * Add glossary export.
     *
     * @param \stdClass $course The course record.
     * @param \cm_info $sourcecm The sourcecm.
     * @param ?\stdClass $glossary The glossary.
     * @return int The result.
     */
    protected function add_glossary_export(\stdClass $course, \cm_info $sourcecm, ?\stdClass $glossary): int {
        global $DB;

        if (!$glossary) {
            return 0;
        }

        $entries = $DB->get_records('glossary_entries', ['glossaryid' => $glossary->id, 'approved' => 1], 'concept ASC');
        if (!$entries) {
            return 0;
        }

        $context = \context_module::instance($sourcecm->id);
        $baseprefix = $this->build_safe_filename('glossar-cm' . $sourcecm->id . '-' . ($sourcecm->name ?: 'glossar'));
        $overviewparts = [];

        foreach ($entries as $entry) {
            $concept = trim((string)$entry->concept);
            $conceptlabel = $concept !== '' ? $concept : get_string('untitledglossaryentry', 'block_fredkitheagent');

            $entryhtml = $this->render_compact_document($course, $sourcecm, [
                'type' => 'Wissen',
                'title' => $conceptlabel,
                'description' => format_text($entry->definition, $entry->definitionformat, ['context' => $context]),
                'link' => (new \moodle_url('/mod/glossary/showentry.php', ['eid' => $entry->id]))->out(false),
                'category' => format_string($glossary->name),
            ]);

            $entryfilename = $this->build_safe_filename($baseprefix . '-' . $conceptlabel . '.html');
            $this->add_text_file('/glossaries/', $entryfilename, $entryhtml);
            $overviewparts[] = '<li>' . s($conceptlabel) . '</li>';
        }

        $overviewhtml = '<html><head><meta charset="utf-8"></head><body>' .
            '<h1>' . format_string($glossary->name) . '</h1>' .
            '<p><strong>' . get_string('glossaryentries', 'block_fredkitheagent') . ':</strong> ' . count($entries) . '</p>' .
            '<p>Die folgenden Begriffe gehören direkt zu diesem Glossar im Moodle-Kurs.</p>' .
            '<ul>' . implode("
", $overviewparts) . '</ul>' .
            '</body></html>';

        $this->add_text_file('/glossaries/', $this->build_safe_filename($baseprefix . '-uebersicht.html'), $overviewhtml);

        return count($entries);
    }

    /**
     * Add label export.
     *
     * @param \stdClass $course The course record.
     * @param \cm_info $sourcecm The sourcecm.
     * @return int The result.
     */
    protected function add_label_export(\stdClass $course, \cm_info $sourcecm): int {
        $content = trim((string)($sourcecm->content ?? ''));
        if ($content === '' || trim(strip_tags($content)) === '') {
            return 0;
        }

        $html = $this->render_compact_document($course, $sourcecm, [
            'type' => get_string('sourcelabel', 'block_fredkitheagent'),
            'title' => format_string($sourcecm->name ?: get_string('sourcelabel', 'block_fredkitheagent')),
            'content' => format_text($content, FORMAT_HTML, ['context' => \context_module::instance($sourcecm->id)]),
            'hint' => 'Dieser Text und der Aktivitätslink gehören direkt zusammen.',
        ]);

        $this->add_text_file('/labels/', $this->build_safe_filename('label-cm' . $sourcecm->id . '.html'), $html);
        return 1;
    }

    /**
     * Add book export.
     *
     * @param \stdClass $course The course record.
     * @param \cm_info $sourcecm The sourcecm.
     * @param \stdClass $book The book.
     * @return int The result.
     */
    protected function add_book_export(\stdClass $course, \cm_info $sourcecm, \stdClass $book): int {
        global $DB;

        if (!$DB->get_manager()->table_exists('book_chapters')) {
            return 0;
        }

        $chapters = $DB->get_records('book_chapters', ['bookid' => $book->id], 'pagenum ASC');
        if (!$chapters) {
            return 0;
        }

        $count = 0;
        foreach ($chapters as $chapter) {
            $title = trim((string)$chapter->title) ?: ('Kapitel ' . $chapter->id);
            $chapterurl = new \moodle_url('/mod/book/view.php', [
                'id' => $sourcecm->id,
                'chapterid' => (int)$chapter->id,
            ]);
            $html = $this->render_compact_document($course, $sourcecm, [
                'type' => 'Buch',
                'title' => $title,
                'description' => '<p><strong>Buch:</strong> ' . format_string($book->name) . '</p>' .
                    '<p><strong>Kapitel:</strong> ' . s($title) . '</p>',
                'content' => format_text(
                    $chapter->content,
                    $chapter->contentformat ?? FORMAT_HTML,
                    ['context' => \context_module::instance($sourcecm->id)]
                ),
                'hint' => 'Dieses Kapitel gehört direkt zu dem Buch „' . format_string($book->name) . '“ im Moodle-Kurs.',
                'link' => $chapterurl->out(false),
                'category' => format_string($book->name),
            ]);
            $filename = $this->build_safe_filename('buch-cm' . $sourcecm->id . '-kapitel-' . $chapter->id . '-' . $title . '.html');
            $this->add_text_file('/books/', $filename, $html);
            $count++;
        }

        return $count;
    }

    /**
     * Add lesson export.
     *
     * @param \stdClass $course The course record.
     * @param \cm_info $sourcecm The sourcecm.
     * @param \stdClass $lesson The lesson.
     * @return int The result.
     */
    protected function add_lesson_export(\stdClass $course, \cm_info $sourcecm, \stdClass $lesson): int {
        global $DB;

        $count = 0;
        $introhtml = $this->render_compact_document($course, $sourcecm, [
            'type' => 'Lektion',
            'title' => format_string($sourcecm->name),
            'description' => format_text(
                $lesson->intro ?? '',
                $lesson->introformat ?? FORMAT_HTML,
                ['context' => \context_module::instance($sourcecm->id)]
            ),
            'hint' => 'Diese Beschreibung gehört direkt zu dieser Lektion im Moodle-Kurs.',
        ]);
        $this->add_text_file(
            '/lessons/',
            $this->build_safe_filename('lektion-cm' . $sourcecm->id . '-beschreibung.html'),
            $introhtml
        );
        $count++;

        if ($DB->get_manager()->table_exists('lesson_pages')) {
            $pages = $DB->get_records('lesson_pages', ['lessonid' => $lesson->id], 'prevpageid ASC, id ASC');
            foreach ($pages as $page) {
                $plain = trim(strip_tags((string)($page->contents ?? '')));
                if ($plain === '') {
                    continue;
                }
                $title = trim((string)($page->title ?? '')) ?: ('Seite ' . $page->id);
                $html = $this->render_compact_document($course, $sourcecm, [
                    'type' => 'Lektion',
                    'title' => $title,
                    'description' => '<p><strong>Lektion:</strong> ' . format_string($lesson->name) . '</p>',
                    'content' => format_text($page->contents, FORMAT_HTML, ['context' => \context_module::instance($sourcecm->id)]),
                    'hint' => 'Diese Seite gehört direkt zu der Lektion „' . format_string($lesson->name) . '“ im Moodle-Kurs.',
                ]);
                $filename = $this->build_safe_filename(
                    'lektion-cm' . $sourcecm->id . '-seite-' . $page->id . '-' . $title . '.html'
                );
                $this->add_text_file('/lessons/', $filename, $html);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get instance record.
     *
     * @param \cm_info $sourcecm The sourcecm.
     * @return ?\stdClass The result.
     */
    protected function get_instance_record(\cm_info $sourcecm): ?\stdClass {
        global $DB;

        $supported = ['resource', 'folder', 'url', 'page', 'book', 'glossary', 'lesson', 'h5pactivity', 'scorm'];
        if (!in_array($sourcecm->modname, $supported, true)) {
            return null;
        }

        return $DB->get_record($sourcecm->modname, ['id' => $sourcecm->instance], '*', IGNORE_MISSING) ?: null;
    }

    /**
     * Add text file.
     *
     * @param string $filepath The filepath.
     * @param string $filename The file name.
     * @param string $content The content.
     */
    protected function add_text_file(string $filepath, string $filename, string $content): void {
        $key = $filepath . $filename;
        $this->textfiles[$key] = [
            'filepath' => $filepath,
            'filename' => $filename,
            'content' => $content,
        ];
    }

    /**
     * Render activity intro html.
     *
     * @param \stdClass $course The course record.
     * @param \cm_info $sourcecm The sourcecm.
     * @param \stdClass $record The record.
     * @return string The result.
     */
    protected function render_activity_intro_html(\stdClass $course, \cm_info $sourcecm, \stdClass $record): string {
        $context = \context_module::instance($sourcecm->id);
        $intro = format_text($record->intro ?? '', $record->introformat ?? FORMAT_HTML, ['context' => $context]);

        return $this->render_compact_document($course, $sourcecm, [
            'type' => 'Aktivitätsbeschreibung',
            'title' => format_string($sourcecm->name),
            'description' => $intro,
            'hint' => 'Diese Beschreibung gehört direkt zu dieser Moodle-Aktivität. ' .
                'Bei passenden Fragen bitte diesen Kontext berücksichtigen.',
        ]);
    }

    /**
     * Render url html.
     *
     * @param \stdClass $course The course record.
     * @param \cm_info $sourcecm The sourcecm.
     * @param \stdClass $record The record.
     * @return string The result.
     */
    protected function render_url_html(\stdClass $course, \cm_info $sourcecm, \stdClass $record): string {
        $context = \context_module::instance($sourcecm->id);
        $intro = format_text($record->intro ?? '', $record->introformat ?? FORMAT_HTML, ['context' => $context]);
        $url = trim((string)($record->externalurl ?? ''));

        return $this->render_compact_document($course, $sourcecm, [
            'type' => 'Link',
            'title' => format_string($sourcecm->name),
            'description' => $intro,
            'hint' => 'Wenn nach weiterführenden Informationen zu diesem Thema gefragt wird, ' .
                'gehört dieser Link direkt zu der Aktivität „' . format_string($sourcecm->name) . '“.',
            'externallinks' => $url !== '' ? [$url] : [],
        ]);
    }

    /**
     * Render page html.
     *
     * @param \stdClass $course The course record.
     * @param \cm_info $sourcecm The sourcecm.
     * @param \stdClass $record The record.
     * @return string The result.
     */
    protected function render_page_html(\stdClass $course, \cm_info $sourcecm, \stdClass $record): string {
        $context = \context_module::instance($sourcecm->id);
        $intro = format_text($record->intro ?? '', $record->introformat ?? FORMAT_HTML, ['context' => $context]);
        $content = format_text($record->content ?? '', $record->contentformat ?? FORMAT_HTML, ['context' => $context]);

        return $this->render_compact_document($course, $sourcecm, [
            'type' => 'Seite',
            'title' => format_string($sourcecm->name),
            'description' => $intro,
            'content' => $content,
            'hint' => 'Beschreibung, Inhalt und Aktivitätslink gehören direkt zusammen und beschreiben dieselbe Moodle-Seite.',
        ]);
    }

    /**
     * Render section html.
     *
     * @param \stdClass $course The course record.
     * @param string $sectionname The sectionname.
     * @param \stdClass $section The section.
     * @return string The result.
     */
    protected function render_section_html(
        \stdClass $course,
        string $sectionname,
        \stdClass $section
    ): string {
        $link = new \moodle_url('/course/view.php', ['id' => $course->id], 'section-' . $section->section);
        return $this->render_entry_document($course, null, [
            'title' => $sectionname,
            'type' => 'Wissen',
            'category' => $sectionname,
            'description' => format_text($section->summary, $section->summaryformat ?? FORMAT_HTML),
            'benefit' => 'Hilft dabei, diesen Kursabschnitt einzuordnen und passende Inhalte im Kurs schneller zu finden.',
            'course' => format_string($course->fullname),
            'link' => $link->out(false),
        ]);
    }

    /**
     * Render guide html.
     *
     * @param \stdClass $course The course record.
     * @param \cm_info $sourcecm The sourcecm.
     * @param \stdClass $record The record.
     * @param string $typename The typename.
     * @param string $hint The hint.
     * @return string The result.
     */
    protected function render_guide_html(
        \stdClass $course,
        \cm_info $sourcecm,
        \stdClass $record,
        string $typename,
        string $hint
    ): string {
        $context = \context_module::instance($sourcecm->id);
        $intro = format_text($record->intro ?? '', $record->introformat ?? FORMAT_HTML, ['context' => $context]);

        return $this->render_compact_document($course, $sourcecm, [
            'type' => $typename,
            'title' => format_string($sourcecm->name),
            'description' => $intro,
            'benefit' => $hint,
        ]);
    }

    /**
     * Render compact document.
     *
     * @param \stdClass $course The course record.
     * @param ?\cm_info $sourcecm The sourcecm.
     * @param array $data The form data.
     * @return string The result.
     */
    protected function render_compact_document(\stdClass $course, ?\cm_info $sourcecm, array $data): string {
        $activitylink = null;
        $sectionname = '';
        if ($sourcecm) {
            $activitylink = new \moodle_url('/mod/' . $sourcecm->modname . '/view.php', ['id' => $sourcecm->id]);
            $sectionname = format_string($sourcecm->get_section_info()->name ?? '') ?: get_section_name(
                $course,
                $sourcecm->sectionnum
            );
        }

        $type = $this->normalise_type((string)($data['type'] ?? ''), $sourcecm ? $sourcecm->modname : '');
        $description = (string)($data['description'] ?? '');
        $content = (string)($data['content'] ?? '');
        if (trim(strip_tags($content)) !== '') {
            if (trim(strip_tags($description)) !== '') {
                $description .= "\n\nINHALT:\n" . $content;
            } else {
                $description = $content;
            }
        }

        $link = trim((string)($data['link'] ?? ''));
        $profiletags = $this->get_activity_profile_tags($sourcecm);
        if ($link === '' && $activitylink) {
            $link = $activitylink->out(false);
        }

        return $this->render_entry_document($course, $sourcecm, [
            'title' => (string)($data['title'] ?? ''),
            'type' => $type,
            'category' => (string)($data['category'] ?? ($sectionname ?: $type)),
            'description' => $description,
            'benefit' => (string)($data['benefit'] ?? $this->default_benefit($type)),
            'course' => format_string($course->fullname),
            'link' => $link,
        ]);
    }


    /**
     * Get activity profile fields.
     *
     * @param ?\cm_info $sourcecm The sourcecm.
     * @return array The result.
     */
    protected function get_activity_profile_fields(?\cm_info $sourcecm): array {
        global $DB;
        if (!$sourcecm || empty($sourcecm->id)) {
            return [];
        }
        if (!$DB->get_manager()->table_exists('customfield_data') || !$DB->get_manager()->table_exists('customfield_field')) {
            return [];
        }

        $hascategory = $DB->get_manager()->table_exists('customfield_category');
        $select = "d.id, d.value, d.charvalue, d.shortcharvalue, d.intvalue, d.decvalue, f.name, f.shortname, f.type, f.configdata";
        $from = "{customfield_data} d JOIN {customfield_field} f ON f.id = d.fieldid";
        $where = "d.instanceid = :instanceid";
        if ($hascategory) {
            $from .= " LEFT JOIN {customfield_category} c ON c.id = f.categoryid";
            $where .= " AND (c.component = 'core_course' OR c.component = 'local_modcustomfields'" .
                " OR c.component LIKE 'mod_%' OR c.component IS NULL)";
        }

        $records = $DB->get_records_sql("SELECT $select FROM $from WHERE $where", [
            'instanceid' => (int)$sourcecm->id,
        ]);
        $fields = [];
        foreach ($records as $record) {
            $name = trim((string)($record->name ?? $record->shortname ?? ''));
            if ($name === '') {
                continue;
            }
            $value = $this->normalise_customfield_value($record);
            if ($value === '') {
                continue;
            }
            $fields[$name] = $value;
        }
        return $fields;
    }

    /**
     * Normalise customfield value.
     *
     * @param \stdClass $record The record.
     * @return string The result.
     */
    protected function normalise_customfield_value(\stdClass $record): string {
        $raw = '';
        foreach (['value', 'charvalue', 'shortcharvalue', 'intvalue', 'decvalue'] as $field) {
            if (isset($record->{$field}) && trim((string)$record->{$field}) !== '') {
                $raw = trim((string)$record->{$field});
                break;
            }
        }
        if ($raw === '' || $raw === '0') {
            return '';
        }
        $mapped = $this->map_select_customfield_value($raw, (string)($record->configdata ?? ''));
        if ($mapped !== '') {
            $raw = $mapped;
        }
        return $this->plain_text($raw);
    }

    /**
     * Map select customfield value.
     *
     * @param string $raw The raw.
     * @param string $configdata The configdata.
     * @return string The result.
     */
    protected function map_select_customfield_value(string $raw, string $configdata): string {
        if ($configdata === '' || !preg_match('/^-?\d+$/', $raw)) {
            return '';
        }
        $config = json_decode($configdata, true);
        if (!is_array($config)) {
            return '';
        }
        $options = $config['options'] ?? $config['menuoptions'] ?? $config['choices'] ?? null;
        if (is_string($options)) {
            $options = preg_split('/\R+/', $options, -1, PREG_SPLIT_NO_EMPTY);
        }
        if (!is_array($options)) {
            return '';
        }
        $idx = (int)$raw;
        if ($idx <= 0) {
            return '';
        }
        if (isset($options[$raw]) && is_scalar($options[$raw])) {
            return trim((string)$options[$raw]);
        }
        if ($idx > 0 && isset($options[$idx - 1]) && is_scalar($options[$idx - 1])) {
            return trim((string)$options[$idx - 1]);
        }
        if (isset($options[$idx]) && is_scalar($options[$idx])) {
            return trim((string)$options[$idx]);
        }
        return '';
    }

    /**
     * Get activity profile tags.
     *
     * @param ?\cm_info $sourcecm The sourcecm.
     * @return string The result.
     */
    protected function get_activity_profile_tags(?\cm_info $sourcecm): string {
        $fields = $this->get_activity_profile_fields($sourcecm);
        if (empty($fields)) {
            return '';
        }
        $parts = [];
        foreach ($fields as $name => $value) {
            $parts[] = trim($name . ': ' . $value);
        }
        return implode(', ', $parts);
    }

    /**
     * Render entry document.
     *
     * @param \stdClass $course The course record.
     * @param ?\cm_info $sourcecm The sourcecm.
     * @param array $data The form data.
     * @return string The result.
     */
    protected function render_entry_document(\stdClass $course, ?\cm_info $sourcecm, array $data): string {
        $title = trim(strip_tags((string)($data['title'] ?? '')));
        $type = $this->normalise_type((string)($data['type'] ?? ''), $sourcecm ? $sourcecm->modname : '');
        $category = trim(strip_tags((string)($data['category'] ?? '')));
        $description = $this->plain_text((string)($data['description'] ?? ''));
        $benefit = $this->plain_text((string)($data['benefit'] ?? ''));
        $coursename = trim(strip_tags((string)($data['course'] ?? format_string($course->fullname))));
        $link = trim((string)($data['link'] ?? ''));
        $profiletags = $this->get_activity_profile_tags($sourcecm);

        if ($title === '') {
            $title = get_string('pluginname', 'block_fredkitheagent');
        }
        if ($category === '') {
            $category = $type;
        }
        if ($description === '') {
            $description = 'Dieser Moodle-Inhalt gehört zum angegebenen Kurs und kann über den Link direkt geöffnet werden.';
        }
        if ($benefit === '') {
            $benefit = $this->default_benefit($type);
        }
        if ($link === '') {
            // Einträge ohne Link sollen nicht entstehen. Als Sicherheitsnetz bleibt der Kurslink erhalten.
            $link = (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
        }

        $body = "START EINTRAG\n\n" .
            "TITEL:\n" . $title . "\n\n" .
            "TYP:\n" . $type . "\n\n" .
            ($profiletags !== '' ? "SCHLAGWORTE / PROFILFELDER:\n" . $profiletags . "\n\n" : '') .
            "KATEGORIE:\n" . $category . "\n\n" .
            "KURZBESCHREIBUNG:\n" . $description . "\n\n" .
            "NUTZEN:\n" . $benefit . "\n\n" .
            "KURS:\n" . ($coursename !== '' ? $coursename : '—') . "\n\n" .
            "LINK:\n" . $link . "\n\n" .
            "ENDE EINTRAG";

        return '<html><head><meta charset="utf-8"></head><body><div class="fredki-entry">' . nl2br(
            s($body),
            false
        ) . '</div></body></html>';
    }

    /**
     * Normalise type.
     *
     * @param string $type The type.
     * @param string $modname The modname.
     * @return string The result.
     */
    protected function normalise_type(string $type, string $modname = ''): string {
        $type = trim($type);
        $modname = trim($modname);
        if ($modname === 'scorm' || $modname === 'h5pactivity' || $modname === 'lesson') {
            return 'Lernprogramm';
        }
        if (in_array($modname, ['resource', 'folder', 'url'], true)) {
            return 'Material';
        }
        if (in_array($modname, ['page', 'book', 'glossary', 'label'], true)) {
            return 'Wissen';
        }
        $map = [
            'SCORM' => 'Lernprogramm',
            'H5P' => 'Lernprogramm',
            'Lektion' => 'Lernprogramm',
            'Buch' => 'Wissen',
            'Glossar' => 'Wissen',
            'Link' => 'Material',
            'Seite' => 'Wissen',
            'Abschnitt' => 'Wissen',
            'Aktivitätsbeschreibung' => 'Material',
        ];
        if (isset($map[$type])) {
            return $map[$type];
        }
        $allowed = ['Lernprogramm', 'Vorlage', 'Checkliste', 'Material', 'Kurs', 'Wissen'];
        return in_array($type, $allowed, true) ? $type : 'Wissen';
    }

    /**
     * Default benefit.
     *
     * @param string $type The type.
     * @return string The result.
     */
    protected function default_benefit(string $type): string {
        switch ($type) {
            case 'Lernprogramm':
                return 'Hilft dabei, den Inhalt strukturiert zu erarbeiten und direkt im Moodle-Kurs zu starten.';
            case 'Vorlage':
            case 'Checkliste':
                return 'Hilft bei der praktischen Umsetzung und kann direkt als Arbeitsgrundlage genutzt werden.';
            case 'Material':
                return 'Hilft dabei, den Moodle-Inhalt schnell einzuordnen und direkt aufzurufen.';
            case 'Kurs':
                return 'Bietet einen strukturierten Überblick über zusammengehörige Inhalte.';
            case 'Wissen':
            default:
                return 'Hilft dabei, den Inhalt fachlich einzuordnen und passende Informationen im Kurs schnell wiederzufinden.';
        }
    }

    /**
     * Plain text.
     *
     * @param string $html The html.
     * @return string The result.
     */
    protected function plain_text(string $html): string {
        $text = html_to_text($html, 0, false);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }

    /**
     * Build safe filename.
     *
     * @param string $name The name.
     * @return string The result.
     */
    protected function build_safe_filename(string $name): string {
        $name = clean_filename($name);
        return $name !== '' ? $name : ('export-' . time() . '.html');
    }
}

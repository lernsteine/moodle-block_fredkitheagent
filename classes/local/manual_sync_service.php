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
 * Metadata tag helpers for synchronised course files.
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Manual sync service class for FredKI course import.
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_fredkitheagent\local;

/**
 * Manual sync service class.
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manual_sync_service {
    /**
     * Resolve custom activity fields ("Weitere Felder" / activity profile fields) for the
     * original Moodle activity and return them as FredKI tags. This intentionally does not
     * depend on one exact Moodle customfield schema because activity custom fields differ
     * between Moodle versions. It first resolves the original cmid from the generated file path
     * and then reads all customfield_data rows for that cmid.
     *
     * @param \stored_file $file The file.
     * @return string The result.
     */
    public static function get_moodle_activity_profile_tags(\stored_file $file): string {
        // The tags depend only on the source activity, not on the individual file. Cache them per
        // request so a course with many files does not repeat the same custom-field queries.
        static $cache = [];
        $sourcecmid = self::get_source_cmid_from_file($file);
        if ($sourcecmid <= 0) {
            return '';
        }
        if (!array_key_exists($sourcecmid, $cache)) {
            $cache[$sourcecmid] = self::build_moodle_activity_profile_tags($file);
        }
        return $cache[$sourcecmid];
    }

    /**
     * Table available.
     *
     * @param string $table The table.
     * @return bool True on success.
     */
    private static function table_available(string $table): bool {
        global $DB;
        static $known = [];
        if (!array_key_exists($table, $known)) {
            $known[$table] = $DB->get_manager()->table_exists($table);
        }
        return $known[$table];
    }

    /**
     * Build moodle activity profile tags.
     *
     * @param \stored_file $file The file.
     * @return string The result.
     */
    private static function build_moodle_activity_profile_tags(\stored_file $file): string {
        global $DB;

        $sourcecmid = self::get_source_cmid_from_file($file);
        tag_debugger::log(
            'CUSTOMFIELD STEP 1 SOURCE CMID',
            ['filename' => $file->get_filename(), 'filepath' => $file->get_filepath(), 'sourcecmid' => $sourcecmid]
        );
        if ($sourcecmid <= 0) {
            tag_debugger::log(
                'CUSTOMFIELD FAIL NO SOURCE CMID',
                ['filename' => $file->get_filename(), 'filepath' => $file->get_filepath()]
            );
            return '';
        }
        if (!self::table_available('customfield_data') || !self::table_available('customfield_field')) {
            tag_debugger::log(
                'CUSTOMFIELD FAIL TABLE MISSING',
                [
                    'customfield_data' => $DB->get_manager()->table_exists('customfield_data'),
                    'customfield_field' => $DB->get_manager()->table_exists('customfield_field'),
                ]
            );
            return '';
        }

        $hascategory = self::table_available('customfield_category');
        $select = "d.id, d.value, d.charvalue, d.shortcharvalue, d.intvalue, d.decvalue, f.name, f.shortname, f.type, f.configdata";
        $from = "{customfield_data} d JOIN {customfield_field} f ON f.id = d.fieldid";
        $where = "d.instanceid = :instanceid";
        $params = ['instanceid' => $sourcecmid];
        if ($hascategory) {
            $from .= " LEFT JOIN {customfield_category} c ON c.id = f.categoryid";
            // In Moodle 4.x activity custom fields normally use core_course/customfield_coursemodule.
            // Some installations store the area slightly differently; keep the filter permissive.
            $where .= " AND (c.component = 'core_course' OR c.component = 'local_modcustomfields'" .
                " OR c.component LIKE 'mod_%' OR c.component IS NULL)";
        }

        $records = $DB->get_records_sql("SELECT $select FROM $from WHERE $where", $params);
        tag_debugger::log(
            'CUSTOMFIELD STEP 2 SQL RESULT',
            ['sourcecmid' => $sourcecmid, 'count' => count($records), 'hascategory' => $hascategory, 'sqlwhere' => $where]
        );

        // Prefer Moodle's own local_modcustomfields/core_customfield controllers for display values.
        // This avoids the dangerous manual select mapping that can turn stored value "1" into the
        // second option instead of the first one. The SQL fallback below is kept only for older sites
        // or unusual schemas where the controller classes are unavailable.
        $parts = self::get_activity_profile_tags_via_customfield_api($sourcecmid);
        if (!empty($parts)) {
            $result = implode(', ', array_values(array_unique($parts)));
            tag_debugger::log('CUSTOMFIELD STEP 4 TAG STRING API', ['sourcecmid' => $sourcecmid, 'result' => $result]);
            return $result;
        }

        $parts = [];
        foreach ($records as $record) {
            $name = trim((string)($record->name ?? $record->shortname ?? ''));
            if ($name === '') {
                continue;
            }
            $value = self::normalise_customfield_value($record);
            if ($value === '') {
                continue;
            }
            tag_debugger::log(
                'CUSTOMFIELD STEP 3 FIELD FOUND',
                [
                    'sourcecmid' => $sourcecmid,
                    'name' => $name,
                    'shortname' => $record->shortname ?? '',
                    'type' => $record->type ?? '',
                    'raw_value' => $record->value ?? null,
                    'charvalue' => $record->charvalue ?? null,
                    'intvalue' => $record->intvalue ?? null,
                    'normalised' => $value,
                ]
            );
            $parts[] = $name . ': ' . $value;
        }
        $result = implode(', ', array_values(array_unique($parts)));
        tag_debugger::log('CUSTOMFIELD STEP 4 TAG STRING', ['sourcecmid' => $sourcecmid, 'result' => $result]);
        return $result;
    }

    /**
     * Get activity profile tags via customfield api.
     *
     * @param int $sourcecmid The sourcecmid.
     * @return array The result.
     */
    protected static function get_activity_profile_tags_via_customfield_api(int $sourcecmid): array {
        $parts = [];
        try {
            if (!class_exists('\local_modcustomfields\customfield\mod_handler')) {
                return [];
            }
            $handler = \local_modcustomfields\customfield\mod_handler::create();
            if (!method_exists($handler, 'get_instance_data')) {
                return [];
            }
            $fields = $handler->get_instance_data($sourcecmid, false);
            foreach ($fields as $data) {
                if (!is_object($data) || !method_exists($data, 'get_field')) {
                    continue;
                }
                $field = $data->get_field();
                $name = '';
                if (is_object($field) && method_exists($field, 'get')) {
                    $name = trim((string)($field->get('name') ?: $field->get('shortname')));
                }
                if ($name === '') {
                    continue;
                }

                $value = '';
                if (method_exists($data, 'export_value')) {
                    $value = self::clean_customfield_display_value($data->export_value());
                }
                if ($value === '' && method_exists($data, 'get_value')) {
                    $value = self::clean_customfield_display_value($data->get_value());
                }
                if ($value === '') {
                    continue;
                }
                tag_debugger::log('CUSTOMFIELD API FIELD FOUND', ['sourcecmid' => $sourcecmid, 'name' => $name, 'value' => $value]);
                $parts[] = $name . ': ' . $value;
            }
        } catch (\Throwable $e) {
            tag_debugger::log(
                'CUSTOMFIELD API FALLBACK',
                ['sourcecmid' => $sourcecmid, 'error' => get_string('apierror', 'block_fredkitheagent')]
            );
            return [];
        }
        return $parts;
    }

    /**
     * Clean customfield display value.
     *
     * @param mixed $value The value.
     * @return string The result.
     */
    protected static function clean_customfield_display_value($value): string {
        if (is_array($value)) {
            $value = implode(', ', array_filter(array_map('strval', $value), static function ($v) {
                return trim($v) !== '';
            }));
        }
        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                $value = (string)$value;
            } else {
                return '';
            }
        }
        $value = html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = trim(preg_replace('/\s+/', ' ', $value));
        // In local_modcustomfields/select fields, an unset selection may be stored/exported as 0.
        // 0 is not a real option and must not become a FredKI tag.
        if ($value === '0') {
            return '';
        }
        return $value;
    }

    /**
     * Get source cmid from file.
     *
     * @param \stored_file $file The file.
     * @return int The result.
     */
    protected static function get_source_cmid_from_file(\stored_file $file): int {
        $filepath = $file->get_filepath();
        $filename = $file->get_filename();
        if (preg_match('~^/course-files/cm(\d+)(?:/|$)~', $filepath, $matches)) {
            return (int)$matches[1];
        }
        if (preg_match('~cm(\d+)~', $filename, $matches)) {
            return (int)$matches[1];
        }
        return 0;
    }

    /**
     * Normalise customfield value.
     *
     * @param \stdClass $record The record.
     * @return string The result.
     */
    protected static function normalise_customfield_value(\stdClass $record): string {
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

        $mapped = self::map_select_customfield_value($raw, (string)($record->configdata ?? ''));
        if ($mapped !== '') {
            $raw = $mapped;
        }
        $raw = html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/', ' ', $raw));
    }

    /**
     * Map select customfield value.
     *
     * @param string $raw The raw.
     * @param string $configdata The configdata.
     * @return string The result.
     */
    protected static function map_select_customfield_value(string $raw, string $configdata): string {
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

        // Moodle select custom fields store the selected option as a 1-based value, while the
        // decoded options array is normally zero-based. Therefore value "1" means the first
        // option, not $options[1]. Checking $options[$raw] first caused exactly the observed bug:
        // "Einsteiger" was read as "Praktiker".
        if ($idx > 0 && array_key_exists($idx - 1, $options) && is_scalar($options[$idx - 1])) {
            return trim((string)$options[$idx - 1]);
        }
        if (array_key_exists($raw, $options) && is_scalar($options[$raw])) {
            return trim((string)$options[$raw]);
        }
        if (array_key_exists($idx, $options) && is_scalar($options[$idx])) {
            return trim((string)$options[$idx]);
        }
        return '';
    }
}

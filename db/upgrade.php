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
 * FredKI The Agent: upgrade.php
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade block_fredkitheagent.
 *
 * @param int $oldversion Installed plugin version.
 * @return bool
 */
function xmldb_block_fredkitheagent_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026060800) {
        $table = new xmldb_table('fredkitheagent_map');
        $field = new xmldb_field('metahash', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null, 'filesize');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_block_savepoint(true, 2026060800, 'fredkitheagent');
    }

    if ($oldversion < 2026072900) {
        $table = new xmldb_table('fredkitheagent');
        foreach (['knowledgeid'] as $fieldname) {
            $field = new xmldb_field($fieldname, XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
            if ($dbman->field_exists($table, $field)) {
                $dbman->change_field_default($table, $field);
            }
        }

        $maptable = new xmldb_table('fredkitheagent_map');
        $index = new xmldb_index('pathnamehash_uix', XMLDB_INDEX_UNIQUE, ['activityid', 'pathnamehash']);
        if ($dbman->index_exists($maptable, $index)) {
            $dbman->drop_index($maptable, $index);
        }

        foreach (
            [
            ['pathnamehash', '40'],
            ['contenthash', '40'],
            ['filename', '255'],
            ['metahash', '40'],
            ] as [$fieldname, $length]
        ) {
            $field = new xmldb_field($fieldname, XMLDB_TYPE_CHAR, $length, null, XMLDB_NOTNULL, null, null);
            if ($dbman->field_exists($maptable, $field)) {
                $dbman->change_field_precision($maptable, $field);
                $dbman->change_field_default($maptable, $field);
            }
        }

        if (!$dbman->index_exists($maptable, $index)) {
            $dbman->add_index($maptable, $index);
        }

        upgrade_block_savepoint(true, 2026072900, 'fredkitheagent');
    }

    if ($oldversion < 2026101200) {
        $table = new xmldb_table('fredkitheagent');
        $field = new xmldb_field('managedtargetid', XMLDB_TYPE_CHAR, '64', null, null, null, null, 'timesynced');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_block_savepoint(true, 2026101200, 'fredkitheagent');
    }

    if ($oldversion < 2026101201) {
        $table = new xmldb_table('fredkitheagent');
        $field = new xmldb_field(
            'verifiedat',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            null,
            null,
            null,
            'managedtargetid'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_block_savepoint(true, 2026101201, 'fredkitheagent');
    }

    // New course agents default to hidden until a teacher explicitly enables their chat.
    // Keep the values of existing courses unchanged (no surprise visibility change on upgrade).
    if ($oldversion < 2026102200) {
        $table = new xmldb_table('fredkitheagent');
        $field = new xmldb_field(
            'agentcoursevisible',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'fredkichaturl'
        );
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_default($table, $field);
        }
        upgrade_block_savepoint(true, 2026102200, 'fredkitheagent');
    }

    // Optional per-course presentation: never modify stored agent or knowledge assignments.
    if ($oldversion < 2026102201) {
        $table = new xmldb_table('fredkitheagent_design');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('agentid', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
            foreach (['headercolor', 'accentcolor', 'backgroundcolor', 'botbubblecolor', 'userbubblecolor'] as $name) {
                $table->add_field($name, XMLDB_TYPE_CHAR, '7', null, null, null, null);
            }
            $table->add_field('welcometext', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('footertext', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            // A foreign-key declaration here would generate an implicit index on courseid.
            // Moodle XMLDB refuses to add the unique course_uix on those same columns.
            // Keep the unique index: one set of visual settings per Moodle course.
            $table->add_index('course_uix', XMLDB_INDEX_UNIQUE, ['courseid']);
            $dbman->create_table($table);
        } else {
            // Safe when a previous, interrupted upgrade created the table but did not
            // finish creating the uniqueness constraint. Never delete existing records.
            $index = new xmldb_index('course_uix', XMLDB_INDEX_UNIQUE, ['courseid']);
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }
        upgrade_block_savepoint(true, 2026102201, 'fredkitheagent');
    }

    // Finish upgrade to the corrected version even if no schema changes are needed.
    if ($oldversion < 2026102202) {
        upgrade_block_savepoint(true, 2026102202, 'fredkitheagent');
    }

    // No schema change: fast, bounded incremental course sync and manual full check.
    if ($oldversion < 2026102203) {
        upgrade_block_savepoint(true, 2026102203, 'fredkitheagent');
    }

    // Table names must carry the full component prefix (block_fredkitheagent*).
    if ($oldversion < 2026102400) {
        foreach (['fredkitheagent', 'fredkitheagent_map', 'fredkitheagent_design'] as $oldname) {
            $oldtable = new xmldb_table($oldname);
            if ($dbman->table_exists($oldtable) && !$dbman->table_exists(new xmldb_table('block_' . $oldname))) {
                $dbman->rename_table($oldtable, 'block_' . $oldname);
            }
        }
        upgrade_block_savepoint(true, 2026102400, 'fredkitheagent');
    }

    // 1.4.1: the FredKI licence server moved to fredki-theagent.lernsteine.de. A stored copy of the
    // former default address is replaced; an address the administrator entered deliberately is kept.
    if ($oldversion < 2026102700) {
        $old = 'https://bildungbleibt.de/fredki-hub/api/v1/index.php';
        if (trim((string)get_config('block_fredkitheagent', 'managed_api_url')) === $old) {
            $new = 'https://fredki-theagent.lernsteine.de/fredki-hub/api/v1/index.php';
            set_config('managed_api_url', $new, 'block_fredkitheagent');
        }
        upgrade_block_savepoint(true, 2026102700, 'fredkitheagent');
    }

    // 1.4.2: the licence server subdomain serves the API at its root (without /fredki-hub/).
    if ($oldversion < 2026102701) {
        $stored = trim((string)get_config('block_fredkitheagent', 'managed_api_url'));
        $former = [
            'https://bildungbleibt.de/fredki-hub/api/v1/index.php',
            'https://fredki-theagent.lernsteine.de/fredki-hub/api/v1/index.php',
        ];
        if (in_array($stored, $former, true)) {
            set_config('managed_api_url', 'https://fredki-theagent.lernsteine.de/api/v1/index.php', 'block_fredkitheagent');
        }
        upgrade_block_savepoint(true, 2026102701, 'fredkitheagent');
    }

    // 1.6.0: agent role per course.
    if ($oldversion < 2026110400) {
        $table = new xmldb_table('block_fredkitheagent');
        $field = new xmldb_field('agentrole', XMLDB_TYPE_CHAR, '40', null, null, null, null, 'verifiedat');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_block_savepoint(true, 2026110400, 'fredkitheagent');
    }

    return true;
}

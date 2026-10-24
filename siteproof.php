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
 * Public site-ownership proof endpoint.
 *
 * The FredKI licence server fetches this URL (without any Moodle session) to confirm that the
 * registering installation really controls this Moodle site. It returns only the random installation
 * identifier and the SHA-256 hash of the 256-bit installation secret; the secret itself is never
 * revealed and no user or course data is exposed. The endpoint answers 404 until the site is registered.
 *
 * @package    block_fredkitheagent
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);

// This endpoint is intentionally public (see the file description); it discloses no user or course data.
// phpcs:ignore moodle.Files.RequireLogin.Missing
require_once(__DIR__ . '/../../config.php');
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$id = (string)get_config('block_fredkitheagent', 'managed_installation_id');
$secret = (string)get_config('block_fredkitheagent', 'managed_installation_secret');
if (!preg_match('/^[a-f0-9]{32}$/D', $id) || !preg_match('/^[a-f0-9]{64}$/D', $secret)) {
    http_response_code(404);
    exit;
}
echo json_encode(['installation_id' => $id, 'proof' => hash('sha256', $secret)], JSON_THROW_ON_ERROR);

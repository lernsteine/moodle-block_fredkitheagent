// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Continue a staged synchronisation automatically with the next stage.
 *
 * @module     block_fredkitheagent/sync_continue
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Submit the continue form shortly after the progress page is shown. The manual button only appears
 * if nothing has happened after a while.
 *
 * @param {string} formid Id of the continue form.
 */
export const init = (formid) => {
    const form = document.getElementById(formid);
    if (!form) {
        return;
    }
    form.hidden = true;
    window.setTimeout(() => {
        form.hidden = false;
    }, 15000);
    window.setTimeout(() => {
        form.submit();
    }, 400);
};
